<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\AuditLog;

use Osumi\OsumiFramework\App\Model\AdminUser;
use Osumi\OsumiFramework\App\Model\AuditLog;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\Core\OComponent;

class AuditLogComponent extends OComponent {
  public ?AuditLog $audit_log = null;

  public ?string $actor_public_id = null;
  public ?string $actor_name = null;

  /**
   * Resolves display metadata for the event actor.
   *
   * @return void
   */
  public function run(): void {
    if (is_null($this->audit_log)) {
      return;
    }

    switch ($this->audit_log->actor_type) {
      case 'admin':
        $this->loadAdminActor();
        break;

      case 'installation':
        $this->loadInstallationActor();
        break;

      case 'system':
        $this->actor_name = 'Sistema';
        break;
    }
  }

  /**
   * Loads administrator actor metadata.
   *
   * @return void
   */
  private function loadAdminActor(): void {
    if (
      is_null($this->audit_log) ||
      is_null($this->audit_log->id_admin_user)
    ) {
      return;
    }

    $admin = AdminUser::findOne([
      'id' => $this->audit_log->id_admin_user
    ]);

    if (is_null($admin)) {
      return;
    }

    $this->actor_public_id = $admin->public_id;
    $this->actor_name = $admin->name;
  }

  /**
   * Loads installation actor metadata.
   *
   * @return void
   */
  private function loadInstallationActor(): void {
    if (
      is_null($this->audit_log) ||
      is_null($this->audit_log->id_installation)
    ) {
      return;
    }

    $installation = Installation::findOne([
      'id' => $this->audit_log->id_installation
    ]);

    if (is_null($installation)) {
      return;
    }

    $this->actor_public_id = $installation->public_id;
    $this->actor_name = $installation->name;
  }
}
