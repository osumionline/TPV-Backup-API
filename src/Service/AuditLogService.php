<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use JsonException;
use Throwable;
use InvalidArgumentException;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Model\AuditLog;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\AdminUser;

class AuditLogService extends OService {
  public const ACTION_BACKUP_DOWNLOAD = 'backup.download';
  public const ACTION_BACKUP_DELETE = 'backup.delete';
  public const ACTION_BACKUP_RETENTION_DELETE = 'backup.retention_delete';

  public const ENTITY_BACKUP = 'backup';

  public const ACTION_ADMIN_LOGIN = 'admin.login';

  public const ACTION_SUBSCRIPTION_CREATE = 'subscription.create';
  public const ACTION_SUBSCRIPTION_UPDATE = 'subscription.update';
  public const ACTION_SUBSCRIPTION_SET_ACTIVE = 'subscription.set_active';
  public const ACTION_SUBSCRIPTION_DELETE = 'subscription.delete';

  public const ACTION_INSTALLATION_CREATE = 'installation.create';
  public const ACTION_INSTALLATION_UPDATE = 'installation.update';
  public const ACTION_INSTALLATION_SET_ACTIVE = 'installation.set_active';
  public const ACTION_INSTALLATION_DELETE = 'installation.delete';
  public const ACTION_INSTALLATION_CREDENTIAL_ROTATE =
    'installation.credential_rotate';
  public const ACTION_INSTALLATION_CREDENTIAL_REVOKE =
    'installation.credential_revoke';
  public const ACTION_INSTALLATION_AUTHENTICATE =
  'installation.authenticate';

  public const ENTITY_ADMIN_USER = 'admin_user';
  public const ENTITY_SUBSCRIPTION = 'subscription';
  public const ENTITY_INSTALLATION = 'installation';

  private const ACTOR_ADMIN = 'admin';
  private const ACTOR_INSTALLATION = 'installation';
  private const ACTOR_SYSTEM = 'system';

  /**
   * Gets a page of audit events ordered from newest to oldest.
   *
   * @param int $page      One-based page number.
   * @param int $page_size Number of events per page.
   *
   * @return AuditLog[] Audit events for the requested page.
   *
   * @throws InvalidArgumentException When pagination values are invalid.
   */
  public function getPage(
    int $page,
    int $page_size
  ): array {
    if (
      $page < 1 ||
      $page_size < 1
    ) {
      throw new InvalidArgumentException(
        'Audit pagination values must be greater than zero.'
      );
    }

    return AuditLog::all([
      'order_by' => 'id#DESC',
      'limit' => $page_size,
      'offset' => ($page - 1) * $page_size
    ]);
  }

  /**
   * Gets the total number of audit events.
   *
   * @return int Total audit event count.
   */
  public function countAll(): int {
    return AuditLog::count();
  }

  /**
   * Records an action performed by the authenticated administrator.
   *
   * Audit persistence is best-effort so a logging failure never converts an
   * already completed business operation into an API failure.
   *
   * @param string               $action           Audited action code.
   * @param string               $entity_type      Affected entity type.
   * @param string|null          $entity_public_id Public identifier of the affected entity.
   * @param array<string, mixed> $data             Additional non-sensitive event data.
   *
   * @return bool True when the audit event was persisted.
   */
  public function recordAdminAction(
    string $action,
    string $entity_type,
    ?string $entity_public_id = null,
    array $data = []
  ): bool {
    $admin_id = $this->getAuthenticatedAdminId();

    if (is_null($admin_id)) {
      $this->logFailure(
        'Authenticated administrator context is unavailable.'
      );

      return false;
    }

    return $this->record(
      self::ACTOR_ADMIN,
      $admin_id,
      null,
      $action,
      $entity_type,
      $entity_public_id,
      $data,
      $this->getClientIp(),
      $this->getUserAgent()
    );
  }

  /**
   * Records an action performed by a known administrator.
   *
   * This variant is used before AdminAuth middleware context exists, such as
   * immediately after a successful administrator login.
   *
   * @param AdminUser             $admin            Administrator actor.
   * @param string                $action           Audited action code.
   * @param string                $entity_type      Affected entity type.
   * @param string|null           $entity_public_id Public identifier of the affected entity.
   * @param array<string, mixed>  $data             Additional non-sensitive event data.
   *
   * @return bool True when the audit event was persisted.
   */
  public function recordAdminUserAction(
    AdminUser $admin,
    string $action,
    string $entity_type,
    ?string $entity_public_id = null,
    array $data = []
  ): bool {
    if (is_null($admin->id)) {
      $this->logFailure(
        'Administrator actor must be persisted before creating an audit event.'
      );

      return false;
    }

    return $this->record(
      self::ACTOR_ADMIN,
      $admin->id,
      null,
      $action,
      $entity_type,
      $entity_public_id,
      $data,
      $this->getClientIp(),
      $this->getUserAgent()
    );
  }

