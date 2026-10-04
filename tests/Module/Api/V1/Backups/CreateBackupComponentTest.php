<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Module\Api\V1\Backups;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Osumi\OsumiFramework\App\DTO\CreateBackupDTO;
use Osumi\OsumiFramework\App\Exception\BackupConflictException;
use Osumi\OsumiFramework\App\Exception\InvalidOtpvPackageException;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Module\Api\V1\Backups\CreateBackup\CreateBackupComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;

final class CreateBackupComponentTest extends TestCase {
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

    $GLOBALS['core'] = new OCore();

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
   * Verifies that an expired subscription cannot upload.
   *
   * @return void
   */
  public function testRunRejectsUploadWhenNotAllowed(): void {
    $component = $this->createComponent();

    $component->run(
      $this->createDto(
        false
      )
    );

    self::assertSame(
      403,
      OMiddleware::getStatusCode()
    );

    self::assertSame(
      'Backup upload is not allowed for this subscription.',
      $component->message
    );
  }

  /**
   * Verifies that oversized backups are rejected.
   *
   * @return void
   */
  public function testRunRejectsOversizedBackup(): void {
    $component = $this->createComponent();

    $component
      ->expects(
        self::once()
      )
      ->method('isUploadedFile')
      ->willReturn(true);

    $component
      ->expects(
        self::once()
      )
      ->method('getFileSize')
      ->willReturn(
        8589934593
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      413,
      OMiddleware::getStatusCode()
    );

    self::assertSame(
      'Backup file is too large.',
      $component->message
    );
  }

