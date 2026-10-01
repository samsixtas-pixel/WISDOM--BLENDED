<?php
declare(strict_types=1);

namespace Wisdom\Services;

use finfo;
use Wisdom\Core\AppException;

/** Validates and stores an uploaded file under a random, non-guessable name. */
final class FileUploader
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private string $directory, private int $maxBytes)
    {
    }

    /**
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int}|null $file
     * @throws AppException
     */
    public function store(?array $file): string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new AppException('Choose a file to upload.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new AppException('The file could not be uploaded. Please try again.');
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new AppException('Invalid upload.');
        }
        if ((int) ($file['size'] ?? 0) > $this->maxBytes) {
            throw new AppException(sprintf('The file must be smaller than %d MB.', intdiv($this->maxBytes, 1024 * 1024)));
        }

        // Trust the file's actual content type, never the client-supplied one.
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new AppException('Only JPG, PNG, or PDF files are accepted.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new AppException('The upload folder is unavailable.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $this->directory . '/' . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new AppException('The file could not be saved.');
        }
        chmod($destination, 0640);

        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            try {
                $this->reencodeImage($destination, $mime);
            } catch (AppException $exception) {
                @unlink($destination);
                throw $exception;
            }
        }

        return $filename;
    }

    private function reencodeImage(string $path, string $mime): string
    {
        if (!extension_loaded('gd')) return $path;
        $image = $mime === 'image/jpeg' ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if ($image === false || $image === null) throw new AppException('That image could not be processed.');
        if ($mime === 'image/png') { imagealphablending($image, false); imagesavealpha($image, true); }
        $tmp = $path . '.reencode';
        $ok = $mime === 'image/jpeg' ? imagejpeg($image, $tmp, 88) : imagepng($image, $tmp, 7);
        imagedestroy($image);
        if (!$ok || !@rename($tmp, $path)) { @unlink($tmp); throw new AppException('That image could not be re-encoded.'); }
        return $path;
    }

    public function pathFor(string $storedFilename): string
    {
        return $this->directory . '/' . basename($storedFilename);
    }
}
