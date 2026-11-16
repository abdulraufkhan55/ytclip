<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Security;
use App\Models\Setting;
use RuntimeException;

class VideoProcessor
{
    private string $storageDir;
    private string $thumbnailDir;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage/uploads';
        $this->thumbnailDir = dirname(__DIR__, 2) . '/storage/thumbnails';

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }

    /**
     * Scans and syncs videos dropped manually into the 'Upload videos man' folder
     */
    public function syncManualFolder(int $ownerId = 1): array
    {
        $manualDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Upload videos man';
        if (!is_dir($manualDir)) {
            mkdir($manualDir, 0755, true);
            return [];
        }

        $files = scandir($manualDir);
        if ($files === false) {
            return [];
        }

        $imported = [];
        $allowedExtensions = ['mp4', 'mov', 'webm'];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $manualDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($sourcePath) || filesize($sourcePath) === 0) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $checksum = hash_file('sha256', $sourcePath);

            // Check if already imported by checksum
            $existing = \App\Core\Database::fetch("SELECT id FROM videos WHERE checksum = :cs", ['cs' => $checksum]);
            if ($existing) {
                continue;
            }

            // Inspect metadata (duration, width, height)
            $meta = $this->inspectVideo($sourcePath);

            // Copy to storage/uploads with safe filename
            $storedFilename = 'manual_' . Security::generateSafeFilename($ext);
            $targetPath = $this->storageDir . DIRECTORY_SEPARATOR . $storedFilename;
            copy($sourcePath, $targetPath);

            // Generate thumbnail
            $thumbFilename = pathinfo($storedFilename, PATHINFO_FILENAME) . '.jpg';
            $thumbPath = $this->thumbnailDir . DIRECTORY_SEPARATOR . $thumbFilename;
            $this->generateThumbnail($targetPath, $thumbPath);

            // Title from filename
            $cleanTitle = pathinfo($file, PATHINFO_FILENAME);
            $cleanTitle = ucwords(str_replace(['_', '-'], ' ', $cleanTitle));

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $targetPath) ?: 'video/mp4';
            finfo_close($finfo);

            $videoId = \App\Models\Video::create([
                'owner_id' => $ownerId,
                'original_filename' => $file,
                'stored_filename' => $storedFilename,
                'storage_path' => $targetPath,
                'thumbnail_path' => file_exists($thumbPath) ? $thumbPath : null,
                'mime_type' => $mimeType,
                'file_size' => filesize($targetPath),
                'duration_seconds' => $meta['duration'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'title' => $cleanTitle,
                'description' => "Imported from 'Upload videos man' manual directory.",
                'default_caption' => $cleanTitle . " #Shorts #Reels",
                'hashtags' => "#Shorts #Reels #Viral",
                'checksum' => $checksum,
                'video_status' => 'ready'
            ]);

            \App\Core\Logger::audit('manual_video_imported', 'video', (string) $videoId, ['file' => $file, 'title' => $cleanTitle], $ownerId);
            $imported[] = $cleanTitle;
        }

        return $imported;
    }

    /**
     * Process an uploaded file from $_FILES array
     */
    public function processUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->getUploadErrorMessage($file['error']));
        }

        // 1. Validate File Size
        $maxMb = (int) Setting::get('max_upload_size_mb', 200);
        $maxBytes = $maxMb * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException("File exceeds maximum allowed size of {$maxMb}MB.");
        }

        // 2. Validate Extension
        $originalName = Security::sanitizeFilename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['mp4', 'mov', 'webm'];
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new RuntimeException("Invalid file extension '.{$ext}'. Only MP4, MOV, and WebM videos are supported.");
        }

        // 3. Validate MIME Type with finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['video/mp4', 'video/quicktime', 'video/webm', 'application/mp4'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new RuntimeException("Invalid video MIME type '{$mimeType}'. Expected standard MP4/MOV video.");
        }

        // 4. Generate Safe Stored Filename
        $storedFilename = Security::generateSafeFilename($ext);
        $targetPath = $this->storageDir . DIRECTORY_SEPARATOR . $storedFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException("Failed to safely store the uploaded video.");
        }

        // 5. Inspect Video Metadata (FFprobe or PHP atom parser)
        $meta = $this->inspectVideo($targetPath);

        // 6. Validate Duration against configured limits (default 30-60s)
        $minDuration = (float) Setting::get('min_video_duration', 30);
        $maxDuration = (float) Setting::get('max_video_duration', 60);

        if ($meta['duration'] > 0) {
            if ($meta['duration'] < $minDuration || $meta['duration'] > $maxDuration) {
                // Delete invalid file
                @unlink($targetPath);
                throw new RuntimeException(sprintf(
                    "Video duration is %.1fs, but Shorts/Reels require duration between %.0fs and %.0fs.",
                    $meta['duration'],
                    $minDuration,
                    $maxDuration
                ));
            }
        }

        // 7. Calculate Checksum
        $checksum = hash_file('sha256', $targetPath);

        // 8. Generate Thumbnail if ffmpeg available
        $thumbFilename = pathinfo($storedFilename, PATHINFO_FILENAME) . '.jpg';
        $thumbPath = $this->thumbnailDir . DIRECTORY_SEPARATOR . $thumbFilename;
        $this->generateThumbnail($targetPath, $thumbPath);

        return [
            'original_filename' => $originalName,
            'stored_filename' => $storedFilename,
            'storage_path' => $targetPath,
            'thumbnail_path' => file_exists($thumbPath) ? $thumbPath : null,
            'mime_type' => $mimeType,
            'file_size' => filesize($targetPath),
            'duration_seconds' => $meta['duration'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            'checksum' => $checksum,
        ];
    }

    /**
     * Inspects video metadata using ffprobe if available, or pure PHP MP4 box parser
     */
    public function inspectVideo(string $filePath): array
    {
        $meta = [
            'duration' => 0.0,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'codec' => 'h264'
        ];

        // Check ffprobe
        $ffprobe = Setting::get('ffprobe_path', '');
        if (empty($ffprobe)) {
            $ffprobe = 'ffprobe';
        }

        $cmd = escapeshellcmd($ffprobe) . ' -v quiet -print_format json -show_format -show_streams ' . escapeshellarg($filePath);
        $output = @shell_exec($cmd);

        if ($output) {
            $data = json_decode($output, true);
            if (!empty($data['format']['duration'])) {
                $meta['duration'] = round((float) $data['format']['duration'], 2);
            }

            if (!empty($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    if (($stream['codec_type'] ?? '') === 'video') {
                        $meta['width'] = (int) ($stream['width'] ?? $meta['width']);
                        $meta['height'] = (int) ($stream['height'] ?? $meta['height']);
                        $meta['codec'] = $stream['codec_name'] ?? $meta['codec'];
                        break;
                    }
                }
            }
            return $meta;
        }

        // Fallback: Pure PHP MP4 'mvhd' atom duration parser
        $phpMeta = $this->parseMp4Metadata($filePath);
        if ($phpMeta['duration'] > 0) {
            $meta['duration'] = $phpMeta['duration'];
            $meta['width'] = $phpMeta['width'];
            $meta['height'] = $phpMeta['height'];
        }

        return $meta;
    }

    /**
     * Pure PHP parser for ISO Base Media File Format (MP4/MOV) boxes
     */
    private function parseMp4Metadata(string $filePath): array
    {
        $meta = ['duration' => 0.0, 'width' => 1080, 'height' => 1920];
        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return $meta;
        }

        $filesize = filesize($filePath);
        while (ftell($handle) < $filesize - 8) {
            $sizeData = fread($handle, 4);
            $typeData = fread($handle, 4);
            if (strlen($sizeData) < 4 || strlen($typeData) < 4) {
                break;
            }

            $size = unpack('N', $sizeData)[1];
            $type = $typeData;

            if ($size === 1) { // 64-bit large size
                $largeSize = fread($handle, 8);
                $size = unpack('J', $largeSize)[1];
            } elseif ($size === 0) {
                break;
            }

            if ($type === 'moov') {
                // Read inside moov container
                $moovEnd = ftell($handle) + $size - 8;
                while (ftell($handle) < $moovEnd) {
                    $subSizeData = fread($handle, 4);
                    $subType = fread($handle, 4);
                    if (strlen($subSizeData) < 4 || strlen($subType) < 4) {
                        break;
                    }
                    $subSize = unpack('N', $subSizeData)[1];
                    if ($subType === 'mvhd') {
                        $version = ord(fread($handle, 1));
                        fseek($handle, 3, SEEK_CUR); // flags
                        if ($version === 1) {
                            fseek($handle, 16, SEEK_CUR); // created + modified (64bit)
                            $timescale = unpack('N', fread($handle, 4))[1];
                            $duration = unpack('J', fread($handle, 8))[1];
                        } else {
                            fseek($handle, 8, SEEK_CUR); // created + modified (32bit)
                            $timescale = unpack('N', fread($handle, 4))[1];
                            $duration = unpack('N', fread($handle, 4))[1];
                        }
                        if ($timescale > 0) {
                            $meta['duration'] = round($duration / $timescale, 2);
                        }
                        break 2;
                    }
                    if ($subSize > 8) {
                        fseek($handle, $subSize - 8, SEEK_CUR);
                    } else {
                        break;
                    }
                }
            } else {
                fseek($handle, $size - 8, SEEK_CUR);
            }
        }
        fclose($handle);

        return $meta;
    }

    private function generateThumbnail(string $videoPath, string $thumbPath): void
    {
        $ffmpeg = Setting::get('ffmpeg_path', '');
        if (empty($ffmpeg)) {
            $ffmpeg = 'ffmpeg';
        }

        $cmd = escapeshellcmd($ffmpeg) . ' -y -i ' . escapeshellarg($videoPath) . ' -ss 00:00:01 -vframes 1 -vf "scale=360:640:force_original_aspect_ratio=decrease" ' . escapeshellarg($thumbPath);
        @shell_exec($cmd);
    }

    private function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'Uploaded video exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'Uploaded video exceeds MAX_FILE_SIZE specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write video file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.'
        };
    }
}
