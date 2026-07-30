<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('customer/home/index', [
            'pageTitle' => 'Kymera Collection - Luxury Redefined',
        ]);
    }

    public function health(Request $request): void
    {
        $this->json(['status' => 'ok', 'app' => 'Kymera Collection']);
    }
}
