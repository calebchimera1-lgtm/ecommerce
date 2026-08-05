<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;

final class AuditLogController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));

        $this->view('admin/audit-logs/index', [
            'pageTitle' => 'Audit Log | Kymera Collection Admin',
            'logs' => AuditLog::paginateAll($page, self::PER_PAGE),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => AuditLog::count(),
        ], 'admin/layouts/app');
    }
}
