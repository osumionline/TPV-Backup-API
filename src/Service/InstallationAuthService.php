<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\InstallationCredential;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\Plugins\OToken;

class InstallationAuthService extends OService {
  private const DEFAULT_TOKEN_TTL = 3600;

  /**
   * Authenticates an installation credential and issues a short-lived token.
   *
   * Expired subscriptions may authenticate so existing backups remain
   * recoverable, but the returned can_upload flag will be false.
   *
   * @param string $key_id Credential public identifier.
   * @param string $secret Credential secret.
   *
   * @return array{
   *   token: string,
   *   expires_at: int,
   *   installation: Installation,
   *   subscription: Subscription,
   *   credential: InstallationCredential,
   *   subscription_status: string,
   *   can_upload: bool
   * }|null Authentication result or null when credentials are not usable.
   *
   * @throws RuntimeException When token configuration or persistence fails.
   */
  public function login(
    string $key_id,
    string $secret
  ): ?array {
    $key_id = trim(
      $key_id
    );

    if (
      $key_id === '' ||
      $secret === ''
    ) {
      return null;
    }

    $credential = $this->getCredentialByKeyId(
      $key_id
    );

    if (
      is_null($credential) ||
      !is_null($credential->revoked_at) ||
      is_null($credential->secret_hash) ||
      !password_verify(
        $secret,
        $credential->secret_hash
      ) ||
      is_null($credential->id_installation)
    ) {
      return null;
    }

    $installation = $this->getInstallationById(
      $credential->id_installation
    );

    if (
      is_null($installation) ||
      $installation->active !== true ||
      is_null($installation->id_subscription)
    ) {
      return null;
    }

    $subscription = $this->getSubscriptionById(
      $installation->id_subscription
    );

    if (
      is_null($subscription) ||
      $subscription->active !== true
    ) {
      return null;
    }

    $token_data = $this->createToken(
      $installation,
      $credential
    );

    $this->markSuccessfulAuthentication(
      $credential,
      $installation,
      $secret
    );

    $subscription_status = $this->isSubscriptionExpired(
      $subscription
    )
      ? 'expired'
      : 'active';

    return [
      'token' => $token_data['token'],
      'expires_at' => $token_data['expires_at'],
      'installation' => $installation,
      'subscription' => $subscription,
      'credential' => $credential,
      'subscription_status' => $subscription_status,
      'can_upload' => $subscription_status === 'active'
    ];
  }

  /**
   * Authenticates an installation access token.
   *
   * The current credential, installation and subscription state is checked on
   * every request so credential revocation and administrative disabling take
   * effect immediately.
   *
   * @param string $raw_token Encoded installation token.
   *
   * @return array<string, mixed>|null Trusted middleware context or null.
   */
  public function authenticateToken(
    string $raw_token
  ): ?array {
    $claims = $this->decodeToken(
      $raw_token
    );

    if (
      is_null($claims) ||
      ($claims['type'] ?? null) !== 'installation'
    ) {
      return null;
    }

    $credential_id = filter_var(
      $claims['credential_id']
        ?? null,
      FILTER_VALIDATE_INT
    );

    $key_id = $claims['key_id']
      ?? null;

    $installation_id = filter_var(
      $claims['installation_id']
        ?? null,
      FILTER_VALIDATE_INT
    );

    $installation_public_id =
      $claims['installation_public_id']
      ?? null;

    if (
      $credential_id === false ||
      $credential_id <= 0 ||
      !is_string($key_id) ||
      $key_id === '' ||
      $installation_id === false ||
      $installation_id <= 0 ||
      !is_string($installation_public_id) ||
      $installation_public_id === ''
    ) {
      return null;
    }

    $credential = $this->getCredentialById(
      $credential_id
    );

    if (
      is_null($credential) ||
      !is_null($credential->revoked_at) ||
      $credential->key_id !== $key_id ||
      $credential->id_installation !== $installation_id
    ) {
      return null;
    }

    $installation = $this->getInstallationById(
      $installation_id
    );

    if (
      is_null($installation) ||
      $installation->active !== true ||
      $installation->public_id !==
        $installation_public_id ||
      is_null($installation->id_subscription)
    ) {
      return null;
    }

    $subscription = $this->getSubscriptionById(
      $installation->id_subscription
    );

    if (
      is_null($subscription) ||
      $subscription->active !== true ||
      is_null($subscription->id) ||
      is_null($subscription->public_id)
    ) {
      return null;
    }

    $subscription_status = $this->isSubscriptionExpired(
      $subscription
    )
      ? 'expired'
      : 'active';

    return [
      'status' => 'ok',
      'installation_id' => $installation->id,
      'installation_public_id' => $installation->public_id,
      'installation_name' => $installation->name,
      'credential_id' => $credential->id,
      'key_id' => $credential->key_id,
      'subscription_id' => $subscription->id,
      'subscription_public_id' => $subscription->public_id,
      'subscription_name' => $subscription->name,
      'subscription_status' => $subscription_status,
      'can_upload' => $subscription_status === 'active'
    ];
  }

  /**
   * Gets a credential by its public key identifier.
   *
   * @param string $key_id Credential key identifier.
   *
   * @return InstallationCredential|null Credential or null.
   */
  protected function getCredentialByKeyId(
    string $key_id
  ): ?InstallationCredential {
    return InstallationCredential::findOne([
      'key_id' => $key_id
    ]);
  }

