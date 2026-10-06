<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Throwable;
use Osumi\OsumiFramework\App\Service\BackupStorageReconciliationService;
use Osumi\OsumiFramework\Core\OTask;

class ReconcileBackupStorageTask extends OTask {
  /**
   * Gets the task description shown by the CLI.
   *
   * @return string Task description.
   */
  public function __toString(): string {
    return 'reconcileBackupStorage: Revisa la consistencia entre metadata y storage de backups';
  }

  /**
   * Runs a read-only reconciliation of backup storage.
   *
   * @param array<string, mixed> $options CLI options.
   *
   * @return void
   */
  public function run(
    array $options = []
  ): void {
    unset(
      $options
    );

    /** @var BackupStorageReconciliationService $service */
    $service = inject(
      BackupStorageReconciliationService::class
    );

    try {
      $report =
        $service->inspect();
    }
    catch (Throwable $exception) {
      echo "Error: no se ha podido revisar el storage de backups.\n";
      echo $exception->getMessage() . "\n";

      return;
    }

    echo "Reconciliación de TPV Backup\n";
    echo "============================\n\n";

    echo "Registros de metadata: "
      . $report['metadataCount']
      . "\n";

    echo "Objetos físicos: "
      . $report['storageObjectCount']
      . "\n";

    echo "Objetos huérfanos: "
      . count(
        $report['orphanedObjects']
      )
      . "\n";

    echo "Objetos ausentes: "
      . count(
        $report['missingObjects']
      )
      . "\n";

    echo "Metadata inválida: "
      . count(
        $report['invalidMetadata']
      )
      . "\n";

    if (
      $report['orphanedObjects'] !== []
    ) {
      echo "\nObjetos físicos sin metadata:\n";

      foreach (
        $report['orphanedObjects']
        as $object
      ) {
        echo "- "
          . $object['storageKey']
          . " ("
          . $object['sizeBytes']
          . " bytes, mtime "
          . date(
            'Y-m-d H:i:s',
            $object['modifiedAt']
          )
          . ")\n";
      }
    }

    if (
      $report['missingObjects'] !== []
    ) {
      echo "\nMetadata sin objeto físico:\n";

      foreach (
        $report['missingObjects']
        as $backup
      ) {
        echo "- publicId="
          . (
            $backup['publicId']
            ?? 'null'
          )
          . " backupId="
          . (
            $backup['backupId']
            ?? 'null'
          )
          . " storageKey="
          . $backup['storageKey']
          . "\n";
      }
    }

    if (
      $report['invalidMetadata'] !== []
    ) {
      echo "\nMetadata con storage_key inválido:\n";

      foreach (
        $report['invalidMetadata']
        as $backup
      ) {
        echo "- publicId="
          . (
            $backup['publicId']
            ?? 'null'
          )
          . " backupId="
          . (
            $backup['backupId']
            ?? 'null'
          )
          . "\n";
      }
    }

    echo "\nNo se ha modificado ningún dato.\n";
  }
}
