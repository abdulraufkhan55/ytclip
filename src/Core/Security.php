<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Security
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;
    private const IV_LENGTH = 12;

    public static function getEncryptionKey(): string
    {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $keyHex = $appConfig['key'] ?? '';

        if (empty($keyHex)) {
            // Check settings table
            try {
                $setting = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'encryption_key'");
                if (!empty($setting['setting_value'])) {
                    $keyHex = $setting['setting_value'];
                }
            } catch (\Throwable) {}
        }

        if (empty($keyHex)) {
            // Check key file in storage/keys/secret.key
            $keyFile = dirname(__DIR__, 2) . '/storage/keys/secret.key';
            if (file_exists($keyFile)) {
                $keyHex = trim((string) file_get_contents($keyFile));
            }
        }

        if (empty($keyHex)) {
            // Generate and save automatically
            $keyHex = bin2hex(random_bytes(32));
            $keyDir = dirname(__DIR__, 2) . '/storage/keys';
            if (!is_dir($keyDir)) {
                mkdir($keyDir, 0700, true);
            }
            file_put_contents($keyDir . '/secret.key', $keyHex);
        }

        return hex2bin($keyHex);
    }

    /**
     * Encrypt a string using AES-256-GCM authenticated encryption.
     * Returns base64 encoded string: iv + tag + ciphertext.
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::getEncryptionKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a base64 encoded AES-256-GCM string.
     */
    public static function decrypt(string $payload): ?string
    {
        if ($payload === '') {
            return '';
        }

        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) < (self::IV_LENGTH + self::TAG_LENGTH)) {
            return null;
        }

        $key = self::getEncryptionKey();
        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext !== false ? $plaintext : null;
    }

    public static function generateCsrfToken(): string
    {
        Session::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        Session::start();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . self::e($token) . '">';
    }

    public static function e(?string $string): string
    {
        return htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizeFilename(string $filename): string
    {
        // Strip path traversal characters
        $filename = basename($filename);
        $clean = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        return $clean ?: 'video_' . time();
    }

    public static function generateSafeFilename(string $extension): string
    {
        $ext = strtolower(ltrim($extension, '.'));
        // Allowed safe video extension
        if (!in_array($ext, ['mp4', 'mov', 'webm'])) {
            $ext = 'mp4';
        }
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    }
}
