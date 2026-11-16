<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Setting
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => $key]);
            if ($row) {
                self::$cache[$key] = $row['setting_value'];
                return $row['setting_value'];
            }
        } catch (\Throwable) {}

        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$cache[$key] = (string) $value;
        $existing = Database::fetch("SELECT id FROM settings WHERE setting_key = :k", ['k' => $key]);
        if ($existing) {
            Database::update('settings', ['setting_value' => (string) $value], 'setting_key = :k', ['k' => $key]);
        } else {
            Database::insert('settings', ['setting_key' => $key, 'setting_value' => (string) $value]);
        }
    }

    public static function getAll(): array
    {
        $rows = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
            self::$cache[$r['setting_key']] = $r['setting_value'];
        }
        return $settings;
    }
}
