<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\InstallationCredential;
use Osumi\OsumiFramework\App\Utils\Uuid;
use Osumi\OsumiFramework\Core\OService;

class InstallationCredentialService extends OService {
  /**
   * Creates a new credential for an installation.
   *
   * The generated secret is returned only once and only its password hash is persisted.
   *
   * @param Installation $installation Installation that owns the credential.
   *
   * @return array{credential: InstallationCredential, secret: string} Generated credential data.
   *
   * @throws RuntimeException When the credential cannot be persisted.
   */
  public function create(Installation $installation): array {
    if (is_null($installation->id)) {
      throw new RuntimeException('Installation must be persisted before creating a credential.');
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
      throw new RuntimeException('Installation credential could not be created.');
    }

    return [
      'credential' => $credential,
      'secret' => $secret
    ];
  }
}
