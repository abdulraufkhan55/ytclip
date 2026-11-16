<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;

class SocialAccount
{
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM social_accounts WHERE id = :id", ['id' => $id]);
    }

    public static function findByPlatformAndAccount(string $platform, string $accountId): ?array
    {
        return Database::fetch(
            "SELECT * FROM social_accounts WHERE platform = :p AND account_id = :aid",
            ['p' => $platform, 'aid' => $accountId]
        );
    }

    public static function getAll(): array
    {
        $accounts = Database::fetchAll("SELECT * FROM social_accounts ORDER BY platform ASC, created_at DESC");
        // Strip sensitive encrypted tokens before returning to views/controllers
        foreach ($accounts as &$acc) {
            unset($acc['encrypted_access_token'], $acc['encrypted_refresh_token']);
        }
        return $accounts;
    }

    public static function getConnectedByPlatform(string $platform): array
    {
        return Database::fetchAll(
            "SELECT id, user_id, platform, account_id, account_name, connection_status, last_verified_at, token_expires_at, metadata 
             FROM social_accounts 
             WHERE platform = :p AND connection_status = 'connected' 
             ORDER BY account_name ASC",
            ['p' => $platform]
        );
    }

    public static function getConnectedGrouped(): array
    {
        $all = self::getAll();
        $grouped = ['youtube' => [], 'tiktok' => [], 'instagram' => []];
        foreach ($all as $acc) {
            if (isset($grouped[$acc['platform']])) {
                $grouped[$acc['platform']][] = $acc;
            }
        }
        return $grouped;
    }

    public static function getDecryptedTokens(int $id): ?array
    {
        $acc = Database::fetch("SELECT * FROM social_accounts WHERE id = :id", ['id' => $id]);
        if (!$acc) {
            return null;
        }

        return [
            'access_token' => Security::decrypt($acc['encrypted_access_token']),
            'refresh_token' => !empty($acc['encrypted_refresh_token']) ? Security::decrypt($acc['encrypted_refresh_token']) : null,
            'token_expires_at' => $acc['token_expires_at'],
            'granted_scopes' => $acc['granted_scopes'],
            'metadata' => !empty($acc['metadata']) ? json_decode($acc['metadata'], true) : []
        ];
    }

    public static function createOrUpdate(array $data): int
    {
        // Encrypt tokens before storing
        if (!empty($data['access_token'])) {
            $data['encrypted_access_token'] = Security::encrypt($data['access_token']);
            unset($data['access_token']);
        }

        if (isset($data['refresh_token'])) {
            $data['encrypted_refresh_token'] = !empty($data['refresh_token']) ? Security::encrypt($data['refresh_token']) : null;
            unset($data['refresh_token']);
        }

        $existing = self::findByPlatformAndAccount($data['platform'], $data['account_id']);
        if ($existing) {
            $id = (int) $existing['id'];
            $data['updated_at'] = date('Y-m-d H:i:s');
            Database::update('social_accounts', $data, 'id = :id', ['id' => $id]);
            return $id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            return Database::insert('social_accounts', $data);
        }
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::update('social_accounts', [
            'connection_status' => $status,
            'last_verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);
    }

    public static function updateTokens(int $id, string $accessToken, ?string $refreshToken = null, ?string $expiresAt = null): void
    {
        $update = [
            'encrypted_access_token' => Security::encrypt($accessToken),
            'connection_status' => 'connected',
            'last_verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if ($refreshToken !== null) {
            $update['encrypted_refresh_token'] = Security::encrypt($refreshToken);
        }
        if ($expiresAt !== null) {
            $update['token_expires_at'] = $expiresAt;
        }

        Database::update('social_accounts', $update, 'id = :id', ['id' => $id]);
    }

    public static function disconnect(int $id): bool
    {
        // Safely remove any associated publishing jobs and attempts
        $jobs = Database::fetchAll("SELECT id FROM publishing_jobs WHERE social_account_id = :id", ['id' => $id]);
        foreach ($jobs as $job) {
            Database::query("DELETE FROM publishing_attempts WHERE publishing_job_id = :jid", ['jid' => $job['id']]);
        }
        Database::query("DELETE FROM publishing_jobs WHERE social_account_id = :id", ['id' => $id]);
        $stmt = Database::query("DELETE FROM social_accounts WHERE id = :id", ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function countConnected(): int
    {
        $res = Database::fetch("SELECT COUNT(*) as cnt FROM social_accounts WHERE connection_status = 'connected'");
        return (int) ($res['cnt'] ?? 0);
    }
}
