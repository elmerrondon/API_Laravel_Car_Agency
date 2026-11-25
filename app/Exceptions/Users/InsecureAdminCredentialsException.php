<?php

declare(strict_types=1);

namespace App\Exceptions\Users;

use RuntimeException;

/**
 * Falla de configuración del administrador fundacional.
 *
 * El seeder de producción aborta antes de escribir nada cuando las
 * credenciales no son seguras: un despliegue con la clave por defecto o con
 * un marcador `CHANGE_ME_*` expone la cuenta con más privilegios.
 */
final class InsecureAdminCredentialsException extends RuntimeException
{
    public static function email(): self
    {
        return new self(
            'ADMIN_EMAIL no está definido, no es un correo válido o conserva un '
            .'marcador CHANGE_ME_*: define el correo real del administrador en producción.'
        );
    }

    public static function missingPassword(): self
    {
        return new self(
            'ADMIN_PASSWORD no está definido: define una contraseña segura para el '
            .'administrador en producción.'
        );
    }

    public static function defaultPassword(): self
    {
        return new self(
            'ADMIN_PASSWORD no puede ser la contraseña por defecto "secret1234": '
            .'define una contraseña segura en producción.'
        );
    }

    public static function placeholderPassword(): self
    {
        return new self(
            'ADMIN_PASSWORD conserva un marcador CHANGE_ME_*: define una contraseña '
            .'real antes de desplegar en producción.'
        );
    }

    public static function weakPassword(): self
    {
        return new self(
            'ADMIN_PASSWORD no cumple la política de producción: mínimo 8 caracteres '
            .'con mayúsculas, minúsculas, números y símbolos.'
        );
    }
}
