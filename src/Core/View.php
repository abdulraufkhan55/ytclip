<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'main'): void
    {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $user = Auth::user();

        // Extract variables
        $data['app'] = $appConfig;
        $data['currentUser'] = $user;
        $data['csrf_token'] = Security::generateCsrfToken();
        $data['csrf_field'] = Security::csrfField();
        $data['flashSuccess'] = Session::getFlash('success');
        $data['flashError'] = Session::getFlash('error');
        $data['flashInfo'] = Session::getFlash('info');

        extract($data);

        $viewFile = dirname(__DIR__, 2) . '/views/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo "View file not found: " . Security::e($template);
            exit;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutFile = dirname(__DIR__, 2) . '/views/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }
}
