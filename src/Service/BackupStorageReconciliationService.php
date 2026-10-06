<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\Core\OService;

class BackupStorageReconciliationService extends OService {
  private BackupStorageService $storage_service;

  /**
   * Initializes reconciliation dependencies.
   */
  public function __construct() {
    $this->storage_service = inject(
      BackupStorageService::class
    );
  }

  /**
   * Compares persisted backup metadata with
   * the physical objects currently stored.
   *
   * This operation is strictly read-only.
   *
   * @return array{
   *   metadataCount: int,
   *   storageObjectCount: int,
   *   orphanedObjects: array<int, array{
   *     storageKey: string,
   *     sizeBytes: int,
   *     modifiedAt: int
   *   }>,
   *   missingObjects: array<int, array{
   *     publicId: string|null,
   *     backupId: string|null,
   *     storageKey: string
   *   }>,
   *   invalidMetadata: array<int, array{
   *     publicId: string|null,
   *     backupId: string|null
   *   }>
   * } Reconciliation report.
   */
  public function inspect(): array {
    $backups =
      $this->getBackups();

    $objects =
      $this->storage_service->listObjects();

    $metadata_keys = [];

    $invalid_metadata = [];

    foreach ($backups as $backup) {
      if (
        is_null($backup->storage_key) ||
        trim(
          $backup->storage_key
        ) === ''
      ) {
        $invalid_metadata[] = [
          'publicId' =>
            $backup->public_id,

          'backupId' =>
            $backup->backup_id
        ];

        continue;
      }

      $metadata_keys[
        $backup->storage_key
      ] = true;
    }

    $physical_keys = [];

    foreach ($objects as $object) {
      $physical_keys[
        $object['storageKey']
      ] = true;
    }

    $orphaned_objects = [];

    foreach ($objects as $object) {
      if (
        !isset(
          $metadata_keys[
            $object['storageKey']
          ]
        )
      ) {
        $orphaned_objects[] =
          $object;
      }
    }

    $missing_objects = [];

    foreach ($backups as $backup) {
      if (
        is_null($backup->storage_key) ||
        trim(
          $backup->storage_key
        ) === ''
      ) {
        continue;
      }

      if (
        isset(
          $physical_keys[
            $backup->storage_key
          ]
        )
      ) {
        continue;
      }

      $missing_objects[] = [
        'publicId' =>
          $backup->public_id,

        'backupId' =>
          $backup->backup_id,

        'storageKey' =>
          $backup->storage_key
      ];
    }

    return [
      'metadataCount' =>
        count(
          $backups
        ),

      'storageObjectCount' =>
        count(
          $objects
        ),

      'orphanedObjects' =>
        $orphaned_objects,

      'missingObjects' =>
        $missing_objects,

      'invalidMetadata' =>
        $invalid_metadata
    ];
  }

  /**
   * Gets every persisted backup metadata record.
   *
   * Kept as a separate method so reconciliation
   * can be tested without a real database.
   *
   * @return Backup[] Persisted backups.
   */
  protected function getBackups(): array {
    return Backup::all();
  }
}
