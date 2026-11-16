<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        // If relative URL, prepend app url
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $appUrl = (require dirname(__DIR__, 2) . '/config/app.php')['url'];
            $url = rtrim($appUrl, '/') . '/' . ltrim($url, '/');
        }

        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    public static function download(string $filePath, string $downloadFilename, string $mimeType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "File not found.";
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($downloadFilename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}
