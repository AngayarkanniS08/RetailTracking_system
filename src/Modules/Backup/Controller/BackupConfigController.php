<?php
namespace Modules\Backup\Controller;

use Core\Middlewares\AuthMiddleware;
use Modules\Backup\Service\GoogleDriveService;
use Modules\Backup\Service\BackupService;
use Modules\Backup\Repository\BackupRepository;
use Modules\Backup\DTO\BackupConfigDTO;
use Modules\Backup\Model\BackupConfig;
use Core\Cache\ValkeyCache;

class BackupConfigController
{
    private BackupService $service;
    private GoogleDriveService $driveService;
    private BackupRepository $repo;

    public function __construct()
    {
        $this->repo = new BackupRepository();
        $this->driveService = new GoogleDriveService();
        $this->service = new BackupService($this->repo, $this->driveService);
    }

    public function get(): void
    {
        header('Content-Type: application/json');

        // Default response when DB tables are missing
        $defaultResponse = [
            'gdrive_connected' => false,
            'gdrive_auth_email' => null,
            'gdrive_backup_folder_id' => null,
            'schedule_enabled' => false,
            'schedule_time' => '22:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 12,
            'last_backup_at' => null,
            'last_backup_status' => 'never'
        ];

        // Check file tokens even if DB is wiped
        $fileTokens = $this->driveService->loadTokens();
        if (!empty($fileTokens['refresh_token'])) {
            $defaultResponse['gdrive_connected'] = true;
            $defaultResponse['gdrive_auth_email'] = $fileTokens['auth_email'] ?? null;
            $defaultResponse['gdrive_backup_folder_id'] = $fileTokens['folder_id'] ?? null;
        }

        try {
            $config = $this->repo->getConfig('');
        } catch (\PDOException $e) {
            // backup_config table doesn't exist (DB wiped) — return defaults with file tokens
            echo json_encode($defaultResponse);
            return;
        }

        // Migrate existing DB tokens to file (one-time, so Drive works after DB wipe)
        if ($config && empty($fileTokens['refresh_token']) && !empty($config->gdriveRefreshToken)) {
            $this->driveService->saveTokens([
                'refresh_token' => $config->gdriveRefreshToken,
                'folder_id' => $config->gdriveBackupFolderId,
                'auth_email' => $config->gdriveAuthEmail,
            ]);
        }

        if (!$config) {
            echo json_encode($defaultResponse);
            return;
        }

        echo json_encode([
            'gdrive_connected' => !empty($config->gdriveRefreshToken),
            'gdrive_auth_email' => $config->gdriveAuthEmail,
            'gdrive_backup_folder_id' => $config->gdriveBackupFolderId,
            'schedule_enabled' => $config->scheduleEnabled,
            'schedule_time' => $config->scheduleTime,
            'retention_daily' => $config->retentionDaily,
            'retention_weekly' => $config->retentionWeekly,
            'retention_monthly' => $config->retentionMonthly,
            'last_backup_at' => $config->lastBackupAt,
            'last_backup_status' => $config->lastBackupStatus
        ]);
    }

    public function update(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? '';
        $input = json_decode(file_get_contents('php://input'), true);

        $dto = BackupConfigDTO::fromArray($input);

        // Sync folder_id to file (survives DB wipe)
        $newFolderId = $dto->gdriveBackupFolderId;
        if (!$newFolderId) {
            $fileTokens = $this->driveService->loadTokens();
            $newFolderId = $fileTokens['folder_id'] ?? null;
        }
        if ($newFolderId) {
            $this->driveService->saveTokens(['folder_id' => $newFolderId]);
        }

        // Save to DB for backward compat with schedule/retention
        try {
            $config = $this->repo->getConfig($userId);
            $currentGdriveToken = $config ? $config->gdriveRefreshToken : null;
            $currentAuthEmail = $config ? $config->gdriveAuthEmail : null;

            $updated = new BackupConfig(
                id: $config?->id,
                userId: $userId,
                gdriveRefreshToken: $currentGdriveToken,
                gdriveBackupFolderId: $newFolderId,
                gdriveAuthEmail: $currentAuthEmail,
                scheduleEnabled: $dto->scheduleEnabled,
                scheduleTime: $dto->scheduleTime,
                retentionDaily: $dto->retentionDaily,
                retentionWeekly: $dto->retentionWeekly,
                retentionMonthly: $dto->retentionMonthly,
                lastBackupAt: $config?->lastBackupAt,
                lastBackupStatus: $config?->lastBackupStatus ?? 'never',
                createdAt: $config?->createdAt,
                updatedAt: null
            );
            $this->repo->saveConfig($updated);
        } catch (\PDOException $e) {
            // backup_config table may not exist (DB wiped) — file save is sufficient
        }

        echo json_encode(['success' => true]);
    }

    public function authUrl(): void
    {
        header('Content-Type: application/json');
        AuthMiddleware::authenticate();

        try {
            $clientConfigPath = __DIR__ . '/../../../../config/google_client.json';
            if (!file_exists($clientConfigPath)) {
                http_response_code(400);
                echo json_encode(['error' => 'Google client config not found. Place config/google_client.json']);
                return;
            }
            $this->driveService->setAuthConfig(json_decode(file_get_contents($clientConfigPath), true));
            $url = $this->driveService->getAuthUrl();
            echo json_encode(['auth_url' => $url]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function exchangeCode(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? '';
        $input = json_decode(file_get_contents('php://input'), true);
        $code = $input['code'] ?? '';

        if (empty($code)) {
            http_response_code(422);
            echo json_encode(['error' => 'Authorization code is required']);
            return;
        }

        try {
            $clientConfigPath = __DIR__ . '/../../../../config/google_client.json';
            if (!file_exists($clientConfigPath)) {
                http_response_code(400);
                echo json_encode(['error' => 'Google client config not found']);
                return;
            }
            $this->driveService->setAuthConfig(json_decode(file_get_contents($clientConfigPath), true));
            $token = $this->driveService->exchangeAuthCode($code);

            // Save tokens to file (survives DB wipe)
            $this->driveService->saveTokens([
                'refresh_token' => $token['refresh_token'] ?? null,
                'auth_email' => $token['email'] ?? null,
            ]);

            // Also save to DB for backward compat with schedule/retention
        try {
            $config = $this->repo->getConfig('');
                $updated = new BackupConfig(
                    id: $config?->id,
                    userId: $userId,
                    gdriveRefreshToken: $token['refresh_token'] ?? null,
                    gdriveBackupFolderId: $config?->gdriveBackupFolderId,
                    gdriveAuthEmail: $token['email'] ?? null,
                    scheduleEnabled: $config?->scheduleEnabled ?? false,
                    scheduleTime: $config?->scheduleTime ?? '22:00',
                    retentionDaily: $config?->retentionDaily ?? 7,
                    retentionWeekly: $config?->retentionWeekly ?? 4,
                    retentionMonthly: $config?->retentionMonthly ?? 12,
                    lastBackupAt: $config?->lastBackupAt,
                    lastBackupStatus: $config?->lastBackupStatus ?? 'never',
                    createdAt: $config?->createdAt,
                    updatedAt: null
                );
                $this->repo->saveConfig($updated);
            } catch (\PDOException $e) {
                // backup_config table may not exist (DB wiped) — file save is sufficient
            }

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
