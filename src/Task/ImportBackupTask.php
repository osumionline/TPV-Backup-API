<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Throwable;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OTask;

class ImportBackupTask extends OTask {
  /**
   * Gets the task description shown by the CLI.
   *
   * @return string Task description.
   */
  public function __toString(): string {
    return 'importBackup: Importa una copia .otpv para una instalación';
  }

  /**
   * Imports an OTPV backup through the normal backup persistence service.
   *
   * @param array<string, mixed> $options CLI options.
   *
   * @return void
   */
  public function run(
    array $options = []
  ): void {
    $installation_public_id = isset(
      $options['installation']
    )
      ? trim(
        (string) $options['installation']
      )
      : '';

    $file_path = isset(
      $options['file']
    )
      ? trim(
        (string) $options['file']
      )
      : '';

    if (
      $installation_public_id === '' ||
      $file_path === ''
    ) {
      $this->showUsage();

      return;
    }

    $resolved_file_path = realpath(
      $file_path
    );

    if (
      $resolved_file_path === false ||
      !is_file($resolved_file_path) ||
      !is_readable($resolved_file_path)
    ) {
      echo "Error: el archivo indicado no existe o no se puede leer.\n";

      return;
    }

    /** @var InstallationService $installation_service */
    $installation_service = inject(
      InstallationService::class
    );

    $installation = $installation_service->getByPublicId(
      $installation_public_id
    );

    if (is_null($installation)) {
      echo "Error: no existe ninguna instalación con ese public ID.\n";

      return;
    }

    /** @var BackupService $backup_service */
    $backup_service = inject(
      BackupService::class
    );

    try {
      $backup = $backup_service->createFromFile(
        $installation,
        $resolved_file_path,
        basename(
          $resolved_file_path
        )
      );
    }
    catch (Throwable $exception) {
      echo "Error: no se ha podido importar la copia.\n";
      echo $exception->getMessage() . "\n";

      return;
    }

    echo "Copia importada correctamente.\n";
    echo "Public ID: " . $backup->public_id . "\n";
    echo "Backup ID: " . $backup->backup_id . "\n";
    echo "Archivo: " . $backup->original_filename . "\n";
    echo "Tamaño: " . $backup->size_bytes . " bytes\n";
    echo "SHA-256: " . $backup->sha256 . "\n";
    echo "Storage key: " . $backup->storage_key . "\n";
  }

  /**
   * Shows the command usage help.
   *
   * @return void
   */
  private function showUsage(): void {
    echo "Uso:\n";
    echo "  php of importBackup "
      . "--installation <PUBLIC_ID> "
      . "--file <RUTA_OTPV>\n\n";

    echo "Ejemplo:\n";
    echo "  php of importBackup "
      . "--installation "
      . "123e4567-e89b-42d3-a456-426614174000 "
      . "--file \"C:\\backups\\copia.otpv\"\n";
  }
}
