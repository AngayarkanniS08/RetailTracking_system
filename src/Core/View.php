<?php
declare(strict_types=1);

namespace Core;

class View
{
    private static string $viewsPath = __DIR__ . '/../../views/';

    /**
     * Render a page view within the shared master layout.
     *
     * @param string $viewPath - e.g. 'dashboard/index' or 'billing/index'
     * @param array $data      - data array passed to view
     * @param string|null $layout - layout wrapper filename or null for standalone
     */
    public static function render(string $viewPath, array $data = [], ?string $layout = 'layouts/layout'): void
    {
        extract($data);
        $contentFile = self::$viewsPath . $viewPath . '.php';

        if (!file_exists($contentFile)) {
            throw new \Exception("View file [{$viewPath}] not found at {$contentFile}");
        }

        if ($layout !== null) {
            $layoutFile = self::$viewsPath . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new \Exception("Layout file [{$layout}] not found at {$layoutFile}");
            }

            // Buffer content view
            ob_start();
            require $contentFile;
            $pageContent = ob_get_clean();

            // Render within master layout
            require $layoutFile;
        } else {
            require $contentFile;
        }
    }
}
