<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Política de contraseñas 100% local.
 *
 * No se usa `uncompromised()`: esa regla consulta la API de Have I Been Pwned
 * en cada validación y rompe la regla de autosuficiencia del proyecto.
 */
final class PasswordPolicy
{
    public const STRONG_MIN_LENGTH = 8;

    public const RELAXED_MIN_LENGTH = 6;

    /**
     * Regla fuerte y determinista: 8 caracteres, mayúsculas, minúsculas,
     * números y símbolos, sin llamadas HTTP salientes.
     */
    public static function strong(): Password
    {
        return Password::min(self::STRONG_MIN_LENGTH)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    /**
     * Regla de desarrollo y testing para no complicar los datos de ejemplo.
     */
    public static function relaxed(): Password
    {
        return Password::min(self::RELAXED_MIN_LENGTH);
    }

    /**
     * La producción aplica la política fuerte; el resto de entornos usa la
     * relajada para que los seeders y la suite sigan siendo cómodos.
     */
    public static function current(): Password
    {
        return app()->isProduction() ? self::strong() : self::relaxed();
    }
}