  /**
   * Records an action performed on behalf of an installation.
   *
   * @param Installation         $installation     Installation actor.
   * @param string               $action           Audited action code.
   * @param string               $entity_type      Affected entity type.
   * @param string|null          $entity_public_id Public identifier of the affected entity.
   * @param array<string, mixed> $data             Additional non-sensitive event data.
   *
   * @return bool True when the audit event was persisted.
   */
  public function recordInstallationAction(
    Installation $installation,
    string $action,
    string $entity_type,
    ?string $entity_public_id = null,
    array $data = []
  ): bool {
    if (is_null($installation->id)) {
      $this->logFailure(
        'Installation actor must be persisted before creating an audit event.'
      );

      return false;
    }

    return $this->record(
      self::ACTOR_INSTALLATION,
      null,
      $installation->id,
      $action,
      $entity_type,
      $entity_public_id,
      $data,
      $this->getClientIp(),
      $this->getUserAgent()
    );
  }

  /**
   * Records an internally generated system action.
   *
   * @param string               $action           Audited action code.
   * @param string               $entity_type      Affected entity type.
   * @param string|null          $entity_public_id Public identifier of the affected entity.
   * @param array<string, mixed> $data             Additional non-sensitive event data.
   *
   * @return bool True when the audit event was persisted.
   */
  public function recordSystemAction(
    string $action,
    string $entity_type,
    ?string $entity_public_id = null,
    array $data = []
  ): bool {
    return $this->record(
      self::ACTOR_SYSTEM,
      null,
      null,
      $action,
      $entity_type,
      $entity_public_id,
      $data,
      null,
      null
    );
  }

  /**
   * Persists one normalized audit event.
   *
   * @param string               $actor_type       Actor type.
   * @param int|null             $id_admin_user    Administrator identifier.
   * @param int|null             $id_installation  Installation identifier.
   * @param string               $action           Audited action code.
   * @param string               $entity_type      Affected entity type.
   * @param string|null          $entity_public_id Public identifier of the affected entity.
   * @param array<string, mixed> $data             Additional non-sensitive event data.
   * @param string|null          $ip               Origin IP address.
   * @param string|null          $user_agent       Origin User-Agent.
   *
   * @return bool True when the audit event was persisted.
   */
  private function record(
    string $actor_type,
    ?int $id_admin_user,
    ?int $id_installation,
    string $action,
    string $entity_type,
    ?string $entity_public_id,
    array $data,
    ?string $ip,
    ?string $user_agent
  ): bool {
    try {
      $audit_log = new AuditLog();

      $audit_log->actor_type = $actor_type;
      $audit_log->id_admin_user = $id_admin_user;
      $audit_log->id_installation = $id_installation;
      $audit_log->action = $action;
      $audit_log->entity_type = $entity_type;
      $audit_log->entity_public_id = $entity_public_id;
      $audit_log->data = $this->encodeData(
        $data
      );
      $audit_log->ip = $ip;
      $audit_log->user_agent = $user_agent;

      if (!$this->persist($audit_log)) {
        $this->logFailure(
          "Audit event '{$action}' could not be persisted."
        );

        return false;
      }

      return true;
    }
    catch (Throwable $exception) {
      $this->logFailure(
        "Audit event '{$action}' failed: "
        . $exception->getMessage()
      );

      return false;
    }
  }

  /**
   * Encodes non-sensitive audit metadata as JSON.
   *
   * @param array<string, mixed> $data Audit metadata.
   *
   * @return string|null Encoded metadata or null when no metadata is provided.
   *
   * @throws JsonException When metadata cannot be encoded.
   */
  private function encodeData(
    array $data
  ): ?string {
    if ($data === []) {
      return null;
    }

    return json_encode(
      $data,
      JSON_UNESCAPED_UNICODE |
      JSON_UNESCAPED_SLASHES |
      JSON_THROW_ON_ERROR
    );
  }

  /**
   * Gets the authenticated administrator identifier from middleware context.
   *
   * @return int|null Administrator identifier or null when unavailable.
   */
  protected function getAuthenticatedAdminId(): ?int {
    $admin_id = OMiddleware::getContext(
      'AdminAuth',
      'id'
    );

    return is_int($admin_id) &&
      $admin_id > 0
      ? $admin_id
      : null;
  }

  /**
   * Gets the direct client IP address reported by the web server.
   *
   * Forwarded headers are intentionally ignored because they are not trusted
   * unless the proxy chain is explicitly configured.
   *
   * @return string|null Valid IPv4/IPv6 address or null.
   */
  protected function getClientIp(): ?string {
    $ip = $_SERVER['REMOTE_ADDR']
      ?? null;

    if (
      !is_string($ip) ||
      filter_var(
        $ip,
        FILTER_VALIDATE_IP
      ) === false
    ) {
      return null;
    }

    return $ip;
  }

  /**
   * Gets the request User-Agent truncated to the database field limit.
   *
   * @return string|null User-Agent or null when unavailable.
   */
  protected function getUserAgent(): ?string {
    $user_agent = $_SERVER['HTTP_USER_AGENT']
      ?? null;

    if (
      !is_string($user_agent) ||
      $user_agent === ''
    ) {
      return null;
    }

    return substr(
      $user_agent,
      0,
      255
    );
  }

  /**
   * Persists an audit model.
   *
   * Kept as a separate method so audit behavior can be unit tested without a
   * database connection.
   *
   * @param AuditLog $audit_log Audit event to persist.
   *
   * @return bool True when persistence succeeds.
   */
  protected function persist(
    AuditLog $audit_log
  ): bool {
    return $audit_log->save();
  }

  /**
   * Writes an audit failure to the regular application log when available.
   *
   * @param string $message Error message.
   *
   * @return void
   */
  private function logFailure(
    string $message
  ): void {
    $this->log?->error(
      $message
    );
  }
}
