<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Logger;

abstract class AbstractPlatformAdapter implements PlatformAdapterInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Executes HTTP requests with timeout, authorization headers, and error handling
     */
    protected function makeHttpRequest(string $url, string $method = 'GET', array $headers = [], mixed $body = null, int $timeout = 60): array
    {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = is_numeric($k) ? $v : "{$k}: {$v}";
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
            }
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            Logger::error("HTTP request failed", ['url' => $url, 'error' => $curlError]);
            return [
                'success' => false,
                'http_status' => $httpCode ?: 0,
                'error_code' => 'CURL_ERROR',
                'error_message' => $curlError ?: 'Connection failed or timed out',
                'response' => null
            ];
        }

        $decoded = json_decode($rawResponse, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_status' => $httpCode,
            'error_code' => $httpCode >= 400 ? "HTTP_{$httpCode}" : null,
            'error_message' => $httpCode >= 400 ? ($decoded['error']['message'] ?? $decoded['message'] ?? "HTTP error {$httpCode}") : null,
            'response' => $decoded ?? $rawResponse,
            'raw' => $rawResponse
        ];
    }
}
