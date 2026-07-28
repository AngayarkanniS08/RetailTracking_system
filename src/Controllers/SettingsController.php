<?php
declare(strict_types=1);

namespace Controllers;

use Core\Request;
use Core\View;

class SettingsController
{
    public function backup(Request $request): void
    {
        View::render('settings/backup', [
            'pageTitle'    => 'Database Backup & Restore',
            'currentRoute' => '/settings/backup',
        ]);
    }
}
