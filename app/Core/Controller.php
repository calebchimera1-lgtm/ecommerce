<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller providing view rendering, JSON responses, redirects,
 * and simple input validation available to every controller.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = null): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            Response::abort(500, "View not found: {$view}");
        }

        if ($layout !== null) {
            $layoutFile = dirname(__DIR__) . '/Views/' . $layout . '.php';
            $content = function () use ($viewFile, $data): void {
                extract($data, EXTR_SKIP);
                require $viewFile;
            };
            require $layoutFile;
            return;
        }

        require $viewFile;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): never
    {
        Response::redirect($url, $status);
    }

    protected function back(): never
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function validate(array $data, array $rules): array
    {
        $validator = new Validator($data, $rules);

        if (!$validator->passes()) {
            Session::flash('errors', $validator->errors());
            Session::flash('old', $data);
            $this->back();
        }

        return $validator->validated();
    }

    protected function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
