<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Service\BackupService;

final class BackupServiceRetentionTest extends TestCase {
  /**
   * Verifies that retention does nothing while the configured limit is not exceeded.
   *
   * @return void
   */
  public function testRetentionKeepsBackupsWithinLimit(): void {
    $installation = $this->createInstallation();

    $backups = [
      $this->createBackup(
        1,
        '2026-10-01 10:00:00'
      ),
      $this->createBackup(
        2,
        '2026-10-02 10:00:00'
      ),
      $this->createBackup(
        3,
        '2026-10-03 10:00:00'
      ),
      $this->createBackup(
        4,
        '2026-10-04 10:00:00'
      ),
      $this->createBackup(
        5,
        '2026-10-05 10:00:00'
      ),
      $this->createBackup(
        6,
        '2026-10-06 10:00:00'
      )
    ];

    $service = $this->createServiceMock();

    $service
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

    $service
      ->expects(
        self::never()
      )
      ->method('delete');

    $service->enforceRetention(
      $installation
    );
  }

  /**
   * Verifies that the oldest backup is deleted when the limit is exceeded.
   *
   * @return void
   */
  public function testRetentionDeletesOldestBackup(): void {
    $installation = $this->createInstallation();

    $backups = [
      $this->createBackup(
        4,
        '2026-10-04 10:00:00'
      ),
      $this->createBackup(
        7,
        '2026-10-07 10:00:00'
      ),
      $this->createBackup(
        2,
        '2026-10-02 10:00:00'
      ),
      $this->createBackup(
        5,
        '2026-10-05 10:00:00'
      ),
      $this->createBackup(
        1,
        '2026-10-01 10:00:00'
      ),
      $this->createBackup(
        6,
        '2026-10-06 10:00:00'
      ),
      $this->createBackup(
        3,
        '2026-10-03 10:00:00'
      )
    ];

    $deleted_ids = [];

    $service = $this->createServiceMock();

    $service
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

    $service
      ->expects(
        self::once()
      )
      ->method('delete')
      ->willReturnCallback(
        static function(
          Backup $backup
        ) use (
          &$deleted_ids
        ): void {
          $deleted_ids[] = $backup->id;
        }
      );

    $service->enforceRetention(
      $installation
    );

    self::assertSame(
      [1],
      $deleted_ids
    );
  }

  /**
   * Verifies that every excess backup is removed from oldest to newest.
   *
   * @return void
   */
  public function testRetentionDeletesAllExcessBackups(): void {
    $installation = $this->createInstallation();

    $backups = [
      $this->createBackup(
        8,
        '2026-10-08 10:00:00'
      ),
      $this->createBackup(
        3,
        '2026-10-03 10:00:00'
      ),
      $this->createBackup(
        1,
        '2026-10-01 10:00:00'
      ),
      $this->createBackup(
        6,
        '2026-10-06 10:00:00'
      ),
      $this->createBackup(
        2,
        '2026-10-02 10:00:00'
      ),
      $this->createBackup(
        7,
        '2026-10-07 10:00:00'
      ),
      $this->createBackup(
        5,
        '2026-10-05 10:00:00'
      ),
      $this->createBackup(
        4,
        '2026-10-04 10:00:00'
      )
    ];

    $deleted_ids = [];

    $service = $this->createServiceMock();

    $service
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

    $service
      ->expects(
        self::exactly(2)
      )
      ->method('delete')
      ->willReturnCallback(
        static function(
          Backup $backup
        ) use (
          &$deleted_ids
        ): void {
          $deleted_ids[] = $backup->id;
        }
      );

    $service->enforceRetention(
      $installation
    );

    self::assertSame(
      [
        1,
        2
      ],
      $deleted_ids
    );
  }

  /**
   * Verifies that the internal identifier breaks equal creation-date ties.
   *
   * @return void
   */
  public function testRetentionUsesIdAsDateTieBreaker(): void {
    $installation = $this->createInstallation();

    $backups = [
      $this->createBackup(
        7,
        '2026-10-02 10:00:00'
      ),
      $this->createBackup(
        2,
        '2026-10-01 10:00:00'
      ),
      $this->createBackup(
        1,
        '2026-10-01 10:00:00'
      ),
      $this->createBackup(
        3,
        '2026-10-03 10:00:00'
      ),
      $this->createBackup(
        4,
        '2026-10-04 10:00:00'
      ),
      $this->createBackup(
        5,
        '2026-10-05 10:00:00'
      ),
      $this->createBackup(
        6,
        '2026-10-06 10:00:00'
      )
    ];

    $deleted_ids = [];

    $service = $this->createServiceMock();

    $service
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

    $service
      ->expects(
        self::once()
      )
      ->method('delete')
      ->willReturnCallback(
        static function(
          Backup $backup
        ) use (
          &$deleted_ids
        ): void {
          $deleted_ids[] = $backup->id;
        }
      );

    $service->enforceRetention(
      $installation
    );

    self::assertSame(
      [1],
      $deleted_ids
    );
  }

  /**
   * Verifies that retention exposes deletion failures to its caller.
   *
   * The create flow catches this failure separately so a previously persisted
   * backup is not invalidated.
   *
   * @return void
   */
  public function testRetentionPropagatesDeletionFailure(): void {
    $installation = $this->createInstallation();

    $backups = [];

    for (
      $id = 1;
      $id <= 7;
      $id++
    ) {
      $backups[] = $this->createBackup(
        $id,
        sprintf(
          '2026-10-%02d 10:00:00',
          $id
        )
      );
    }

    $service = $this->createServiceMock();

    $service
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

    $service
      ->expects(
        self::once()
      )
      ->method('delete')
      ->willThrowException(
        new RuntimeException(
          'Delete failed.'
        )
      );

    $this->expectException(
      RuntimeException::class
    );

    $this->expectExceptionMessage(
      'Delete failed.'
    );

    $service->enforceRetention(
      $installation
    );
  }

  /**
   * Creates a persisted-looking installation for retention tests.
   *
   * @return Installation Test installation.
   */
  private function createInstallation(): Installation {
    $installation = new Installation();

    $installation->id = 10;
    $installation->public_id =
      '8a8758ea-5052-4c71-bd77-679509addead';

    return $installation;
  }

  /**
   * Creates backup metadata for retention tests.
   *
   * @param int    $id                Internal backup identifier.
   * @param string $created_at_client Client creation timestamp.
   *
   * @return Backup Test backup.
   */
  private function createBackup(
    int $id,
    string $created_at_client
  ): Backup {
    $backup = new Backup();

    $backup->id = $id;
    $backup->created_at_client =
      $created_at_client;

    return $backup;
  }

  /**
   * Creates a BackupService mock without initializing real dependencies.
   *
   * @return BackupService&MockObject Backup service mock.
   */
  private function createServiceMock(): BackupService&MockObject {
    return $this
      ->getMockBuilder(
        BackupService::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getByInstallation',
        'delete'
      ])
      ->getMock();
  }
}
