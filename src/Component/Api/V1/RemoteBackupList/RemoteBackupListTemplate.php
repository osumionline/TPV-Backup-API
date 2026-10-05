<?php

use Osumi\OsumiFramework\App\Component\Api\V1\RemoteBackup\RemoteBackupComponent;

foreach ($list as $index => $backup) {
  $component = new RemoteBackupComponent([
    'backup' => $backup
  ]);

  echo strval(
    $component
  );

  if ($index < count($list) - 1) {
    echo ',';
  }
}
