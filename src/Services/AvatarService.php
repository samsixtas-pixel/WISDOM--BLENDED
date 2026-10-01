<?php
declare(strict_types=1);

namespace Wisdom\Services;

use finfo;
use Wisdom\Core\AppException;
use Wisdom\Models\User;
use Wisdom\Repositories\AuditLogRepository;
use Wisdom\Repositories\UserRepository;

/** Validates and stores profile pictures outside the public document root. */
final class AvatarService
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private string $directory,
        private int $maxBytes,
        private UserRepository $users,
        private AuditLogRepository $audit,
    ) {
    }

    /**
     * @param array<string,mixed>|null $file
     * @throws AppException
     */
    public function store(User $user, ?array $file): string
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new AppException('Choose an image to upload.');
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new AppException('The image could not be uploaded. Please try again.');
        }

        $temporaryPath = $file['tmp_name'] ?? '';
        if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new AppException('Invalid upload. Please choose the image again.');
        }

        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > $this->maxBytes) {
            throw new AppException('The image must be smaller than 2 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extension = is_string($mime) ? (self::ALLOWED_MIME[$mime] ?? null) : null;
        $imageInfo = @getimagesize($temporaryPath);
        if ($extension === null || $imageInfo === false || !isset($imageInfo['mime']) || $imageInfo['mime'] !== $mime) {
            throw new AppException('Only valid JPG, PNG, GIF or WebP images are accepted.');
        }
        if (($imageInfo[0] * $imageInfo[1]) > 40000000) {
            throw new AppException('The image dimensions are too large.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new AppException('The profile-picture folder is unavailable.');
        }

        $filename = 'avatar-' . $user->getId() . '-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new AppException('The image could not be saved.');
        }
        chmod($destination, 0640);

        try {
            $this->users->updateAvatar($user->getId(), $filename);
        } catch (\Throwable $exception) {
            if (is_file($destination)) {
                unlink($destination);
            }
            throw $exception;
        }

        $oldAvatar = $user->getAvatar();
        if ($oldAvatar !== null && $oldAvatar !== '') {
            $oldPath = $this->pathFor($oldAvatar);
            if ($oldPath !== null && $oldPath !== $destination) {
                unlink($oldPath);
            }
        }
        $this->audit->record($user->getId(), 'profile.avatar_updated');

        return $filename;
    }

    public function pathFor(?string $filename): ?string
    {
        if ($filename === null || $filename === '' || basename($filename) !== $filename) {
            return null;
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        return is_file($path) && is_readable($path) ? $path : null;
    }
}