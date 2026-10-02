<?php

use Osumi\OsumiFramework\App\Component\Model\Backup\BackupComponent;

foreach ($list as $i => $backup) {
  $component = new BackupComponent([
    'backup' => $backup
  ]);

  echo strval($component);

  if ($i < count($list) - 1) {
    echo ',';
  }
}
