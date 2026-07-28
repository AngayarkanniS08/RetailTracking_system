<?php
declare(strict_types=1);

namespace Core;

class ExceptionHandler
{
    public static function handle(\Throwable $e): void
    {
        Logger::error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        $status = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($status);

        if ($status === 404) {
            View::render('errors/404', ['title' => '404 - Page Not Found', 'currentRoute' => '']);
            exit;
        }

        View::render('errors/500', ['title' => '500 - Server Error', 'message' => $e->getMessage(), 'currentRoute' => '']);
        exit;
    }
}
