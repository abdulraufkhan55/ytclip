<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, array|callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => '/' . trim($path, '/'),
            'handler' => $handler
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Enforce CSRF protection on POST requests
        if ($method === 'POST') {
            $token = $request->post('csrf_token') ?? ($request->json('csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
            if (!Security::validateCsrfToken($token)) {
                Logger::warning('CSRF validation failed for request', ['path' => $path, 'ip' => $request->ip()]);
                if ($request->isAjax()) {
                    Response::json(['success' => false, 'message' => 'CSRF token mismatch or expired. Please refresh the page.'], 419);
                } else {
                    Session::setFlash('error', 'Security token mismatch. Please try submitting the form again.');
                    Response::redirect($path);
                }
                return;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], [$request, $params]);
                    return;
                }

                if (is_array($route['handler']) && count($route['handler']) === 2) {
                    [$controllerClass, $action] = $route['handler'];
                    if (class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        if (method_exists($controller, $action)) {
                            $controller->$action($request, $params);
                            return;
                        }
                    }
                }
            }
        }

        // 404 Not Found
        http_response_code(404);
        if ($request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Endpoint not found.'], 404);
        } else {
            View::render('dashboard.404', ['title' => 'Page Not Found', 'path' => $path], Auth::check() ? 'main' : 'auth');
        }
    }
}
