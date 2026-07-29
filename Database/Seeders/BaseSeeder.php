<?php
declare(strict_types=1);

namespace Database\Seeders;

use PDO;

/**
 * Abstract base for all module seeders.
 *
 * All seeders use INSERT ... ON CONFLICT DO NOTHING by default (idempotent).
 * Seeders that require truncation must explicitly override $allowTruncate
 * and check the environment before calling truncate().
 *
 * Lifecycle:
 *   SeederRunner → run() → beforeSeed() → seed() → afterSeed()
 */
abstract class BaseSeeder
{
    protected PDO $pdo;

    /** Override to true only in development/testing seeders that need a clean slate */
    protected bool $allowTruncate = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Module this seeder belongs to.
     */
    abstract public function module(): string;

    /**
     * Environments this seeder is allowed to run in.
     * Override to restrict to specific environments.
     *
     * @return string[]  e.g. ['development', 'testing']
     */
    public function environments(): array
    {
        return ['development', 'testing'];
    }

    /**
     * Implement seed logic here. Use INSERT ... ON CONFLICT DO NOTHING.
     */
    abstract protected function seed(): void;

    /**
     * Called by SeederRunner. Checks environment and runs the seeder.
     *
     * @throws \RuntimeException if run in an unsupported environment
     */
    public function run(): void
    {
        $env = strtolower(getenv('APP_ENV') ?: 'development');

        if (!in_array($env, $this->environments(), true)) {
            throw new \RuntimeException(
                sprintf(
                    "Seeder '%s' is not allowed in APP_ENV='%s'. Allowed: %s",
                    static::class,
                    $env,
                    implode(', ', $this->environments())
                )
            );
        }

        $this->beforeSeed();
        $this->seed();
        $this->afterSeed();
    }

    // -------------------------------------------------------------------------
    // Hooks (override as needed)
    // -------------------------------------------------------------------------

    protected function beforeSeed(): void {}

    protected function afterSeed(): void {}

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Truncate tables — only callable in development/testing environments.
     *
     * @param string[] $tables
     */
    protected function truncate(array $tables): void
    {
        $env = strtolower(getenv('APP_ENV') ?: 'development');

        if (!in_array($env, ['development', 'testing', 'local'], true)) {
            throw new \RuntimeException(
                "truncate() is not allowed in environment: {$env}"
            );
        }

        foreach ($tables as $table) {
            $this->pdo->exec("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
        }
    }
}
