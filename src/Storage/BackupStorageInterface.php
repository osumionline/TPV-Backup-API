<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Storage;

interface BackupStorageInterface {
  /**
   * Stores a local source file using the supplied logical storage key.
   *
   * @param string $source_path Local source file.
   * @param string $storage_key Logical storage key.
   *
   * @return void
   */
  public function storeFile(
    string $source_path,
    string $storage_key
  ): void;

  /**
   * Opens a stored object for sequential reading.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return resource Readable stream.
   */
  public function openReadStream(
    string $storage_key
  ): mixed;

  /**
   * Checks whether a stored object exists.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return bool True when the object exists.
   */
  public function exists(
    string $storage_key
  ): bool;

  /**
   * Gets the size of a stored object in bytes.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return int Object size in bytes.
   */
  public function getSize(
    string $storage_key
  ): int;

  /**
   * Calculates the SHA-256 hash of a stored object.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return string Lowercase hexadecimal SHA-256 hash.
   */
  public function getSha256(
    string $storage_key
  ): string;

  /**
   * Deletes a stored object.
   *
   * The operation is idempotent when the object does not exist.
   *
   * @param string $storage_key Logical storage key.
   *
   * @return void
   */
  public function delete(
    string $storage_key
  ): void;
}
