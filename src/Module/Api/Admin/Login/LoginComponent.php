<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Login;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\AdminLoginDTO;
use Osumi\OsumiFramework\App\Service\AdminAuthService;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class LoginComponent extends OComponent {
  private ?AdminAuthService $auth_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status     = 'error';
  public string $token      = '';
  public int    $expires_at = 0;
  public string $public_id  = '';
  public string $name       = '';
  public string $email      = '';

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->auth_service = inject(AdminAuthService::class);
    $this->audit_log_service = inject(AuditLogService::class);
  }

  /**
   * Authenticates an administrator.
   *
   * @param AdminLoginDTO $dto Login request data.
   *
   * @return void
   */
  public function run(AdminLoginDTO $dto): void {
    global $core;

    if (!$dto->isValid() || is_null($dto->email) || is_null($dto->password)) {
      $core->setHttpStatus(400);
      return;
    }

    $result = $this->auth_service->login($dto->email, $dto->password);

    if (is_null($result)) {
      $core->setHttpStatus(401);
      return;
    }

    $admin = $result['admin'];

    $this->audit_log_service?->recordAdminUserAction(
      $admin,
      AuditLogService::ACTION_ADMIN_LOGIN,
      AuditLogService::ENTITY_ADMIN_USER,
      $admin->public_id
    );

    $this->status     = 'ok';
    $this->token      = $result['token'];
    $this->expires_at = $result['expires_at'];
    $this->public_id  = $admin->public_id ?? '';
    $this->name       = $admin->name ?? '';
    $this->email      = $admin->email ?? '';
  }
}
