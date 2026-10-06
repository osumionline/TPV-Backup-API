<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Storage;

use RuntimeException;
use Throwable;

class FileBackupStorage implements BackupStorageInterface {
  private string $root_path;

  /**
   * Creates a filesystem backup storage.
   *
   * @param string $root_path Root directory used to store backup objects.
   *
   * @throws RuntimeException When the storage directory cannot be created or resolved.
   */
  public function __construct(string $root_path) {
    $root_path = rtrim(
      trim($root_path),
      '/\\'
    );

    if ($root_path === '') {
      throw new RuntimeException(
        'Backup storage root path cannot be empty.'
      );
    }

    if (
      !is_dir($root_path) &&
      !mkdir(
        $root_path,
        0770,
        true
      ) &&
      !is_dir($root_path)
    ) {
      throw new RuntimeException(
        'Backup storage root directory could not be created.'
      );
    }

    $resolved_path = realpath($root_path);

    if ($resolved_path === false) {
      throw new RuntimeException(
        'Backup storage root directory could not be resolved.'
      );
    }

    $this->root_path = $resolved_path;
  }

  /**
   * Stores a local source file using the supplied logical storage key.
   *
   * The file is copied to a temporary destination in the target directory and
   * renamed only after the complete copy succeeds.
   *
   * @param string $source_path Local source file.
   * @param string $storage_key Logical storage key.
   *
   * @return void
   *
   * @throws RuntimeException When the source is invalid, the destination exists
   *                          or the file cannot be stored.
   */
  public function storeFile(
    string $source_path,
    string $storage_key
  ): void {
    if (!is_file($source_path)) {
      throw new RuntimeException(
        'Backup storage source file does not exist.'
      );
    }

    $destination_path = $this->resolvePath($storage_key);

    if (file_exists($destination_path)) {
      throw new RuntimeException(
        'Backup storage destination already exists.'
      );
    }

    $destination_directory = dirname($destination_path);

    if (
      !is_dir($destination_directory) &&
      !mkdir(
        $destination_directory,
        0770,
        true
      ) &&
      !is_dir($destination_directory)
    ) {
      throw new RuntimeException(
        'Backup storage destination directory could not be created.'
      );
    }

    $temporary_path = $destination_path
      . '.tmp-'
      . bin2hex(random_bytes(16));

    $source_stream = null;
    $destination_stream = null;

    try {
      $source_stream = fopen(
        $source_path,
        'rb'
      );

      if ($source_stream === false) {
        throw new RuntimeException(
          'Backup storage source file could not be opened.'
        );
      }

      $destination_stream = fopen(
        $temporary_path,
        'xb'
      );

      if ($destination_stream === false) {
        throw new RuntimeException(
          'Backup storage temporary file could not be opened.'
        );
      }

      $copied_bytes = stream_copy_to_stream(
        $source_stream,
        $destination_stream
      );

      if ($copied_bytes === false) {
        throw new RuntimeException(
          'Backup storage file could not be copied.'
        );
      }

      if (!fflush($destination_stream)) {
        throw new RuntimeException(
          'Backup storage temporary file could not be flushed.'
        );
      }

      fclose($source_stream);
      $source_stream = null;

      fclose($destination_stream);
      $destination_stream = null;

      if (!rename($temporary_path, $destination_path)) {
        throw new RuntimeException(
          'Backup storage temporary file could not be committed.'
        );
      }
    }
    catch (Throwable $exception) {
      if (is_resource($source_stream)) {
        fclose($source_stream);
      }

      if (is_resource($destination_stream)) {
        fclose($destination_stream);
      }

      if (is_file($temporary_path)) {
        @unlink($temporary_path);
      }

      if ($exception instanceof RuntimeException) {
        throw $exception;
      }

      throw new RuntimeException(
        'Backup storage file could not be stored.',
        0,
        $exception
      );
    }
  }

