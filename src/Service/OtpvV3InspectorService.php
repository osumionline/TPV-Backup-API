<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;
use Osumi\OsumiFramework\App\Exception\InvalidOtpvPackageException;
use Osumi\OsumiFramework\Core\OService;

class OtpvV3InspectorService extends OService {
  private const FORMAT_VERSION = 3;
  private const APPLICATION = 'Osumi TPV Client';
  private const CRYPTO_SUITE = 'otpv3-scrypt-aes-256-gcm';

  private const MANIFEST_ENTRY = 'manifest.json';
  private const PAYLOAD_ENTRY = 'payload.enc';

  private const MAX_MANIFEST_SIZE = 65536;

  /**
   * Inspects and validates an OTPV v3 package without decrypting its payload.
   *
   * @param string $file_path Local OTPV file path.
   *
   * @return array{
   *   formatVersion: int,
   *   application: string,
   *   applicationVersion: string,
   *   databaseSchemaVersion: int,
   *   backupId: string,
   *   createdAt: string,
   *   cryptoSuite: string,
   *   sizeBytes: int,
   *   sha256: string
   * } Validated public package metadata.
   *
   * @throws InvalidOtpvPackageException When the package does not conform to OTPV v3.
   * @throws RuntimeException When the package cannot be inspected.
   */
  public function inspect(string $file_path): array {
    if (
      !is_file($file_path) ||
      !is_readable($file_path)
    ) {
      throw new RuntimeException(
        'OTPV package cannot be read.'
      );
    }

    if (!class_exists(ZipArchive::class)) {
      throw new RuntimeException(
        'ZipArchive extension is not available.'
      );
    }

    $size = filesize($file_path);

    if ($size === false) {
      throw new RuntimeException(
        'OTPV package size could not be obtained.'
      );
    }

    $sha256 = hash_file(
      'sha256',
      $file_path
    );

    if ($sha256 === false) {
      throw new RuntimeException(
        'OTPV package SHA-256 could not be calculated.'
      );
    }

    $zip = new ZipArchive();

    $open_result = $zip->open(
      $file_path,
      ZipArchive::RDONLY
    );

    if ($open_result !== true) {
      throw new InvalidOtpvPackageException(
        'OTPV package is not a valid ZIP archive.'
      );
    }

    try {
      $this->validateEntries($zip);

      $manifest = $this->readManifest($zip);

      $this->validateManifest($manifest);

      return [
        'formatVersion' => $manifest['formatVersion'],
        'application' => $manifest['application'],
        'applicationVersion' => $manifest['applicationVersion'],
        'databaseSchemaVersion' => $manifest['databaseSchemaVersion'],
        'backupId' => $manifest['backupId'],
        'createdAt' => $manifest['createdAt'],
        'cryptoSuite' => $manifest['cryptoSuite'],
        'sizeBytes' => $size,
        'sha256' => $sha256
      ];
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Validates the outer ZIP structure.
   *
   * OTPV v3 packages must contain exactly manifest.json and payload.enc.
   * The encrypted payload must be stored without ZIP compression.
   *
   * @param ZipArchive $zip Open OTPV archive.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the archive structure is invalid.
   */
  private function validateEntries(ZipArchive $zip): void {
    if ($zip->numFiles !== 2) {
      throw new InvalidOtpvPackageException(
        'OTPV v3 package must contain exactly two entries.'
      );
    }

    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
      $entry = $zip->getNameIndex($index);

      if ($entry === false) {
        throw new InvalidOtpvPackageException(
          'OTPV package contains an invalid ZIP entry.'
        );
      }

      $entries[] = $entry;
    }

    sort($entries);

    $expected_entries = [
      self::MANIFEST_ENTRY,
      self::PAYLOAD_ENTRY
    ];

    sort($expected_entries);

    if ($entries !== $expected_entries) {
      throw new InvalidOtpvPackageException(
        'OTPV v3 package contains unexpected entries.'
      );
    }

    $payload_stat = $zip->statName(
      self::PAYLOAD_ENTRY
    );

    if (!is_array($payload_stat)) {
      throw new InvalidOtpvPackageException(
        'OTPV encrypted payload could not be inspected.'
      );
    }

    if (
      !array_key_exists('comp_method', $payload_stat) ||
      $payload_stat['comp_method'] !== ZipArchive::CM_STORE
    ) {
      throw new InvalidOtpvPackageException(
        'OTPV encrypted payload must be stored without ZIP compression.'
      );
    }
  }

  /**
   * Reads and decodes the public OTPV manifest.
   *
   * @param ZipArchive $zip Open OTPV archive.
   *
   * @return array<string, mixed> Decoded manifest.
   *
   * @throws InvalidOtpvPackageException When the manifest is missing, too large
   *                                     or contains invalid JSON.
   */
  private function readManifest(ZipArchive $zip): array {
    $manifest_stat = $zip->statName(
      self::MANIFEST_ENTRY
    );

    if (!is_array($manifest_stat)) {
      throw new InvalidOtpvPackageException(
        'OTPV manifest could not be inspected.'
      );
    }

    $manifest_size = $manifest_stat['size'] ?? null;

    if (
      !is_int($manifest_size) ||
      $manifest_size <= 0 ||
      $manifest_size > self::MAX_MANIFEST_SIZE
    ) {
      throw new InvalidOtpvPackageException(
        'OTPV manifest size is invalid.'
      );
    }

    $manifest_json = $zip->getFromName(
      self::MANIFEST_ENTRY
    );

    if ($manifest_json === false) {
      throw new InvalidOtpvPackageException(
        'OTPV manifest could not be read.'
      );
    }

    try {
      $manifest = json_decode(
        $manifest_json,
        true,
        512,
        JSON_THROW_ON_ERROR
      );
    }
    catch (JsonException $exception) {
      throw new InvalidOtpvPackageException(
        'OTPV manifest contains invalid JSON.',
        0,
        $exception
      );
    }

    if (!is_array($manifest)) {
      throw new InvalidOtpvPackageException(
        'OTPV manifest must contain a JSON object.'
      );
    }

    return $manifest;
  }

  /**
   * Validates the complete public OTPV v3 manifest.
   *
   * @param array<string, mixed> $manifest Decoded manifest.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When any required manifest field is invalid.
   */
  private function validateManifest(array $manifest): void {
    $this->assertExactKeys(
      $manifest,
      [
        'formatVersion',
        'application',
        'applicationVersion',
        'databaseSchemaVersion',
        'backupId',
        'createdAt',
        'cryptoSuite',
        'authenticatedData',
        'kdf',
        'keyWrap',
        'payload'
      ],
      'manifest'
    );

    if ($manifest['formatVersion'] !== self::FORMAT_VERSION) {
      throw new InvalidOtpvPackageException(
        'Unsupported OTPV format version.'
      );
    }

    if ($manifest['application'] !== self::APPLICATION) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV application.'
      );
    }

