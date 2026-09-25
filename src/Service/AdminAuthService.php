<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\Plugins\OToken;
use Osumi\OsumiFramework\App\Model\AdminUser;

class AdminAuthService extends OService {
  public function login(string $email, string $password): ?array {
    $email = strtolower(trim($email));

    $admin = AdminUser::findOne(['email' => $email]);

    if (
      is_null($admin) ||
      $admin->active !== true ||
      is_null($admin->password) ||
      !password_verify($password, $admin->password)
    ) {
      return null;
    }

    $secret = $this->getConfig()->getExtra('admin_token_secret');

    if (!is_string($secret) || $secret === '') {
      throw new RuntimeException('Admin token secret is not configured.');
    }

    $ttl = $this->getConfig()->getExtra('admin_token_ttl');
    $ttl = is_int($ttl) && $ttl > 0 ? $ttl : 28800;

    $now = time();
    $expires_at = $now + $ttl;

    $token = new OToken($secret);
    $token->addParam('type', 'admin');
    $token->addParam('id', $admin->id);
    $token->addParam('public_id', $admin->public_id);
    $token->setIAT($now);
    $token->setEXP($expires_at);

    if (password_needs_rehash($admin->password, PASSWORD_DEFAULT)) {
      $admin->password = password_hash($password, PASSWORD_DEFAULT);
    }

    $admin->last_login_at = date('Y-m-d H:i:s');
    $admin->save();

    return [
      'token' => $token->getToken(),
      'expires_at' => $expires_at,
      'admin' => $admin
    ];
  }
}
