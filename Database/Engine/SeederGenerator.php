<?php
declare(strict_types=1);

namespace Database\Engine;

/**
 * Scaffolds seeder class skeletons for modules.
 *
 * Generates:
 *   Database/Seeders/{Module}/{Name}Seeder.php
 */
class SeederGenerator
{
    private const SEEDERS_BASE = __DIR__ . '/../Seeders';

    private const KNOWN_MODULES = [
        'Auth', 'Product', 'Customer', 'Billing',
        'Vendor', 'Security', 'Settings', 'Dashboard',
    ];

    /**
     * Generate a seeder class skeleton.
     *
     * @param string $module  Module name (e.g. Auth)
     * @param string $name    Seeder name (e.g. Roles) -> RolesSeeder
     * @return string         Absolute path to generated seeder file
     */
    public function generate(string $module, string $name): string
    {
        $this->validateModule($module);

        $className = ucfirst($name) . 'Seeder';
        $dir       = self::SEEDERS_BASE . "/{$module}";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = "{$dir}/{$className}.php";

        if (file_exists($filePath)) {
            throw new \RuntimeException("Seeder file already exists: {$filePath}");
        }

        $code = <<<PHP
        <?php
        declare(strict_types=1);

        namespace Database\\Seeders\\{$module};

        use Database\\Seeders\\BaseSeeder;

        /**
         * Seeder for {$module} module.
         */
        class {$className} extends BaseSeeder
        {
            public function module(): string
            {
                return '{$module}';
            }

            public function environments(): array
            {
                return ['development', 'testing'];
            }

            protected function seed(): void
            {
                // TODO: Add idempotent insert logic using ON CONFLICT DO NOTHING
            }
        }
        PHP;

        file_put_contents($filePath, $code);

        MigrationLogger::info("Generated seeder: {$className}", [
            'module' => $module,
            'file'   => $filePath,
        ]);

        return $filePath;
    }

    private function validateModule(string $module): void
    {
        if (!in_array($module, self::KNOWN_MODULES, true)) {
            throw new \InvalidArgumentException(
                "Unknown module: '{$module}'\n" .
                "Known modules: " . implode(', ', self::KNOWN_MODULES)
            );
        }
    }
}
