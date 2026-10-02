<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use Throwable;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\InstallationCredential;
use Osumi\OsumiFramework\App\Utils\Uuid;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;

class InstallationCredentialService extends OService {
  /**
   * Creates a new active credential for an installation.
   *
   * The generated secret is returned only once and only its password hash is persisted.
   *
   * @param Installation $installation Installation that owns the credential.
   *
   * @return array{credential: InstallationCredential, secret: string} Generated credential data.
   *
   * @throws RuntimeException When the installation already has an active credential
   *                          or the credential cannot be persisted.
   */
  public function create(Installation $installation): array {
    if (is_null($installation->id)) {
      throw new RuntimeException(
        'Installation must be persisted before creating a credential.'
      );
    }

    if (!is_null($this->getActive($installation))) {
      throw new RuntimeException(
        'Installation already has an active credential.'
      );
    }

    $secret = rtrim(
      strtr(base64_encode(random_bytes(32)), '+/', '-_'),
      '='
    );

    $credential = new InstallationCredential();
    $credential->id_installation = $installation->id;
    $credential->key_id = Uuid::v4();
    $credential->secret_hash = password_hash($secret, PASSWORD_DEFAULT);
    $credential->last_used_at = null;
    $credential->revoked_at = null;

    if (!$credential->save()) {
      throw new RuntimeException(
        'Installation credential could not be created.'
      );
    }

    return [
      'credential' => $credential,
      'secret' => $secret
    ];
  }

  /**
   * Gets the active credential of an installation.
   *
   * @param Installation $installation Installation to inspect.
   *
   * @return InstallationCredential|null Active credential or null when none exists.
   *
   * @throws RuntimeException When the installation is not persisted.
   */
  public function getActive(
    Installation $installation
  ): ?InstallationCredential {
    $credentials = $this->getActiveCredentials($installation);

    return $credentials[0] ?? null;
  }

  /**
   * Revokes every active credential of an installation.
   *
   * The operation is idempotent. When there is no active credential, zero is returned.
   *
   * @param Installation $installation Installation whose credentials must be revoked.
   *
   * @return int Number of revoked credentials.
   *
   * @throws RuntimeException When the operation cannot be completed.
   */
  public function revokeActive(
    Installation $installation
  ): int {
    $db = ODB::getInstance();

    try {
      $db->beginTransaction();

      $credentials = $this->getActiveCredentials($installation);

      if ($credentials === []) {
        $db->commit();
        return 0;
      }

      $revoked_at = date('Y-m-d H:i:s');

      foreach ($credentials as $credential) {
        $credential->revoked_at = $revoked_at;

        if (!$credential->save()) {
          throw new RuntimeException(
            'Installation credential could not be revoked.'
          );
        }
      }

      $db->commit();

      return count($credentials);
    }
    catch (Throwable $exception) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }

      throw new RuntimeException(
        'Installation credentials could not be revoked.',
        0,
        $exception
      );
    }
  }

  /**
   * Rotates the active credential of an installation.
   *
   * Existing active credentials are revoked and a new credential is created
   * atomically. The generated secret is returned only once.
   *
   * @param Installation $installation Installation whose credential must be rotated.
   *
   * @return array{credential: InstallationCredential, secret: string} New credential data.
   *
   * @throws RuntimeException When the rotation cannot be completed.
   */
  public function rotate(
    Installation $installation
  ): array {
    $db = ODB::getInstance();

    try {
      $db->beginTransaction();

      $credentials = $this->getActiveCredentials($installation);
      $revoked_at = date('Y-m-d H:i:s');

      foreach ($credentials as $credential) {
        $credential->revoked_at = $revoked_at;

        if (!$credential->save()) {
          throw new RuntimeException(
            'Previous installation credential could not be revoked.'
          );
        }
      }

      $credential_data = $this->create($installation);

      $db->commit();

      return $credential_data;
    }
    catch (Throwable $exception) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }

      throw new RuntimeException(
        'Installation credential could not be rotated.',
        0,
        $exception
      );
    }
  }

  /**
   * Gets every active credential of an installation.
   *
   * Multiple results are supported defensively even though the application
   * invariant allows only one active credential.
   *
   * @param Installation $installation Installation to inspect.
   *
   * @return InstallationCredential[] Active credentials ordered from newest to oldest.
   *
   * @throws RuntimeException When the installation is not persisted.
   */
  private function getActiveCredentials(
    Installation $installation
  ): array {
    if (is_null($installation->id)) {
      throw new RuntimeException(
        'Installation must be persisted before accessing its credentials.'
      );
    }

    return InstallationCredential::where(
      [
        'id_installation' => $installation->id,
        'revoked_at' => null
      ],
      [
        'order_by' => 'created_at#DESC'
      ]
    );
  }
}
