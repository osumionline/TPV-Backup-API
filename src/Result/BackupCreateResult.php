<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Result;

use Osumi\OsumiFramework\App\Model\Backup;

final readonly class BackupCreateResult {
  /**
   * Creates a backup creation result.
   *
   * @param Backup $backup  Persisted or previously existing backup.
   * @param bool   $created Whether a new backup was actually created.
   */
  public function __construct(
    public Backup $backup,
    public bool $created
  ) {
  }
}
