<?php
declare(strict_types=1);

namespace Database\Seeders\{{MODULE}};

use Database\Seeders\BaseSeeder;

/**
 * Seeder for {{MODULE}} module.
 */
class {{NAME}}Seeder extends BaseSeeder
{
    public function module(): string
    {
        return '{{MODULE}}';
    }

    public function environments(): array
    {
        return ['development', 'testing'];
    }

    protected function seed(): void
    {
        // TODO: Insert seed data using INSERT ... ON CONFLICT DO NOTHING
    }
}
