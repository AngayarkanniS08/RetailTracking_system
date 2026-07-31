<?php

namespace Modules\Notification\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Modules\Notification\Cache\NotificationCacheService;
use Modules\Notification\Service\NotificationService;

/**
 * NotificationController — the single, reusable notification contract.
 *
 *   GET  /api/notifications                 → { summary, alerts } (cached 60s)
 *   POST /api/notifications/read            → { success, marked }  body: { keys: [...] }
 *   POST /api/notifications/read-all        → { success, marked }
 *
 * The frontend never computes counts or read state; it renders whatever this
 * controller returns.
 */
class NotificationController
{
    private NotificationService $service;
    private NotificationCacheService $cache;

    public function __construct(?NotificationService $service = null, ?NotificationCacheService $cache = null)
    {
        $this->service = $service ?? new NotificationService();
        $this->cache = $cache ?? new NotificationCacheService();
    }

    public function index(): void
    {
        header('Content-Type: application/json');
        $user = AuthMiddleware::authenticate();
        $userId = (string) ($user->data->user_id ?? '');

        $payload = $this->cache->getPayload($userId);
        if ($payload === null) {
            $payload = $this->service->buildPayload($userId);
            $this->cache->setPayload($userId, $payload);
        }

        echo json_encode([
            'summary' => $payload['summary'] ?? [],
            'alerts'  => $payload['alerts'] ?? [],
        ]);
    }

    public function markRead(): void
    {
        header('Content-Type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $user = AuthMiddleware::authenticate();
        $userId = (string) ($user->data->user_id ?? '');

        $input = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $keys = $input['keys'] ?? [];

        try {
            $marked = $this->service->markRead($userId, is_array($keys) ? $keys : []);
            $this->cache->invalidateUser($userId);
            echo json_encode(['success' => true, 'marked' => $marked]);
        } catch (\Throwable $e) {
            error_log('NotificationController::markRead - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark notifications as read']);
        }
    }

    public function markAllRead(): void
    {
        header('Content-Type: application/json');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $user = AuthMiddleware::authenticate();
        $userId = (string) ($user->data->user_id ?? '');

        try {
            $marked = $this->service->markAllRead($userId);
            $this->cache->invalidateUser($userId);
            echo json_encode(['success' => true, 'marked' => $marked]);
        } catch (\Throwable $e) {
            error_log('NotificationController::markAllRead - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark notifications as read']);
        }
    }
}
