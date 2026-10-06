<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use Osumi\OsumiFramework\App\Storage\BackupStorageInterface;
use Osumi\OsumiFramework\App\Storage\FileBackupStorage;
use Osumi\OsumiFramework\Core\OService;

class BackupStorageService extends OService {
  private BackupStorageInterface $storage;

  /**
   * Initializes the configured backup storage implementation.
   */
  public function __construct() {
    global $core;

    $configured_path = $core->config->getExtra(
      'backup_storage_path'
    );

    $root_path = $this->resolveRootPath(
      is_string($configured_path)
        ? $configured_path
        : null,
      $core->config->getDir('base')
    );

    $this->storage = new FileBackupStorage(
      $root_path
    );
  }

  /**
   * Stores a local source file.
   *
   * @param string $source_path Local source file.
   * @param string $storage_key Logical storage key.
   *
   * @return void
   */
  public function storeFile(
    string $source_path,
    string $storage_key
  ): void {
    $this->storage->storeFile(
      $source_path,
      $storage_key
    );
  }

  /**
   * Opens a stored backup for sequential reading.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return resource Readable stream.
   */
  public function openReadStream(
    string $storage_key
  ): mixed {
    return $this->storage->openReadStream(
      $storage_key
    );
  }

  /**
   * Checks whether a stored backup exists.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return bool True when the backup exists.
   */
  public function exists(
    string $storage_key
  ): bool {
    return $this->storage->exists(
      $storage_key
    );
  }

  /**
   * Gets the size of a stored backup.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return int Size in bytes.
   */
  public function getSize(
    string $storage_key
  ): int {
    return $this->storage->getSize(
      $storage_key
    );
  }

  /**
   * Calculates the SHA-256 hash of a stored backup.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return string Lowercase hexadecimal SHA-256 hash.
   */
  public function getSha256(
    string $storage_key
  ): string {
    return $this->storage->getSha256(
      $storage_key
    );
  }

  /**
   * Deletes a stored backup.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return void
   */
  public function delete(
    string $storage_key
  ): void {
    $this->storage->delete(
      $storage_key
    );
  }

  /**
   * Resolves the configured filesystem storage root.
   *
   * Relative paths are resolved from the application base directory. Linux,
   * Windows drive-letter and UNC absolute paths are preserved as supplied.
   *
   * @param string|null $configured_path Configured storage path.
   * @param string      $base_path       Application base directory.
   *
   * @return string Resolved storage root.
   *
   * @throws RuntimeException When the application base directory is invalid.
   */
  private function resolveRootPath(
    ?string $configured_path,
    string $base_path
  ): string {
    $base_path = rtrim(
      $base_path,
      '/\\'
    );

    if ($base_path === '') {
      throw new RuntimeException(
        'Application base path cannot be empty.'
      );
    }

    $configured_path = is_null($configured_path)
      ? ''
      : trim($configured_path);

    if ($configured_path === '') {
      return $base_path
        . DIRECTORY_SEPARATOR
        . 'storage'
        . DIRECTORY_SEPARATOR
        . 'backups';
    }

    $absolute = str_starts_with(
      $configured_path,
      '/'
    )
      || str_starts_with(
        $configured_path,
        '\\'
      )
      || preg_match(
        '/^[A-Za-z]:[\\\\\/]/D',
        $configured_path
      ) === 1;

    if ($absolute) {
      return $configured_path;
    }

    return $base_path
      . DIRECTORY_SEPARATOR
      . str_replace(
        ['/', '\\'],
        DIRECTORY_SEPARATOR,
        $configured_path
      );
  }
}
