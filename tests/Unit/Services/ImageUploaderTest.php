<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Upload\ImageUploader;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the defense-in-depth fix in
 * ImageUploader::delete() found during Module 24's security review:
 * a bare string-prefix check on "/uploads/" does not stop
 * "/uploads/../../something" from resolving outside the uploads
 * directory. No current caller passes attacker-controlled input here
 * (see the class docblock), but delete() should refuse to escape its
 * own directory regardless of who calls it or with what.
 */
final class ImageUploaderTest extends TestCase
{
    public function test_delete_ignores_a_path_that_does_not_start_with_uploads(): void
    {
        // Should simply return - no exception, no attempted unlink.
        ImageUploader::delete('/etc/passwd');
        $this->addToAssertionCount(1);
    }

    public function test_delete_does_not_escape_the_uploads_directory_via_traversal(): void
    {
        $root = dirname(__DIR__, 3);
        $canary = $root . '/storage/logs/traversal-canary.txt';
        file_put_contents($canary, 'must survive');

        // "/uploads/" prefix check alone would pass this string; the
        // realpath containment check must still block it.
        ImageUploader::delete('/uploads/../../storage/logs/traversal-canary.txt');

        $this->assertFileExists($canary, 'delete() must never remove a file outside public/uploads.');

        unlink($canary);
    }

    public function test_delete_removes_a_real_file_inside_uploads(): void
    {
        $root = dirname(__DIR__, 3);
        $dir = $root . '/public/uploads/products';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/test-delete-' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($path, 'not a real image, just needs to exist');

        ImageUploader::delete('/uploads/products/' . basename($path));

        $this->assertFileDoesNotExist($path);
    }
}
