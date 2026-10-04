<?php

use Osumi\OsumiFramework\App\Component\Model\AuditLog\AuditLogComponent;

foreach ($list as $i => $audit_log) {
  $component = new AuditLogComponent([
    'audit_log' => $audit_log
  ]);

  echo strval($component);

  if ($i < count($list) - 1) {
    echo ',';
  }
}
