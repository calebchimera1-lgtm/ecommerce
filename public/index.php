<?php

/**
 * Front controller. Every request is routed through this single entry
 * point (see public/.htaccess), which is the only PHP file exposed
 * directly to the web server.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\App;

$app = new App(dirname(__DIR__));
$app->run();
