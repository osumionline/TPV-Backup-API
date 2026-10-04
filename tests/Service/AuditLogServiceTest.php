<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Osumi\OsumiFramework\App\Model\AuditLog;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Service\AuditLogService;

final class AuditLogServiceTest extends TestCase {
  /**
   * Verifies that an administrator event contains actor and request metadata.
   *
   * @return void
   */
  public function testRecordAdminActionPersistsAuditEvent(): void {
    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('getAuthenticatedAdminId')
      ->willReturn(42);

    $service
      ->expects(
        self::once()
      )
      ->method('getClientIp')
      ->willReturn(
        '203.0.113.10'
      );

    $service
      ->expects(
        self::once()
      )
      ->method('getUserAgent')
      ->willReturn(
        'TPV Backup Test'
      );

    $service
      ->expects(
        self::once()
      )
      ->method('persist')
      ->with(
        self::callback(
          static function(
            AuditLog $audit_log
          ): bool {
            self::assertSame(
              'admin',
              $audit_log->actor_type
            );

            self::assertSame(
              42,
              $audit_log->id_admin_user
            );

            self::assertNull(
              $audit_log->id_installation
            );

            self::assertSame(
              AuditLogService::ACTION_BACKUP_DOWNLOAD,
              $audit_log->action
            );

            self::assertSame(
              AuditLogService::ENTITY_BACKUP,
              $audit_log->entity_type
            );

            self::assertSame(
              'backup-public-id',
              $audit_log->entity_public_id
            );

            self::assertSame(
              [
                'filename' => 'backup.otpv'
              ],
              json_decode(
                $audit_log->data ?? '',
                true
              )
            );

            self::assertSame(
              '203.0.113.10',
              $audit_log->ip
            );

            self::assertSame(
              'TPV Backup Test',
              $audit_log->user_agent
            );

            return true;
          }
        )
      )
      ->willReturn(true);

    self::assertTrue(
      $service->recordAdminAction(
        AuditLogService::ACTION_BACKUP_DOWNLOAD,
        AuditLogService::ENTITY_BACKUP,
        'backup-public-id',
        [
          'filename' => 'backup.otpv'
        ]
      )
    );
  }

  /**
   * Verifies that an administrator event is ignored without auth context.
   *
   * @return void
   */
  public function testRecordAdminActionFailsWithoutAdministrator(): void {
    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('getAuthenticatedAdminId')
      ->willReturn(null);

    $service
      ->expects(
        self::never()
      )
      ->method('persist');

    self::assertFalse(
      $service->recordAdminAction(
        AuditLogService::ACTION_BACKUP_DELETE,
        AuditLogService::ENTITY_BACKUP,
        'backup-public-id'
      )
    );
  }

  /**
   * Verifies that a system event has no user or request actor metadata.
   *
   * @return void
   */
  public function testRecordSystemActionPersistsSystemActor(): void {
    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('persist')
      ->with(
        self::callback(
          static function(
            AuditLog $audit_log
          ): bool {
            self::assertSame(
              'system',
              $audit_log->actor_type
            );

            self::assertNull(
              $audit_log->id_admin_user
            );

            self::assertNull(
              $audit_log->id_installation
            );

            self::assertNull(
              $audit_log->ip
            );

            self::assertNull(
              $audit_log->user_agent
            );

            return true;
          }
        )
      )
      ->willReturn(true);

    self::assertTrue(
      $service->recordSystemAction(
        AuditLogService::ACTION_BACKUP_RETENTION_DELETE,
        AuditLogService::ENTITY_BACKUP,
        'backup-public-id'
      )
    );
  }

  /**
   * Verifies that an installation event records the installation actor.
   *
   * @return void
   */
  public function testRecordInstallationActionPersistsInstallationActor(): void {
    $installation = new Installation();

    $installation->id = 15;

    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('getClientIp')
      ->willReturn(null);

    $service
      ->expects(
        self::once()
      )
      ->method('getUserAgent')
      ->willReturn(null);

    $service
      ->expects(
        self::once()
      )
      ->method('persist')
      ->with(
        self::callback(
          static function(
            AuditLog $audit_log
          ): bool {
            self::assertSame(
              'installation',
              $audit_log->actor_type
            );

            self::assertNull(
              $audit_log->id_admin_user
            );

            self::assertSame(
              15,
              $audit_log->id_installation
            );

            return true;
          }
        )
      )
      ->willReturn(true);

    self::assertTrue(
      $service->recordInstallationAction(
        $installation,
        'backup.create',
        AuditLogService::ENTITY_BACKUP,
        'backup-public-id'
      )
    );
  }

  /**
   * Creates an AuditLogService mock without requiring database persistence.
   *
   * @return AuditLogService&MockObject Audit service mock.
   */
  private function createServiceMock(): AuditLogService&MockObject {
    return $this
      ->getMockBuilder(
        AuditLogService::class
      )
      ->onlyMethods([
        'getAuthenticatedAdminId',
        'getClientIp',
        'getUserAgent',
        'persist'
      ])
      ->getMock();
  }
}
