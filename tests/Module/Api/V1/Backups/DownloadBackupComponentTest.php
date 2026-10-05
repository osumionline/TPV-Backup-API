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
use Osumi\OsumiFramework\App\Module\Api\V1\Backups\DownloadBackup\DownloadBackupComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\OStreamResponse;

final class DownloadBackupComponentTest extends TestCase {
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

    $result =
      $component->run(
        $this->createDto(
          null
        )
      );

    self::assertNull(
      $result
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
   * Verifies that backups owned by another installation are hidden.
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
      ->method('openReadStream');

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

    $result =
      $component->run(
        $this->createDto()
      );

    self::assertNull(
      $result
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
   * Verifies that storage failures return HTTP 500.
   *
   * @return void
   */
  public function testRunReturnsServerErrorWhenBackupCannotBeOpened(): void {
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
      ->method('openReadStream')
      ->with(
        $backup
      )
      ->willThrowException(
        new RuntimeException(
          'Stored file does not exist.'
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

    $result =
      $component->run(
        $this->createDto()
      );

    self::assertNull(
      $result
    );

    self::assertSame(
      'Backup file could not be opened.',
      $component->message
    );

    self::assertSame(
      500,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that an owned backup is streamed and audited.
   *
   * @return void
   */
  public function testRunReturnsStreamForOwnedBackup(): void {
    $contents =
      'remote-otpv-download';

    $stream = fopen(
      'php://temp',
      'w+b'
    );

    self::assertIsResource(
      $stream
    );

    fwrite(
      $stream,
      $contents
    );

    rewind(
      $stream
    );

    $backup =
      $this->createBackup(
        strlen(
          $contents
        ),
        'Copia TPV ñ 2026.otpv'
      );

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
      ->method('openReadStream')
      ->with(
        $backup
      )
      ->willReturn(
        $stream
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
        AuditLogService::ACTION_BACKUP_DOWNLOAD,
        AuditLogService::ENTITY_BACKUP,
        'test-public-id',
        [
          'backupId' =>
            'test-backup-id',
          'originalFilename' =>
            'Copia TPV ñ 2026.otpv',
          'sizeBytes' =>
            strlen(
              $contents
            )
        ]
      );

    $component =
      $this->createComponent(
        $backup_service,
        $installation_service,
        $audit_service
      );

    $result =
      $component->run(
        $this->createDto()
      );

    self::assertInstanceOf(
      OStreamResponse::class,
      $result
    );

    self::assertSame(
      200,
      $result->getStatusCode()
    );

    self::assertSame(
      [
        'Content-Type' =>
          'application/octet-stream',

        'Content-Length' =>
          strval(
            strlen(
              $contents
            )
          ),

        'Content-Disposition' =>
          'attachment; filename="backup.otpv"; filename*=UTF-8\'\''
          . 'Copia%20TPV%20%C3%B1%202026.otpv',

        'Access-Control-Expose-Headers' =>
          'Content-Disposition'
      ],
      $result->getHeaders()
    );

    self::assertSame(
      $contents,
      stream_get_contents(
        $result->getStream()
      )
    );

    $result->close();

    self::assertFalse(
      $result->isOpen()
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
   * Creates a download component with controlled dependencies.
   *
   * @param BackupService             $backup_service       Backup service.
   * @param InstallationService       $installation_service Installation service.
   * @param AuditLogService           $audit_service        Audit service.
   *
   * @return DownloadBackupComponent Prepared component.
   */
  private function createComponent(
    BackupService $backup_service,
    InstallationService $installation_service,
    AuditLogService $audit_service
  ): DownloadBackupComponent {
    $reflection =
      new ReflectionClass(
        DownloadBackupComponent::class
      );

    $component =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      DownloadBackupComponent::class,
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
   * @param DownloadBackupComponent $component Component instance.
   * @param string                  $property  Property name.
   * @param object                  $value     Property value.
   *
   * @return void
   */
  private function setProperty(
    DownloadBackupComponent $component,
    string $property,
    object $value
  ): void {
    $reflection =
      new ReflectionProperty(
        DownloadBackupComponent::class,
        $property
      );

    $reflection->setValue(
      $component,
      $value
    );
  }

  /**
   * Creates a persisted installation fixture.
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
   * Creates backup metadata for download tests.
   *
   * @param int    $size_bytes        Backup size.
   * @param string $original_filename Original filename.
   *
   * @return Backup Backup fixture.
   */
  private function createBackup(
    int $size_bytes = 1024,
    string $original_filename = 'backup.otpv'
  ): Backup {
    $backup = new Backup();

    $backup->public_id =
      'test-public-id';

    $backup->id_installation = 10;

    $backup->backup_id =
      'test-backup-id';

    $backup->storage_key =
      'installations/test/backup.otpv';

    $backup->size_bytes =
      $size_bytes;

    $backup->original_filename =
      $original_filename;

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
