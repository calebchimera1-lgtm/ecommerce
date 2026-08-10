<?php

declare(strict_types=1);

namespace App\Services\Upload;

use RuntimeException;

/**
 * Validated image upload handling. Never trusts the client-supplied
 * filename, extension, or MIME type:
 *  - the real MIME type is sniffed server-side via fileinfo,
 *  - the file is confirmed to actually decode as an image via
 *    getimagesize(),
 *  - the stored filename is a random hex string with a
 *    server-determined extension (never the uploaded name),
 *  - move_uploaded_file() is used, which refuses anything that isn't
 *    a genuine upload (blocks local file inclusion via a crafted
 *    $_FILES array).
 */
final class ImageUploader
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return string|null Web-relative path (e.g. "/uploads/products/xyz.jpg"), or null if no file was submitted.
     * @throws RuntimeException on an invalid/oversized/disallowed file.
     */
    public static function store(array $file, string $subdir): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The file failed to upload. Please try again.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid upload.');
        }

        $maxBytes = (int) config('uploads.max_size_mb') * 1024 * 1024;

        if ($file['size'] > $maxBytes) {
            throw new RuntimeException(sprintf('Image exceeds the %dMB size limit.', config('uploads.max_size_mb')));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        $allowed = (array) config('uploads.allowed_image_types');

        if ($extension === null || !in_array($extension, $allowed, true)) {
            throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
        }

        if (@getimagesize($file['tmp_name']) === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $subdir = trim($subdir, '/');
        $destinationDir = dirname(__DIR__, 3) . '/public/uploads/' . $subdir;

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Could not prepare the upload directory.');
        }

        $destinationPath = $destinationDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }

        return '/uploads/' . $subdir . '/' . $filename;
    }

    /**
     * Handles a multi-file input (`<input type="file" name="images[]" multiple>`),
     * which PHP delivers as parallel arrays rather than a list of
     * single-file arrays.
     *
     * @return string[] Web-relative paths of every file actually stored.
     */
    public static function storeMany(array $filesField, string $subdir): array
    {
        $stored = [];
        $count = count($filesField['name'] ?? []);

        for ($i = 0; $i < $count; $i++) {
            $single = [
                'name' => $filesField['name'][$i],
                'type' => $filesField['type'][$i],
                'tmp_name' => $filesField['tmp_name'][$i],
                'error' => $filesField['error'][$i],
                'size' => $filesField['size'][$i],
            ];

            $path = self::store($single, $subdir);

            if ($path !== null) {
                $stored[] = $path;
            }
        }

        return $stored;
    }

    /**
     * Every current caller passes a path this class itself generated
     * and the app previously wrote to a DB column (never raw request
     * input) - store() only ever produces
     * "/uploads/{subdir}/{random-hex}.{ext}", so there is no live path
     * traversal today. The realpath() containment check below exists
     * as defense-in-depth regardless: a prefix check on the *string*
     * `/uploads/` alone would not catch `/uploads/../../etc/passwd`
     * (which does start with that prefix), so if a future caller ever
     * passes a less-trusted value, this stops it from deleting
     * anything outside the uploads directory rather than relying on
     * every future caller getting that right independently.
     */
    public static function delete(string $webPath): void
    {
        if (!str_starts_with($webPath, '/uploads/')) {
            return;
        }

        $uploadsRoot = realpath(dirname(__DIR__, 3) . '/public/uploads');
        $fullPath = realpath(dirname(__DIR__, 3) . '/public' . $webPath);

        if ($uploadsRoot === false || $fullPath === false) {
            return;
        }

        if (!str_starts_with($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            return;
        }

        unlink($fullPath);
    }
}
