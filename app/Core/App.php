<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application bootstrap: loads config, sets error handling, timezone,
 * starts the secure session, builds the router from the route files,
 * and dispatches the current request.
 */
final class App
{
    private array $config;

    public function __construct(private readonly string $basePath)
    {
        $this->config = require $this->basePath . '/config/config.php';
    }

    public function run(): void
    {
        $this->configureErrorHandling();
        date_default_timezone_set($this->config['app']['timezone']);
        Session::start();
        Auth::attemptResumeFromCookie();
        Response::securityHeaders();

        $router = new Router();

        foreach (['/routes/web.php', '/routes/admin.php', '/routes/vendor.php'] as $routeFile) {
            $registrar = require $this->basePath . $routeFile;
            $registrar($router);
        }

        $router->dispatch(new Request());
    }

    private function configureErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->config['app']['debug'] ? '1' : '0');
        ini_set('log_errors', '1');

        set_exception_handler(function (\Throwable $e): void {
            Logger::critical($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->config['app']['debug']) {
                http_response_code(500);
                echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
                return;
            }

            Response::abort(500, 'Something went wrong. Please try again later.');
        });
    }
}
