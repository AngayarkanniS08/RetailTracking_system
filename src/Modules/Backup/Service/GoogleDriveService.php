<?php
namespace Modules\Backup\Service;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Modules\Backup\Model\BackupFileInfo;

class GoogleDriveService
{
    private const APP_NAME = 'RetailTrackingBackup';
    private const SCOPES = [Drive::DRIVE_FILE];
    private const MIME_TYPE_GZIP = 'application/gzip';
    private const TOKENS_PATH = __DIR__ . '/../../../../config/gdrive_tokens.json';

    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setApplicationName(self::APP_NAME);
        $this->client->setScopes(self::SCOPES);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $http = new \GuzzleHttp\Client([
            'connect_timeout' => 10,
            'timeout'         => 120,
        ]);
        $this->client->setHttpClient($http);
    }

    public function loadTokens(): array
    {
        if (!file_exists(self::TOKENS_PATH)) {
            return [];
        }
        return json_decode(file_get_contents(self::TOKENS_PATH), true) ?: [];
    }

    public function saveTokens(array $data): void
    {
        $existing = $this->loadTokens();
        $merged = array_merge($existing, $data);
        file_put_contents(self::TOKENS_PATH, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function authenticate(): void
    {
        $clientConfigPath = __DIR__ . '/../../../../config/google_client.json';
        if (!file_exists($clientConfigPath)) {
            throw new \RuntimeException("Google client config not found at {$clientConfigPath}");
        }

        $tokens = $this->loadTokens();
        if (empty($tokens['refresh_token'])) {
            throw new \RuntimeException("Google Drive not authenticated");
        }

        $this->client->setAuthConfig(json_decode(file_get_contents($clientConfigPath), true));
        $this->client->setAccessToken([
            'refresh_token' => $tokens['refresh_token'],
            'access_token' => '',
            'expires_in' => 3600
        ]);
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken();
        }
    }

    public function getFolderId(): ?string
    {
        $tokens = $this->loadTokens();
        return $tokens['folder_id'] ?? null;
    }

    public function setAuthConfig(array $config): void
    {
        $this->client->setAuthConfig($config);
    }

    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    public function exchangeAuthCode(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException("Failed to exchange auth code: " . ($token['error_description'] ?? $token['error']));
        }
        return $token;
    }

    private function getDriveService(): Drive
    {
        return new Drive($this->client);
    }

    public function uploadFile(string $filePath, ?string $folderId = null): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $fileName = basename($filePath);
        $fileMetadata = new Drive\DriveFile([
            'name' => $fileName,
            'parents' => $folderId ? [$folderId] : []
        ]);

        $file = $this->getDriveService()->files->create($fileMetadata, [
            'data' => file_get_contents($filePath),
            'mimeType' => self::MIME_TYPE_GZIP,
            'uploadType' => 'multipart',
            'supportsAllDrives' => true
        ]);

        return $file->id;
    }

    public function listBackupFiles(?string $folderId = null): array
    {
        $query = "name contains 'backup_' and mimeType = '" . self::MIME_TYPE_GZIP . "'";
        if ($folderId) {
            $query .= " and '{$folderId}' in parents";
        }
        $query .= " and trashed = false";

        $results = [];
        $pageToken = null;
        $driveService = $this->getDriveService();

        do {
            $optParams = [
                'q' => $query,
                'fields' => 'files(id, name, size, createdTime)',
                'orderBy' => 'createdTime desc',
                'pageSize' => 100
            ];
            if ($pageToken) $optParams['pageToken'] = $pageToken;

            $response = $driveService->files->listFiles($optParams);
            foreach ($response->getFiles() as $file) {
                $results[] = new BackupFileInfo(
                    driveFileId: $file->id,
                    fileName: $file->name,
                    fileSize: (int)($file->size ?? 0),
                    createdTime: $file->createdTime
                );
            }
            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $results;
    }

    public function downloadFile(string $fileId, string $destPath): void
    {
        $driveService = $this->getDriveService();
        $content = $driveService->files->get($fileId, ['alt' => 'media']);
        $body = $content->getBody()->getContents();
        if (empty($body)) {
            throw new \RuntimeException("Downloaded file is empty");
        }
        file_put_contents($destPath, $body);
    }

    public function deleteFile(string $fileId): void
    {
        $this->getDriveService()->files->delete($fileId);
    }

    public function enforceRetention(?string $folderId, int $keepDaily, int $keepWeekly, int $keepMonthly): void
    {
        $files = $this->listBackupFiles($folderId);
        if (count($files) <= $keepDaily) return;

        $toKeep = $this->selectFilesForRetention($files, $keepDaily, $keepWeekly, $keepMonthly);
        $keepIds = array_flip(array_map(fn($f) => $f->driveFileId, $toKeep));

        foreach ($files as $file) {
            if (!isset($keepIds[$file->driveFileId])) {
                try {
                    $this->deleteFile($file->driveFileId);
                } catch (\Exception $e) {
                    error_log("Failed to delete old backup {$file->fileName}: " . $e->getMessage());
                }
            }
        }
    }

    private function selectFilesForRetention(array $files, int $keepDaily, int $keepWeekly, int $keepMonthly): array
    {
        usort($files, fn($a, $b) => strtotime($b->createdTime) - strtotime($a->createdTime));

        $selected = [];
        $seenDays = [];
        $seenWeeks = [];
        $seenMonths = [];
        $count = 0;

        foreach ($files as $file) {
            $date = date('Y-m-d', strtotime($file->createdTime));
            $week = date('o-W', strtotime($file->createdTime));
            $month = date('Y-m', strtotime($file->createdTime));

            if ($count < $keepDaily && !isset($seenDays[$date])) {
                $selected[] = $file;
                $seenDays[$date] = true;
                $count++;
                continue;
            }
            if (count($selected) < $keepDaily + $keepWeekly && !isset($seenWeeks[$week])) {
                $selected[] = $file;
                $seenWeeks[$week] = true;
                $count++;
                continue;
            }
            if (count($selected) < $keepDaily + $keepWeekly + $keepMonthly && !isset($seenMonths[$month])) {
                $selected[] = $file;
                $seenMonths[$month] = true;
                $count++;
                continue;
            }
        }

        return $selected;
    }
}
