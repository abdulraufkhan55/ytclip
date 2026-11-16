<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Video
{
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT v.*, u.name as owner_name FROM videos v LEFT JOIN users u ON v.owner_id = u.id WHERE v.id = :id", ['id' => $id]);
    }

    public static function getAll(array $filters = []): array
    {
        $sql = "SELECT v.*, u.name as owner_name, 
                (SELECT COUNT(*) FROM publishing_jobs pj WHERE pj.video_id = v.id) as total_jobs,
                (SELECT COUNT(*) FROM publishing_jobs pj WHERE pj.video_id = v.id AND pj.status = 'published') as published_jobs,
                (SELECT COUNT(*) FROM publishing_jobs pj WHERE pj.video_id = v.id AND pj.status = 'failed') as failed_jobs
                FROM videos v 
                LEFT JOIN users u ON v.owner_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND v.video_status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (v.title LIKE :search OR v.description LIKE :search OR v.original_filename LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY v.created_at DESC";

        return Database::fetchAll($sql, $params);
    }

    public static function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::insert('videos', $data);
    }

    public static function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('videos', $data, 'id = :id', ['id' => $id]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::update('videos', [
            'video_status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);
    }

    public static function archive(int $id): void
    {
        self::updateStatus($id, 'archived');
    }

    public static function delete(int $id): bool
    {
        $video = self::findById($id);
        if ($video && file_exists($video['storage_path'])) {
            @unlink($video['storage_path']);
        }
        if ($video && !empty($video['thumbnail_path']) && file_exists($video['thumbnail_path'])) {
            @unlink($video['thumbnail_path']);
        }

        $stmt = Database::query("DELETE FROM videos WHERE id = :id", ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function getStats(): array
    {
        $total = Database::fetch("SELECT COUNT(*) as cnt FROM videos");
        $ready = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'ready'");
        $published = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'published'");
        $partially = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'partially_published'");
        $failed = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'failed'");
        $draft = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'draft'");
        $archived = Database::fetch("SELECT COUNT(*) as cnt FROM videos WHERE video_status = 'archived'");

        return [
            'total' => (int) ($total['cnt'] ?? 0),
            'ready' => (int) ($ready['cnt'] ?? 0),
            'published' => (int) ($published['cnt'] ?? 0),
            'partially_published' => (int) ($partially['cnt'] ?? 0),
            'failed' => (int) ($failed['cnt'] ?? 0),
            'draft' => (int) ($draft['cnt'] ?? 0),
            'archived' => (int) ($archived['cnt'] ?? 0),
        ];
    }
}