  /**
   * Verifies that invalid OTPV packages are rejected.
   *
   * @return void
   */
  public function testRunRejectsInvalidOtpvPackage(): void {
    $installation =
      $this->createInstallation();

    $installation_service =
      $this->createInstallationServiceMock();

    $backup_service =
      $this->createBackupServiceMock();

    $component = $this->createComponent(
      $installation_service,
      $backup_service
    );

    $component
      ->expects(
        self::once()
      )
      ->method('isUploadedFile')
      ->willReturn(true);

    $component
      ->expects(
        self::once()
      )
      ->method('getFileSize')
      ->willReturn(1024);

    $installation_service
      ->expects(
        self::once()
      )
      ->method('getById')
      ->with(10)
      ->willReturn(
        $installation
      );

    $backup_service
      ->expects(
        self::once()
      )
      ->method('createFromFile')
      ->willThrowException(
        new InvalidOtpvPackageException(
          'Invalid package.'
        )
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      422,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that conflicting backup identifiers return HTTP 409.
   *
   * @return void
   */
  public function testRunRejectsBackupConflict(): void {
    $installation =
      $this->createInstallation();

    $installation_service =
      $this->createInstallationServiceMock();

    $backup_service =
      $this->createBackupServiceMock();

    $component = $this->createComponent(
      $installation_service,
      $backup_service
    );

    $component
      ->expects(
        self::once()
      )
      ->method('isUploadedFile')
      ->willReturn(true);

    $component
      ->expects(
        self::once()
      )
      ->method('getFileSize')
      ->willReturn(1024);

    $installation_service
      ->method('getById')
      ->willReturn(
        $installation
      );

    $backup_service
      ->method('createFromFile')
      ->willThrowException(
        new BackupConflictException(
          'Conflict.'
        )
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      409,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that a valid upload creates and audits the backup.
   *
   * @return void
   */
  public function testRunCreatesBackup(): void {
    $installation =
      $this->createInstallation();

    $backup = new Backup();

    $backup->public_id =
      'backup-public-id';

    $backup->backup_id =
      '550e8400-e29b-41d4-a716-446655440000';

    $backup->created_at_client =
      '2026-10-04 20:00:00';

    $backup->original_filename =
      'backup.otpv';

    $backup->size_bytes = 1024;

    $backup->sha256 = str_repeat(
      'a',
      64
    );

    $installation_service =
      $this->createInstallationServiceMock();

    $backup_service =
      $this->createBackupServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $component = $this->createComponent(
      $installation_service,
      $backup_service,
      $audit_service
    );

    $component
      ->expects(
        self::once()
      )
      ->method('isUploadedFile')
      ->with('/tmp/upload.otpv')
      ->willReturn(true);

    $component
      ->expects(
        self::once()
      )
      ->method('getFileSize')
      ->with('/tmp/upload.otpv')
      ->willReturn(1024);

    $installation_service
      ->expects(
        self::once()
      )
      ->method('getById')
      ->with(10)
      ->willReturn(
        $installation
      );

    $backup_service
      ->expects(
        self::once()
      )
      ->method('createFromFile')
      ->with(
        $installation,
        '/tmp/upload.otpv',
        'backup.otpv'
      )
      ->willReturn(
        $backup
      );

    $audit_service
      ->expects(
        self::once()
      )
      ->method('recordInstallationAction')
      ->with(
        $installation,
        AuditLogService::ACTION_BACKUP_CREATE,
        AuditLogService::ENTITY_BACKUP,
        'backup-public-id',
        [
          'backupId' =>
            '550e8400-e29b-41d4-a716-446655440000',
          'originalFilename' =>
            'backup.otpv',
          'sizeBytes' => 1024
        ]
      )
      ->willReturn(true);

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      'ok',
      $component->status
    );

    self::assertSame(
      'backup-public-id',
      $component->public_id
    );

    self::assertSame(
      1024,
      $component->size_bytes
    );

    self::assertSame(
      200,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Creates an upload DTO without requiring middleware execution.
   *
   * @param bool $can_upload Whether uploads are allowed.
   *
   * @return CreateBackupDTO Prepared DTO.
   */
  private function createDto(
    bool $can_upload = true
  ): CreateBackupDTO {
    $reflection = new ReflectionClass(
      CreateBackupDTO::class
    );

    $dto =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      CreateBackupDTO::class,
      $dto
    );

    $dto->file = [
      'name' => 'backup.otpv',
      'type' => 'application/octet-stream',
      'tmp_name' => '/tmp/upload.otpv',
      'error' => UPLOAD_ERR_OK,
      'size' => 1024
    ];

    $dto->installationId = 10;
    $dto->canUpload = $can_upload;

    return $dto;
  }

  /**
   * Creates a component with controlled dependencies.
   *
   * @param InstallationService|null $installation_service Installation service.
   * @param BackupService|null       $backup_service       Backup service.
   * @param AuditLogService|null     $audit_service        Audit service.
   *
   * @return CreateBackupComponent&MockObject Prepared component.
   */
  private function createComponent(
    ?InstallationService $installation_service = null,
    ?BackupService $backup_service = null,
    ?AuditLogService $audit_service = null
  ): CreateBackupComponent&MockObject {
    $component = $this
      ->getMockBuilder(
        CreateBackupComponent::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'isUploadedFile',
        'getFileSize'
      ])
      ->getMock();

    $this->setProperty(
      $component,
      'installation_service',
      $installation_service
        ?? $this->createInstallationServiceMock()
    );

    $this->setProperty(
      $component,
      'backup_service',
      $backup_service
        ?? $this->createBackupServiceMock()
    );

    $this->setProperty(
      $component,
      'audit_log_service',
      $audit_service
        ?? $this->createAuditServiceMock()
    );

    return $component;
  }

  /**
   * Sets a private component property for isolated testing.
   *
   * @param CreateBackupComponent $component Component instance.
   * @param string                $property  Property name.
   * @param object                $value     Property value.
   *
   * @return void
   */
  private function setProperty(
    CreateBackupComponent $component,
    string $property,
    object $value
  ): void {
    $reflection = new ReflectionProperty(
      CreateBackupComponent::class,
      $property
    );

    $reflection->setValue(
      $component,
      $value
    );
  }

  /**
   * Creates an active installation.
   *
   * @return Installation Test installation.
   */
  private function createInstallation(): Installation {
    $installation = new Installation();

    $installation->id = 10;
    $installation->public_id =
      'installation-public-id';
    $installation->active = true;

    return $installation;
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
