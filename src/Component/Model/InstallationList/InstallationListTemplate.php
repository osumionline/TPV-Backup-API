<?php

use Osumi\OsumiFramework\App\Component\Model\Installation\InstallationComponent;

foreach ($list as $i => $installation) {
  $component = new InstallationComponent([
    'installation' => $installation
  ]);

  echo strval($component);

  if ($i < count($list) - 1) {
    echo ',';
  }
}
