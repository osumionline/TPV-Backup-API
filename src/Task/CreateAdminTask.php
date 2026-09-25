<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\App\Model\AdminUser;
use Osumi\OsumiFramework\App\Utils\Uuid;

class CreateAdminTask extends OTask {
  public function __toString() {
    return 'createAdmin: Crea un usuario administrador de TPV Backup';
  }

  public function run(array $options = []): void {
    $email = isset($options['email']) ? trim((string) $options['email']) : '';
    $name = isset($options['name']) ? trim((string) $options['name']) : '';

    $password = isset($options['password'])
      ? (string) $options['password']
      : (string) (getenv('TPV_BACKUP_ADMIN_PASSWORD') ?: '');

    if ($email === '' || $name === '' || $password === '') {
      echo "Uso:\n";
      echo "  php of createAdmin --email admin@example.com --name \"Administrador\" --password \"password\"\n\n";
      echo "También puede pasarse la contraseña mediante la variable de entorno TPV_BACKUP_ADMIN_PASSWORD.\n";
      return;
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      echo "Error: el email indicado no es válido.\n";
      return;
    }

    $email = strtolower($email);

    if (!is_null(AdminUser::findOne(['email' => $email]))) {
      echo "Error: ya existe un administrador con ese email.\n";
      return;
    }

    $admin = new AdminUser();
    $admin->public_id = Uuid::v4();
    $admin->email = $email;
    $admin->password = password_hash($password, PASSWORD_DEFAULT);
    $admin->name = $name;
    $admin->active = true;
    $admin->last_login_at = null;
    $admin->save();

    echo "Administrador creado correctamente.\n";
    echo "ID: " . $admin->id . "\n";
    echo "Public ID: " . $admin->public_id . "\n";
  }
}
