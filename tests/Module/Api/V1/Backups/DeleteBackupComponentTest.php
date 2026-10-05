<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Module\Api\V1\Backups;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Osumi\OsumiFramework\App\DTO\InstallationBackupDTO;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Module\Api\V1\Backups\DeleteBackup\DeleteBackupComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;

final class DeleteBackupComponentTest extends TestCase {
  private bool $core_existed = false;
  private mixed $previous_core = null;

  /**
   * Creates isolated framework state for each test.
   *
   * @return void
   */
  protected function setUp(): void {
    parent::setUp();

    $this->core_existed = array_key_exists(
      'core',
      $GLOBALS
    );

    if ($this->core_existed) {
      $this->previous_core =
        $GLOBALS['core'];
    }

    $GLOBALS['core'] =
      new OCore();

    OMiddleware::reset();
  }

  /**
   * Restores framework state after each test.
   *
   * @return void
   */
  protected function tearDown(): void {
    OMiddleware::reset();

    if ($this->core_existed) {
      $GLOBALS['core'] =
        $this->previous_core;
    }
    else {
      unset(
        $GLOBALS['core']
      );
    }

    parent::tearDown();
  }

  /**
   * Verifies that a missing public identifier is rejected.
   *
   * @return void
   */
  public function testRunRejectsMissingPublicId(): void {
    $backup_service =
      $this->createBackupServiceMock();

    $installation_service =
      $this->createInstallationServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $backup_service
      ->expects(
        self::never()
      )
      ->method('getByPublicId');

    $backup_service
      ->expects(
        self::never()
      )
      ->method('delete');

    $installation_service
      ->expects(
        self::never()
      )
      ->method('getById');

    $audit_service
      ->expects(
        self::never()
      )
      ->method('recordInstallationAction');

    $component =
      $this->createComponent(
        $backup_service,
        $installation_service,
        $audit_service
      );

    $component->run(
      $this->createDto(
        null
      )
    );

    self::assertSame(
      'error',
      $component->status
    );

    self::assertSame(
      'Missing required fields.',
      $component->message
    );

    self::assertSame(
      400,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that backups from another installation are hidden.
   *
   * @return void
   */
  public function testRunReturnsNotFoundForForeignBackup(): void {
    $backup =
      $this->createBackup();

    $backup->id_installation = 99;

    $backup_service =
      $this->createBackupServiceMock();

    $installation_service =
      $this->createInstallationServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $backup_service
      ->expects(
        self::once()
      )
      ->method('getByPublicId')
      ->with(
        'test-public-id'
      )
      ->willReturn(
        $backup
      );

    $backup_service
      ->expects(
        self::never()
      )
      ->method('delete');

    $installation_service
      ->expects(
        self::never()
      )
      ->method('getById');

    $audit_service
      ->expects(
        self::never()
      )
      ->method('recordInstallationAction');

    $component =
      $this->createComponent(
        $backup_service,
        $installation_service,
        $audit_service
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      'Backup not found.',
      $component->message
    );

    self::assertSame(
      404,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that deletion failures return HTTP 500 without audit.
   *
   * @return void
   */
  public function testRunReturnsServerErrorWhenDeleteFails(): void {
    $backup =
      $this->createBackup();

    $installation =
      $this->createInstallation();

    $backup_service =
      $this->createBackupServiceMock();

    $installation_service =
      $this->createInstallationServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $backup_service
      ->expects(
        self::once()
      )
      ->method('getByPublicId')
      ->with(
        'test-public-id'
      )
      ->willReturn(
        $backup
      );

    $backup_service
      ->expects(
        self::once()
      )
      ->method('delete')
      ->with(
        $backup
      )
      ->willThrowException(
        new RuntimeException(
          'Delete failed.'
        )
      );

    $installation_service
      ->expects(
        self::once()
      )
      ->method('getById')
      ->with(10)
      ->willReturn(
        $installation
      );

    $audit_service
      ->expects(
        self::never()
      )
      ->method('recordInstallationAction');

    $component =
      $this->createComponent(
        $backup_service,
        $installation_service,
        $audit_service
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      'Backup could not be deleted.',
      $component->message
    );

    self::assertSame(
      500,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that an owned backup is deleted and audited.
   *
   * @return void
   */
  public function testRunDeletesOwnedBackup(): void {
    $backup =
      $this->createBackup();

    $installation =
      $this->createInstallation();

    $backup_service =
      $this->createBackupServiceMock();

    $installation_service =
      $this->createInstallationServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $backup_service
      ->expects(
        self::once()
      )
      ->method('getByPublicId')
      ->with(
        'test-public-id'
      )
      ->willReturn(
        $backup
      );

    $backup_service
      ->expects(
        self::once()
      )
      ->method('delete')
      ->with(
        $backup
      );

    $installation_service
      ->expects(
        self::once()
      )
      ->method('getById')
      ->with(10)
      ->willReturn(
        $installation
      );

    $audit_service
      ->expects(
        self::once()
      )
      ->method('recordInstallationAction')
      ->with(
        $installation,
        AuditLogService::ACTION_BACKUP_DELETE,
        AuditLogService::ENTITY_BACKUP,
        'test-public-id',
        [
          'backupId' =>
            'test-backup-id',
          'originalFilename' =>
            'backup.otpv'
        ]
      );

    $component =
      $this->createComponent(
        $backup_service,
        $installation_service,
        $audit_service
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      'ok',
      $component->status
    );

    self::assertSame(
      'test-public-id',
      $component->public_id
    );

    self::assertSame(
      '',
      $component->message
    );

    self::assertSame(
      200,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Creates an authenticated backup DTO.
   *
   * @param string|null $public_id Backup public identifier.
   *
   * @return InstallationBackupDTO Prepared DTO.
   */
  private function createDto(
    ?string $public_id = 'test-public-id'
  ): InstallationBackupDTO {
    $reflection =
      new ReflectionClass(
        InstallationBackupDTO::class
      );

    $dto =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      InstallationBackupDTO::class,
      $dto
    );

    $dto->publicId =
      $public_id;

    $dto->installationId = 10;

    return $dto;
  }

  /**
   * Creates a delete component with controlled dependencies.
   *
   * @param BackupService       $backup_service       Backup service.
   * @param InstallationService $installation_service Installation service.
   * @param AuditLogService     $audit_service        Audit service.
   *
   * @return DeleteBackupComponent Prepared component.
   */
  private function createComponent(
    BackupService $backup_service,
    InstallationService $installation_service,
    AuditLogService $audit_service
  ): DeleteBackupComponent {
    $reflection =
      new ReflectionClass(
        DeleteBackupComponent::class
      );

    $component =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      DeleteBackupComponent::class,
      $component
    );

    $this->setProperty(
      $component,
      'backup_service',
      $backup_service
    );

    $this->setProperty(
      $component,
      'installation_service',
      $installation_service
    );

    $this->setProperty(
      $component,
      'audit_log_service',
      $audit_service
    );

    return $component;
  }

  /**
   * Sets a private component dependency.
   *
   * @param DeleteBackupComponent $component Component instance.
   * @param string                $property  Property name.
   * @param object                $value     Property value.
   *
   * @return void
   */
  private function setProperty(
    DeleteBackupComponent $component,
    string $property,
    object $value
  ): void {
    $reflection =
      new ReflectionProperty(
        DeleteBackupComponent::class,
        $property
      );

    $reflection->setValue(
      $component,
      $value
    );
  }

  /**
   * Creates an installation fixture.
   *
   * @return Installation Installation fixture.
   */
  private function createInstallation(): Installation {
    $installation =
      new Installation();

    $installation->id = 10;
    $installation->public_id =
      'installation-public-id';

    return $installation;
  }

  /**
   * Creates a backup fixture.
   *
   * @return Backup Backup fixture.
   */
  private function createBackup(): Backup {
    $backup =
      new Backup();

    $backup->public_id =
      'test-public-id';

    $backup->id_installation = 10;

    $backup->backup_id =
      'test-backup-id';

    $backup->original_filename =
      'backup.otpv';

    return $backup;
  }

  /**
   * Creates a backup service mock.
   *
   * @return BackupService&MockObject Service mock.
   */
  private function createBackupServiceMock(): BackupService&MockObject {
    return $this->createMock(
      BackupService::class
    );
  }

  /**
   * Creates an installation service mock.
   *
   * @return InstallationService&MockObject Service mock.
   */
  private function createInstallationServiceMock(): InstallationService&MockObject {
    return $this->createMock(
      InstallationService::class
    );
  }

  /**
   * Creates an audit service mock.
   *
   * @return AuditLogService&MockObject Service mock.
   */
  private function createAuditServiceMock(): AuditLogService&MockObject {
    return $this->createMock(
      AuditLogService::class
    );
  }
}