  /**
   * Opens a stored object for sequential reading.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return resource Readable stream.
   *
   * @throws RuntimeException When the object does not exist or cannot be opened.
   */
  public function openReadStream(
    string $storage_key
  ): mixed {
    $path = $this->resolvePath($storage_key);

    if (!is_file($path)) {
      throw new RuntimeException(
        'Backup storage object does not exist.'
      );
    }

    $stream = fopen(
      $path,
      'rb'
    );

    if ($stream === false) {
      throw new RuntimeException(
        'Backup storage object could not be opened.'
      );
    }

    return $stream;
  }

  /**
   * Checks whether a stored object exists.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return bool True when the object exists.
   */
  public function exists(
    string $storage_key
  ): bool {
    return is_file(
      $this->resolvePath($storage_key)
    );
  }

  /**
   * Gets the size of a stored object in bytes.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return int Object size in bytes.
   *
   * @throws RuntimeException When the object does not exist or its size cannot be obtained.
   */
  public function getSize(
    string $storage_key
  ): int {
    $path = $this->resolvePath($storage_key);

    if (!is_file($path)) {
      throw new RuntimeException(
        'Backup storage object does not exist.'
      );
    }

    $size = filesize($path);

    if ($size === false) {
      throw new RuntimeException(
        'Backup storage object size could not be obtained.'
      );
    }

    return $size;
  }

  /**
   * Calculates the SHA-256 hash of a stored object.
   *
   * The object is read sequentially by hash_file() and
   * is never materialized completely in memory.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return string Lowercase hexadecimal SHA-256 hash.
   *
   * @throws RuntimeException When the object does not exist
   *                          or its hash cannot be calculated.
   */
  public function getSha256(
    string $storage_key
  ): string {
    $path = $this->resolvePath(
      $storage_key
    );

    if (!is_file($path)) {
      throw new RuntimeException(
        'Backup storage object does not exist.'
      );
    }

    $sha256 = hash_file(
      'sha256',
      $path
    );

    if ($sha256 === false) {
      throw new RuntimeException(
        'Backup storage object SHA-256 could not be calculated.'
      );
    }

    return $sha256;
  }

  /**
   * Deletes a stored object.
   *
   * The operation is idempotent when the object does not exist.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return void
   *
   * @throws RuntimeException When the object cannot be deleted.
   */
  public function delete(
    string $storage_key
  ): void {
    $path = $this->resolvePath($storage_key);

    if (!is_file($path)) {
      return;
    }

    if (!unlink($path)) {
      throw new RuntimeException(
        'Backup storage object could not be deleted.'
      );
    }

    $this->removeEmptyParentDirectories(
      dirname($path)
    );
  }

  /**
   * Resolves and validates a logical storage key.
   *
   * Storage keys are deliberately restricted to portable path segments to
   * prevent directory traversal and platform-specific path ambiguities.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return string Absolute filesystem path.
   *
   * @throws RuntimeException When the storage key is invalid.
   */
  private function resolvePath(
    string $storage_key
  ): string {
    if (
      $storage_key === '' ||
      preg_match(
        '/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*$/D',
        $storage_key
      ) !== 1
    ) {
      throw new RuntimeException(
        'Invalid backup storage key.'
      );
    }

    $segments = explode(
      '/',
      $storage_key
    );

    foreach ($segments as $segment) {
      if (
        $segment === '.' ||
        $segment === '..'
      ) {
        throw new RuntimeException(
          'Invalid backup storage key.'
        );
      }
    }

    return $this->root_path
      . DIRECTORY_SEPARATOR
      . str_replace(
        '/',
        DIRECTORY_SEPARATOR,
        $storage_key
      );
  }

  /**
   * Removes empty directories below the configured storage root.
   *
   * @param string $directory Directory to inspect.
   *
   * @return void
   */
  private function removeEmptyParentDirectories(
    string $directory
  ): void {
    while (
      $directory !== $this->root_path &&
      str_starts_with(
        $directory,
        $this->root_path . DIRECTORY_SEPARATOR
      )
    ) {
      $entries = scandir($directory);

      if (
        $entries === false ||
        count($entries) > 2
      ) {
        return;
      }

      if (!rmdir($directory)) {
        return;
      }

      $directory = dirname($directory);
    }
  }
}
