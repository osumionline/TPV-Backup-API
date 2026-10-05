<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Module\Api\V1\Backups;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Osumi\OsumiFramework\App\Component\Api\V1\RemoteBackupList\RemoteBackupListComponent;
use Osumi\OsumiFramework\App\DTO\InstallationBackupsDTO;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Module\Api\V1\Backups\GetBackups\GetBackupsComponent;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;

final class GetBackupsComponentTest extends TestCase {
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
   * Verifies that only backups owned by the authenticated installation are loaded.
   *
   * @return void
   */
  public function testRunLoadsAuthenticatedInstallationBackups(): void {
    $installation =
      $this->createInstallation();

    $first_backup = new Backup();
    $first_backup->public_id =
      'first-backup-public-id';

    $second_backup = new Backup();
    $second_backup->public_id =
      'second-backup-public-id';

    $backups = [
      $first_backup,
      $second_backup
    ];

    $installation_service =
      $this->createInstallationServiceMock();

    $backup_service =
      $this->createBackupServiceMock();

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
      ->method('getByInstallation')
      ->with(
        $installation
      )
      ->willReturn(
        $backups
      );

    $component =
      $this->createComponent(
        $installation_service,
        $backup_service
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      'ok',
      $component->status
    );

    self::assertSame(
      '',
      $component->message
    );

    self::assertNotNull(
      $component->list
    );

    self::assertSame(
      $backups,
      $component->list->list
    );

    self::assertSame(
      200,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that an unavailable authenticated installation is rejected.
   *
   * @return void
   */
  public function testRunRejectsUnavailableInstallation(): void {
    $installation_service =
      $this->createInstallationServiceMock();

    $backup_service =
      $this->createBackupServiceMock();

    $installation_service
      ->expects(
        self::once()
      )
      ->method('getById')
      ->with(10)
      ->willReturn(null);

    $backup_service
      ->expects(
        self::never()
      )
      ->method('getByInstallation');

    $component =
      $this->createComponent(
        $installation_service,
        $backup_service
      );

    $component->run(
      $this->createDto()
    );

    self::assertSame(
      'error',
      $component->status
    );

    self::assertSame(
      'Authenticated installation is not available.',
      $component->message
    );

    self::assertSame(
      403,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Creates an authenticated installation DTO.
   *
   * @return InstallationBackupsDTO Prepared DTO.
   */
  private function createDto(): InstallationBackupsDTO {
    $reflection =
      new ReflectionClass(
        InstallationBackupsDTO::class
      );

    $dto =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      InstallationBackupsDTO::class,
      $dto
    );

    $dto->installationId = 10;

    return $dto;
  }

  /**
   * Creates a component with controlled dependencies.
   *
   * @param InstallationService $installation_service Installation service.
   * @param BackupService       $backup_service       Backup service.
   *
   * @return GetBackupsComponent Prepared component.
   */
  private function createComponent(
    InstallationService $installation_service,
    BackupService $backup_service
  ): GetBackupsComponent {
    $reflection =
      new ReflectionClass(
        GetBackupsComponent::class
      );

    $component =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      GetBackupsComponent::class,
      $component
    );

    $list_reflection =
      new ReflectionClass(
        RemoteBackupListComponent::class
      );

    $list =
      $list_reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      RemoteBackupListComponent::class,
      $list
    );

    $component->list = $list;

    $this->setProperty(
      $component,
      'installation_service',
      $installation_service
    );

    $this->setProperty(
      $component,
      'backup_service',
      $backup_service
    );

    return $component;
  }

  /**
   * Sets a private component dependency.
   *
   * @param GetBackupsComponent $component Component instance.
   * @param string              $property  Property name.
   * @param object              $value     Property value.
   *
   * @return void
   */
  private function setProperty(
    GetBackupsComponent $component,
    string $property,
    object $value
  ): void {
    $reflection =
      new ReflectionProperty(
        GetBackupsComponent::class,
        $property
      );

    $reflection->setValue(
      $component,
      $value
    );
  }

  /**
   * Creates the test installation.
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
}
