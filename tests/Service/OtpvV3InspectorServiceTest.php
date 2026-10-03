<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use ZipArchive;
use PHPUnit\Framework\TestCase;
use Osumi\OsumiFramework\App\Exception\InvalidOtpvPackageException;
use Osumi\OsumiFramework\App\Service\OtpvV3InspectorService;

final class OtpvV3InspectorServiceTest extends TestCase {
  private string $temporary_directory;

  /**
   * Creates an isolated temporary directory for each test.
   *
   * @return void
   */
  protected function setUp(): void {
    parent::setUp();

    $this->temporary_directory = sys_get_temp_dir()
      . DIRECTORY_SEPARATOR
      . 'tpv-backup-otpv-test-'
      . bin2hex(random_bytes(8));

    if (
      !mkdir(
        $this->temporary_directory,
        0700,
        true
      ) &&
      !is_dir($this->temporary_directory)
    ) {
      self::fail('Temporary test directory could not be created.');
    }
  }

  /**
   * Removes temporary test files.
   *
   * @return void
   */
  protected function tearDown(): void {
    $entries = scandir(
      $this->temporary_directory
    );

    if ($entries !== false) {
      foreach ($entries as $entry) {
        if (
          $entry === '.' ||
          $entry === '..'
        ) {
          continue;
        }

        unlink(
          $this->temporary_directory
            . DIRECTORY_SEPARATOR
            . $entry
        );
      }
    }

    if (is_dir($this->temporary_directory)) {
      rmdir(
        $this->temporary_directory
      );
    }

    parent::tearDown();
  }

  /**
   * Verifies that a structurally valid OTPV v3 package is accepted.
   *
   * @return void
   */
  public function testValidPackageIsAccepted(): void {
    $file_path = $this->createPackage(
      $this->createManifest()
    );

    $service = new OtpvV3InspectorService();

    $result = $service->inspect(
      $file_path
    );

    self::assertSame(
      3,
      $result['formatVersion']
    );

    self::assertSame(
      'Osumi TPV Client',
      $result['application']
    );

    self::assertSame(
      'otpv3-scrypt-aes-256-gcm',
      $result['cryptoSuite']
    );

    self::assertSame(
      '123e4567-e89b-42d3-a456-426614174000',
      $result['backupId']
    );

    self::assertSame(
      filesize($file_path),
      $result['sizeBytes']
    );

    self::assertSame(
      hash_file(
        'sha256',
        $file_path
      ),
      $result['sha256']
    );
  }

  /**
   * Verifies that unsupported format versions are rejected.
   *
   * @return void
   */
  public function testInvalidFormatVersionIsRejected(): void {
    $manifest = $this->createManifest();
    $manifest['formatVersion'] = 2;

    $file_path = $this->createPackage(
      $manifest
    );

    $service = new OtpvV3InspectorService();

    $this->expectException(
      InvalidOtpvPackageException::class
    );

    $service->inspect(
      $file_path
    );
  }

  /**
   * Verifies that unexpected ZIP entries are rejected.
   *
   * @return void
   */
  public function testUnexpectedEntryIsRejected(): void {
    $file_path = $this->createPackage(
      $this->createManifest(),
      true
    );

    $service = new OtpvV3InspectorService();

    $this->expectException(
      InvalidOtpvPackageException::class
    );

    $service->inspect(
      $file_path
    );
  }

  /**
   * Verifies that a compressed encrypted payload is rejected.
   *
   * @return void
   */
  public function testCompressedPayloadIsRejected(): void {
    $file_path = $this->createPackage(
      $this->createManifest(),
      false,
      true
    );

    $service = new OtpvV3InspectorService();

    $this->expectException(
      InvalidOtpvPackageException::class
    );

    $service->inspect(
      $file_path
    );
  }

  /**
   * Verifies that an unsupported crypto suite is rejected.
   *
   * @return void
   */
  public function testUnsupportedCryptoSuiteIsRejected(): void {
    $manifest = $this->createManifest();
    $manifest['cryptoSuite'] = 'otpv-v3-scrypt-aes-256-gcm';

    $file_path = $this->createPackage(
      $manifest
    );

    $service = new OtpvV3InspectorService();

    $this->expectException(
      InvalidOtpvPackageException::class
    );

    $this->expectExceptionMessage(
      'Unsupported OTPV crypto suite.'
    );

    $service->inspect(
      $file_path
    );
  }

  /**
   * Creates a valid OTPV v3 public manifest.
   *
   * @return array<string, mixed> Manifest data.
   */
  private function createManifest(): array {
    return [
      'formatVersion' => 3,
      'application' => 'Osumi TPV Client',
      'applicationVersion' => '1.0.0',
      'databaseSchemaVersion' => 1,
      'backupId' => '123e4567-e89b-42d3-a456-426614174000',
      'createdAt' => '2026-10-03T12:00:00Z',
      'cryptoSuite' => 'otpv3-scrypt-aes-256-gcm',
      'authenticatedData' => base64_encode(
        'otpv-v3'
      ),
      'kdf' => [
        'algorithm' => 'scrypt',
        'salt' => base64_encode(
          str_repeat(
            's',
            32
          )
        ),
        'cost' => 32768,
        'blockSize' => 8,
        'parallelization' => 3,
        'length' => 32
      ],
      'keyWrap' => [
        'algorithm' => 'aes-256-gcm',
        'iv' => base64_encode(
          str_repeat(
            'i',
            12
          )
        ),
        'authTag' => base64_encode(
          str_repeat(
            't',
            16
          )
        ),
        'wrappedDek' => base64_encode(
          str_repeat(
            'd',
            32
          )
        )
      ],
      'payload' => [
        'entry' => 'payload.enc',
        'format' => 'zip',
        'algorithm' => 'aes-256-gcm',
        'iv' => base64_encode(
          str_repeat(
            'p',
            12
          )
        ),
        'authTag' => base64_encode(
          str_repeat(
            'a',
            16
          )
        )
      ]
    ];
  }

  /**
   * Creates an OTPV ZIP package for an inspector test.
   *
   * @param array<string, mixed> $manifest           Manifest to store.
   * @param bool                 $add_unexpected_file Whether an unexpected ZIP entry is added.
   * @param bool                 $compress_payload    Whether payload.enc is compressed.
   *
   * @return string Created package path.
   */
  private function createPackage(
    array $manifest,
    bool $add_unexpected_file = false,
    bool $compress_payload = false
  ): string {
    $file_path = $this->temporary_directory
      . DIRECTORY_SEPARATOR
      . bin2hex(random_bytes(8))
      . '.otpv';

    $zip = new ZipArchive();

    $result = $zip->open(
      $file_path,
      ZipArchive::CREATE | ZipArchive::OVERWRITE
    );

    self::assertTrue(
      $result === true
    );

    $manifest_json = json_encode(
      $manifest,
      JSON_THROW_ON_ERROR
    );

    self::assertTrue(
      $zip->addFromString(
        'manifest.json',
        $manifest_json
      )
    );

    self::assertTrue(
      $zip->addFromString(
        'payload.enc',
        str_repeat(
          'encrypted-payload-',
          128
        )
      )
    );

    self::assertTrue(
      $zip->setCompressionName(
        'payload.enc',
        $compress_payload
          ? ZipArchive::CM_DEFLATE
          : ZipArchive::CM_STORE
      )
    );

    if ($add_unexpected_file) {
      self::assertTrue(
        $zip->addFromString(
          'unexpected.txt',
          'unexpected'
        )
      );
    }

    self::assertTrue(
      $zip->close()
    );

    return $file_path;
  }
}
