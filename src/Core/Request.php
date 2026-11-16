<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private ?array $json = null;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
    }

    public function getMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && isset($this->post['_method'])) {
            return strtoupper((string) $this->post['_method']);
        }
        return strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    public function isAjax(): bool
    {
        return (!empty($this->server['HTTP_X_REQUESTED_WITH']) &&
                strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (isset($this->server['HTTP_ACCEPT']) && str_contains($this->server['HTTP_ACCEPT'], 'application/json'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json());
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $this->json = is_array($decoded) ? $decoded : [];
        }

        if ($key === null) {
            return $this->json;
        }

        return $this->json[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip configured app URL path (e.g. /ytclip) if present
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $appPath = parse_url($appConfig['url'] ?? '', PHP_URL_PATH);
        if ($appPath && $appPath !== '/' && str_starts_with($path, $appPath)) {
            $path = substr($path, strlen($appPath));
        }

        // Also check dirname of SCRIPT_NAME as fallback
        $scriptName = $this->server['SCRIPT_NAME'] ?? '';
        $baseDir = str_replace('\\', '/', dirname($scriptName));
        if ($baseDir !== '/' && $baseDir !== '.' && str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        }

        // Strip /public or /index.php if remaining
        if (str_starts_with($path, '/public')) {
            $path = substr($path, 7);
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, 10);
        }

        // Support ?page= routing parameter as fallback
        if (($path === '' || $path === '/') && isset($this->get['page'])) {
            $page = trim((string) $this->get['page'], '/');
            return '/' . $page;
        }

        $cleanPath = '/' . trim($path, '/');
        return $cleanPath === '//' ? '/' : $cleanPath;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