  /**
   * Gets a credential by its internal identifier.
   *
   * @param int $id Credential internal identifier.
   *
   * @return InstallationCredential|null Credential or null.
   */
  protected function getCredentialById(
    int $id
  ): ?InstallationCredential {
    return InstallationCredential::findOne([
      'id' => $id
    ]);
  }

  /**
   * Gets an installation by its internal identifier.
   *
   * @param int $id Installation internal identifier.
   *
   * @return Installation|null Installation or null.
   */
  protected function getInstallationById(
    int $id
  ): ?Installation {
    return Installation::findOne([
      'id' => $id
    ]);
  }

  /**
   * Gets a subscription by its internal identifier.
   *
   * @param int $id Subscription internal identifier.
   *
   * @return Subscription|null Subscription or null.
   */
  protected function getSubscriptionById(
    int $id
  ): ?Subscription {
    return Subscription::findOne([
      'id' => $id
    ]);
  }

  /**
   * Creates a signed short-lived token for an installation.
   *
   * @param Installation           $installation Installation being authenticated.
   * @param InstallationCredential $credential   Credential used for authentication.
   *
   * @return array{token: string, expires_at: int} Generated token data.
   *
   * @throws RuntimeException When token configuration is missing.
   */
  protected function createToken(
    Installation $installation,
    InstallationCredential $credential
  ): array {
    if (
      is_null($installation->id) ||
      is_null($installation->public_id) ||
      is_null($credential->id) ||
      is_null($credential->key_id)
    ) {
      throw new RuntimeException(
        'Installation authentication data is incomplete.'
      );
    }

    $secret = $this
      ->getConfig()
      ->getExtra(
        'installation_token_secret'
      );

    if (
      !is_string($secret) ||
      $secret === ''
    ) {
      throw new RuntimeException(
        'Installation token secret is not configured.'
      );
    }

    $ttl = $this
      ->getConfig()
      ->getExtra(
        'installation_token_ttl'
      );

    $ttl = is_int($ttl) &&
      $ttl > 0
      ? $ttl
      : self::DEFAULT_TOKEN_TTL;

    $now = time();
    $expires_at = $now + $ttl;

    $token = new OToken(
      $secret
    );

    $token->addParam(
      'type',
      'installation'
    );

    $token->addParam(
      'installation_id',
      $installation->id
    );

    $token->addParam(
      'installation_public_id',
      $installation->public_id
    );

    $token->addParam(
      'credential_id',
      $credential->id
    );

    $token->addParam(
      'key_id',
      $credential->key_id
    );

    $token->setIAT(
      $now
    );

    $token->setEXP(
      $expires_at
    );

    return [
      'token' => $token->getToken(),
      'expires_at' => $expires_at
    ];
  }

  /**
   * Decodes and validates a signed installation token.
   *
   * @param string $raw_token Encoded token.
   *
   * @return array<string, mixed>|null Token claims or null when invalid.
   *
   * @throws RuntimeException When token configuration is missing.
   */
  protected function decodeToken(
    string $raw_token
  ): ?array {
    if (
      $raw_token === '' ||
      substr_count(
        $raw_token,
        '.'
      ) !== 2
    ) {
      return null;
    }

    $secret = $this
      ->getConfig()
      ->getExtra(
        'installation_token_secret'
      );

    if (
      !is_string($secret) ||
      $secret === ''
    ) {
      throw new RuntimeException(
        'Installation token secret is not configured.'
      );
    }

    $token = new OToken(
      $secret
    );

    if (
      !$token->checkToken(
        $raw_token
      )
    ) {
      return null;
    }

    return [
      'type' => $token->getParam('type'),
      'installation_id' => $token->getParam(
        'installation_id'
      ),
      'installation_public_id' => $token->getParam(
        'installation_public_id'
      ),
      'credential_id' => $token->getParam(
        'credential_id'
      ),
      'key_id' => $token->getParam(
        'key_id'
      )
    ];
  }

  /**
   * Updates usage metadata after successful credential authentication.
   *
   * @param InstallationCredential $credential   Credential used for login.
   * @param Installation           $installation Authenticated installation.
   * @param string                 $secret       Verified credential secret.
   *
   * @return void
   *
   * @throws RuntimeException When usage metadata cannot be persisted.
   */
  protected function markSuccessfulAuthentication(
    InstallationCredential $credential,
    Installation $installation,
    string $secret
  ): void {
    $now = date(
      'Y-m-d H:i:s'
    );

    if (
      !is_null($credential->secret_hash) &&
      password_needs_rehash(
        $credential->secret_hash,
        PASSWORD_DEFAULT
      )
    ) {
      $credential->secret_hash = password_hash(
        $secret,
        PASSWORD_DEFAULT
      );
    }

    $credential->last_used_at = $now;

    if (!$credential->save()) {
      throw new RuntimeException(
        'Installation credential usage could not be persisted.'
      );
    }

    $installation->last_seen_at = $now;

    if (!$installation->save()) {
      throw new RuntimeException(
        'Installation last seen date could not be persisted.'
      );
    }
  }

  /**
   * Checks whether a subscription has passed its expiration date.
   *
   * @param Subscription $subscription Subscription to inspect.
   *
   * @return bool True when the subscription is expired.
   */
  protected function isSubscriptionExpired(
    Subscription $subscription
  ): bool {
    if (is_null($subscription->expires_at)) {
      return false;
    }

    $expires_at = strtotime(
      $subscription->expires_at
    );

    return $expires_at === false ||
      $expires_at < time();
  }
}
