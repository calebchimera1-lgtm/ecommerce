<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

/**
 * Read-only settings view for now (full editing form is a later
 * module). Gated behind the 'settings.manage' permission - doubles as
 * a live demonstration that PermissionMiddleware enforces granular
 * per-permission access, not just "is staff": Super Admin can reach
 * this page, Manager and Support cannot (see seed data in Module 1).
 */
final class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $stmt = Database::connection()->query('SELECT setting_key, value, `group` FROM settings ORDER BY `group`, setting_key');
        $settings = $stmt->fetchAll();

        $this->view('admin/settings/index', [
            'pageTitle' => 'Settings | Kymera Collection Admin',
            'settings' => $settings,
        ], 'admin/layouts/app');
    }
}
