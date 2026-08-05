<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Vendor;

/**
 * Placeholder landing page, the vendor-side counterpart to Module 3's
 * original admin dashboard placeholder - real content (product
 * management, sales, payouts) arrives in Modules 16-18.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        $this->view('vendor/dashboard/index', [
            'pageTitle' => 'Vendor Dashboard | Kymera Collection',
            'vendor' => $vendor,
        ], 'vendor/layouts/app');
    }
}
