<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Storage;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use Osumi\OsumiFramework\App\Storage\FileBackupStorage;

final class FileBackupStorageTest extends TestCase {
  private string $temporary_directory;
  private string $storage_directory;

  /**
   * Creates an isolated temporary filesystem for each test.
   *
   * @return void
   */
  protected function setUp(): void {
    parent::setUp();

    $this->temporary_directory = sys_get_temp_dir()
      . DIRECTORY_SEPARATOR
      . 'tpv-backup-storage-test-'
      . bin2hex(random_bytes(8));

    $this->storage_directory = $this->temporary_directory
      . DIRECTORY_SEPARATOR
      . 'storage';

    if (
      !mkdir(
        $this->temporary_directory,
        0700,
        true
      ) &&
      !is_dir($this->temporary_directory)
    ) {
      self::fail('Temporary test directory could not be created.');
    }
  }

  /**
   * Removes the temporary filesystem after each test.
   *
   * @return void
   */
  protected function tearDown(): void {
    $this->removeDirectory(
      $this->temporary_directory
    );

    parent::tearDown();
  }

  /**
   * Verifies that a file can be stored, read, measured and deleted.
   *
   * @return void
   */
  public function testStoreReadSizeAndDelete(): void {
    $source_path = $this->temporary_directory
      . DIRECTORY_SEPARATOR
      . 'source.otpv';

    $contents = random_bytes(4096);

    file_put_contents(
      $source_path,
      $contents
    );

    $storage = new FileBackupStorage(
      $this->storage_directory
    );

    $storage_key = 'installations/test-installation/test-backup.otpv';

    $storage->storeFile(
      $source_path,
      $storage_key
    );

    self::assertTrue(
      $storage->exists($storage_key)
    );

    self::assertSame(
      strlen($contents),
      $storage->getSize($storage_key)
    );

    $stream = $storage->openReadStream(
      $storage_key
    );

    self::assertTrue(
      is_resource($stream)
    );

    $stored_contents = stream_get_contents(
      $stream
    );

    fclose($stream);

    self::assertSame(
      $contents,
      $stored_contents
    );

    $storage->delete(
      $storage_key
    );

    self::assertFalse(
      $storage->exists($storage_key)
    );
  }

  /**
   * Verifies that an existing storage key cannot be overwritten.
   *
   * @return void
   */
  public function testStoreRejectsDuplicateDestination(): void {
    $source_path = $this->temporary_directory
      . DIRECTORY_SEPARATOR
      . 'source.otpv';

    file_put_contents(
      $source_path,
      'backup-data'
    );

    $storage = new FileBackupStorage(
      $this->storage_directory
    );

    $storage_key = 'installations/test-installation/test-backup.otpv';

    $storage->storeFile(
      $source_path,
      $storage_key
    );

    $this->expectException(
      RuntimeException::class
    );

    $storage->storeFile(
      $source_path,
      $storage_key
    );
  }

  /**
   * Verifies that directory traversal storage keys are rejected.
   *
   * @return void
   */
  public function testRejectsUnsafeStorageKey(): void {
    $storage = new FileBackupStorage(
      $this->storage_directory
    );

    $this->expectException(
      RuntimeException::class
    );

    $storage->exists(
      '../outside.otpv'
    );
  }

  /**
   * Verifies that deleting a missing object is idempotent.
   *
   * @return void
   */
  public function testDeleteMissingObjectIsIdempotent(): void {
    $storage = new FileBackupStorage(
      $this->storage_directory
    );

    $storage->delete(
      'installations/test-installation/missing.otpv'
    );

    self::assertFalse(
      $storage->exists(
        'installations/test-installation/missing.otpv'
      )
    );
  }

  /**
   * Recursively removes a directory used by a test.
   *
   * @param string $directory Directory to remove.
   *
   * @return void
   */
  private function removeDirectory(
    string $directory
  ): void {
    if (!is_dir($directory)) {
      return;
    }

    $entries = scandir(
      $directory
    );

    if ($entries === false) {
      return;
    }

    foreach ($entries as $entry) {
      if (
        $entry === '.' ||
        $entry === '..'
      ) {
        continue;
      }

      $path = $directory
        . DIRECTORY_SEPARATOR
        . $entry;

      if (is_dir($path)) {
        $this->removeDirectory(
          $path
        );
        continue;
      }

      unlink($path);
    }

    rmdir($directory);
  }
}
