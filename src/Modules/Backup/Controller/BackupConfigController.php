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
        $this->driveService = new GoogleDriveService($this->repo);
        $this->service = new BackupService($this->repo, $this->driveService);
    }

    public function get(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = $user->data->user_id ?? '';

        $config = $this->repo->getConfig($userId);
        if (!$config) {
            echo json_encode([
                'gdrive_connected' => false,
                'schedule_enabled' => false,
                'schedule_time' => '22:00',
                'retention_daily' => 7,
                'retention_weekly' => 4,
                'retention_monthly' => 12,
                'last_backup_at' => null,
                'last_backup_status' => 'never'
            ]);
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

        $config = $this->repo->getConfig($userId);
        $currentGdriveToken = $config ? $config->gdriveRefreshToken : null;
        $currentAuthEmail = $config ? $config->gdriveAuthEmail : null;
        $currentFolderId = $config ? $config->gdriveBackupFolderId : null;

        $updated = new BackupConfig(
            id: $config?->id,
            userId: $userId,
            gdriveRefreshToken: $currentGdriveToken,
            gdriveBackupFolderId: $dto->gdriveBackupFolderId ?: $currentFolderId,
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

            $config = $this->repo->getConfig($userId);
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

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
