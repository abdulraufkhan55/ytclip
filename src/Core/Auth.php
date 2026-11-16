<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public static function user(): ?array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        try {
            return Database::fetch('SELECT id, name, email, role, status FROM users WHERE id = :id AND status = "active"', ['id' => $userId]);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user ? (string) $user['role'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isOperator(): bool
    {
        return in_array(self::role(), ['admin', 'operator'], true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Session::setFlash('error', 'You must log in to access this page.');
            $appUrl = (require dirname(__DIR__, 2) . '/config/app.php')['url'];
            header("Location: {$appUrl}/login");
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            Session::setFlash('error', 'Access denied. Administrator privileges are required.');
            $appUrl = (require dirname(__DIR__, 2) . '/config/app.php')['url'];
            header("Location: {$appUrl}/dashboard");
            exit;
        }
    }

    public static function isRateLimited(string $ip, string $email): bool
    {
        $timeWindow = date('Y-m-d H:i:s', strtotime('-' . self::LOCKOUT_MINUTES . ' minutes'));
        $count = Database::fetch(
            "SELECT COUNT(*) as cnt FROM login_attempts WHERE (ip_address = :ip OR email = :email) AND success = 0 AND attempted_at >= :window",
            ['ip' => $ip, 'email' => $email, 'window' => $timeWindow]
        );

        return (int) ($count['cnt'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    public static function recordAttempt(string $ip, string $email, bool $success): void
    {
        Database::insert('login_attempts', [
            'ip_address' => $ip,
            'email' => $email,
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    }

    public static function attempt(string $email, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (self::isRateLimited($ip, $email)) {
            Logger::warning('Login attempt blocked by rate limit', ['email' => $email, 'ip' => $ip]);
            return [
                'success' => false,
                'message' => 'Too many failed login attempts. Please try again after ' . self::LOCKOUT_MINUTES . ' minutes.'
            ];
        }

        $user = Database::fetch('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordAttempt($ip, $email, false);
            Logger::warning('Failed login attempt', ['email' => $email, 'ip' => $ip]);
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'Your account is ' . $user['status'] . '. Contact administrator.'
            ];
        }

        // Login successful
        self::recordAttempt($ip, $email, true);
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);

        Logger::audit('login', 'user', (string) $user['id'], ['email' => $user['email']], (int) $user['id']);
        Logger::info('User logged in successfully', ['user_id' => $user['id'], 'email' => $email]);

        return [
            'success' => true,
            'user' => $user
        ];
    }

    public static function logout(): void
    {
        $userId = self::id();
        if ($userId) {
            Logger::audit('logout', 'user', (string) $userId, [], $userId);
        }
        Session::destroy();
    }
}
