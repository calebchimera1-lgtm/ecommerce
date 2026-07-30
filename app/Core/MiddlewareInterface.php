<?php

declare(strict_types=1);

namespace App\Core;

interface MiddlewareInterface
{
    /**
     * Return true to allow the request to continue to the controller,
     * or handle the response itself (redirect/abort) and return false.
     */
    public function handle(Request $request): bool;
}
