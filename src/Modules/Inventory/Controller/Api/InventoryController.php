<?php
declare(strict_types=1);

namespace Modules\Inventory\Controller\Api;

use Core\Middlewares\AuthMiddleware;
use Modules\Auth\Validation\ValidationException;
use Modules\Inventory\DTO\InventoryCreateDTO;
use Modules\Inventory\DTO\InventoryUpdateDTO;
use Modules\Inventory\DTO\InventoryRestockDTO;
use Modules\Inventory\Service\InventoryService;
use Modules\Inventory\Validator\InventoryValidator;

/**
 * InventoryController — thin HTTP adapter for the Inventory module.
 *
 * Responsibilities are limited to:
 *   1. authenticate the caller
 *   2. validate the incoming DTO
 *   3. delegate to InventoryService
 *   4. serialise the response
 *
 * No calculations, no SQL, no cache logic, no notification logic live here.
 *
 * API contract (reusable by web/mobile/CLI):
 *   GET  /api/inventory                     → list (page/limit/search/category/subcategory)
 *   GET  /api/inventory/{id}                → details + value + restock recommendation
 *   POST /api/inventory                     → create batch
 *   PUT  /api/inventory/{id}                → edit batch (optimistic concurrency)
 *   POST /api/inventory/{id}/restock        → additive restock { add_quantity }
 */
final class InventoryController
{
    private InventoryService $service;

    public function __construct(?InventoryService $service = null)
    {
        $this->service = $service ?? new InventoryService();
    }

    public function index(): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            $userId = (string)($user->data->user_id ?? '');

            $query = InventoryValidator::validateListQuery($_GET);
            $result = $this->service->listInventory(
                page: $query['page'],
                limit: $query['limit'],
                search: $query['search'],
                categoryId: $query['category_id'],
                subcategoryId: $query['subcategory_id'],
                userId: $userId,
            );

            self::json([
                'success'    => true,
                'data'       => $result['data'],
                'pagination' => $result['pagination'],
                'summary'    => $result['summary'],
                'filters'    => $result['filters'],
            ]);
        } catch (ValidationException $e) {
            self::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            self::log($e);
            self::json(['error' => 'Failed to load inventory'], 500);
        }
    }

    public function show(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            $userId = (string)($user->data->user_id ?? '');

            $data = $this->service->getDetails($id, $userId);
            self::json(['success' => true, 'data' => $data]);
        } catch (ValidationException $e) {
            self::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            self::log($e);
            self::json(['error' => 'Failed to load inventory batch'], 500);
        }
    }

    public function store(): void
    {
        try {
            self::assertMethod('POST');
            $user = AuthMiddleware::authenticate();
            $userId = (string)($user->data->user_id ?? '');

            $dto = InventoryValidator::validateCreate(self::body());
            $batch = $this->service->createBatch($dto, $userId);

            self::json(['success' => true, 'data' => $batch], 201);
        } catch (ValidationException $e) {
            self::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            self::log($e);
            self::json(['error' => 'Failed to create stock batch'], 500);
        }
    }

    public function update(string $id): void
    {
        try {
            self::assertMethod('PUT');
            $user = AuthMiddleware::authenticate();
            $userId = (string)($user->data->user_id ?? '');

            $dto = InventoryValidator::validateUpdate($id, self::body());
            $batch = $this->service->updateBatch($dto, $userId);

            self::json(['success' => true, 'data' => $batch]);
        } catch (ValidationException $e) {
            self::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            self::log($e);
            self::json(['error' => 'Failed to update stock batch'], 500);
        }
    }

    public function restock(string $id): void
    {
        try {
            self::assertMethod('POST');
            $user = AuthMiddleware::authenticate();
            $userId = (string)($user->data->user_id ?? '');

            $dto = InventoryValidator::validateRestock($id, self::body());
            $result = $this->service->restockBatch($dto, $userId);

            self::json(['success' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            self::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            self::log($e);
            self::json(['error' => 'Failed to restock batch'], 500);
        }
    }

    // ── helpers ─────────────────────────────────────────────────

    private static function assertMethod(string $expected): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== $expected) {
            throw new ValidationException('Method not allowed');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(): array
    {
        $raw = json_decode((string)file_get_contents('php://input'), true);
        return is_array($raw) ? $raw : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private static function log(\Throwable $e): void
    {
        error_log(sprintf('InventoryController - %s: %s', get_class($e), $e->getMessage()));
    }
}