    if (
      !is_string($manifest['applicationVersion']) ||
      trim($manifest['applicationVersion']) === '' ||
      strlen($manifest['applicationVersion']) > 50
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV application version.'
      );
    }

    if (
      !is_int($manifest['databaseSchemaVersion']) ||
      $manifest['databaseSchemaVersion'] <= 0
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV database schema version.'
      );
    }

    if (
      !is_string($manifest['backupId']) ||
      preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di',
        $manifest['backupId']
      ) !== 1
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV backup identifier.'
      );
    }

    if (
      !is_string($manifest['createdAt']) ||
      !$this->isValidIsoDate($manifest['createdAt'])
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV creation date.'
      );
    }

    if ($manifest['cryptoSuite'] !== self::CRYPTO_SUITE) {
      throw new InvalidOtpvPackageException(
        'Unsupported OTPV crypto suite.'
      );
    }

    if (!is_string($manifest['authenticatedData'])) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV authenticated data.'
      );
    }

    $this->assertBase64(
      $manifest['authenticatedData'],
      'authenticatedData'
    );

    if (!is_array($manifest['kdf'])) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV KDF configuration.'
      );
    }

    $this->validateKdf($manifest['kdf']);

    if (!is_array($manifest['keyWrap'])) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV key wrapping configuration.'
      );
    }

    $this->validateKeyWrap($manifest['keyWrap']);

    if (!is_array($manifest['payload'])) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV payload configuration.'
      );
    }

    $this->validatePayload($manifest['payload']);
  }

  /**
   * Validates the scrypt KDF configuration.
   *
   * @param array<string, mixed> $kdf KDF configuration.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the KDF configuration is invalid.
   */
  private function validateKdf(array $kdf): void {
    $this->assertExactKeys(
      $kdf,
      [
        'algorithm',
        'salt',
        'cost',
        'blockSize',
        'parallelization',
        'length'
      ],
      'kdf'
    );

    if (
      $kdf['algorithm'] !== 'scrypt' ||
      $kdf['cost'] !== 32768 ||
      $kdf['blockSize'] !== 8 ||
      $kdf['parallelization'] !== 3 ||
      $kdf['length'] !== 32 ||
      !is_string($kdf['salt'])
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV KDF configuration.'
      );
    }

    $this->assertBase64Length(
      $kdf['salt'],
      32,
      'kdf.salt'
    );
  }

  /**
   * Validates the encrypted DEK wrapping information.
   *
   * @param array<string, mixed> $key_wrap Key wrapping configuration.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the key wrapping configuration is invalid.
   */
  private function validateKeyWrap(array $key_wrap): void {
    $this->assertExactKeys(
      $key_wrap,
      [
        'algorithm',
        'iv',
        'authTag',
        'wrappedDek'
      ],
      'keyWrap'
    );

    if (
      $key_wrap['algorithm'] !== 'aes-256-gcm' ||
      !is_string($key_wrap['iv']) ||
      !is_string($key_wrap['authTag']) ||
      !is_string($key_wrap['wrappedDek'])
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV key wrapping configuration.'
      );
    }

    $this->assertBase64Length(
      $key_wrap['iv'],
      12,
      'keyWrap.iv'
    );

    $this->assertBase64Length(
      $key_wrap['authTag'],
      16,
      'keyWrap.authTag'
    );

    $this->assertBase64Length(
      $key_wrap['wrappedDek'],
      32,
      'keyWrap.wrappedDek'
    );
  }

  /**
   * Validates the encrypted payload metadata.
   *
   * @param array<string, mixed> $payload Payload configuration.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the payload configuration is invalid.
   */
  private function validatePayload(array $payload): void {
    $this->assertExactKeys(
      $payload,
      [
        'entry',
        'format',
        'algorithm',
        'iv',
        'authTag'
      ],
      'payload'
    );

    if (
      $payload['entry'] !== self::PAYLOAD_ENTRY ||
      $payload['format'] !== 'zip' ||
      $payload['algorithm'] !== 'aes-256-gcm' ||
      !is_string($payload['iv']) ||
      !is_string($payload['authTag'])
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV payload configuration.'
      );
    }

    $this->assertBase64Length(
      $payload['iv'],
      12,
      'payload.iv'
    );

    $this->assertBase64Length(
      $payload['authTag'],
      16,
      'payload.authTag'
    );
  }

  /**
   * Ensures that an object contains exactly the expected keys.
   *
   * @param array<string, mixed> $data          Object data.
   * @param string[]             $expected_keys Expected keys.
   * @param string               $context       Human-readable context.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the object shape is invalid.
   */
  private function assertExactKeys(
    array $data,
    array $expected_keys,
    string $context
  ): void {
    $actual_keys = array_keys($data);

    sort($actual_keys);
    sort($expected_keys);

    if ($actual_keys !== $expected_keys) {
      throw new InvalidOtpvPackageException(
        "Invalid OTPV {$context} structure."
      );
    }
  }

  /**
   * Validates a Base64-encoded value.
   *
   * @param string $value Base64 value.
   * @param string $field Field name used in errors.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the value is not valid Base64.
   */
  private function assertBase64(
    string $value,
    string $field
  ): void {
    if (
      $value === '' ||
      base64_decode(
        $value,
        true
      ) === false
    ) {
      throw new InvalidOtpvPackageException(
        "Invalid OTPV {$field}."
      );
    }
  }

  /**
   * Validates a Base64-encoded value with an exact decoded length.
   *
   * @param string $value           Base64 value.
   * @param int    $expected_length Expected decoded byte length.
   * @param string $field           Field name used in errors.
   *
   * @return void
   *
   * @throws InvalidOtpvPackageException When the value is invalid.
   */
  private function assertBase64Length(
    string $value,
    int $expected_length,
    string $field
  ): void {
    $decoded = base64_decode(
      $value,
      true
    );

    if (
      $decoded === false ||
      strlen($decoded) !== $expected_length
    ) {
      throw new InvalidOtpvPackageException(
        "Invalid OTPV {$field}."
      );
    }
  }

  /**
   * Checks whether a value contains an ISO-8601 timestamp.
   *
   * @param string $value Timestamp to validate.
   *
   * @return bool True when the value is a valid ISO-8601 timestamp.
   */
  private function isValidIsoDate(string $value): bool {
    if (
      preg_match(
        '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D',
        $value
      ) !== 1
    ) {
      return false;
    }

    try {
      new DateTimeImmutable($value);
    }
    catch (Throwable) {
      return false;
    }

    return true;
  }
}
