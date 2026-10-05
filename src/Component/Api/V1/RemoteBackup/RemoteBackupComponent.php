<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Api\V1\RemoteBackup;

use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\Core\OComponent;

class RemoteBackupComponent extends OComponent {
  public ?Backup $backup = null;
}
