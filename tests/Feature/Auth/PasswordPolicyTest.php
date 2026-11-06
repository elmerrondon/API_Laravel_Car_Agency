<?php

declare(strict_types=1);

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Lee una propiedad protegida de la regla de Laravel para fijar que no se
 * activa `uncompromised()` (la única parte que haría una llamada externa).
 */
function passwordRuleProperty(Password $rule, string $property): mixed
{
    $reflection = new ReflectionClass($rule);
    $reflectionProperty = $reflection->getProperty($property);
    $reflectionProperty->setAccessible(true);

    return $reflectionProperty->getValue($rule);
}

it('aplica la política fuerte local sin consultar servicios externos', function () {
    $rule = PasswordPolicy::strong();

    expect(passwordRuleProperty($rule, 'min'))->toBe(8)
        ->and(passwordRuleProperty($rule, 'mixedCase'))->toBeTrue()
        ->and(passwordRuleProperty($rule, 'numbers'))->toBeTrue()
        ->and(passwordRuleProperty($rule, 'symbols'))->toBeTrue()
        ->and(passwordRuleProperty($rule, 'uncompromised'))->toBeFalse();
});

it('acepta solo contraseñas que cumplen todas las reglas locales', function (string $password, bool $valid) {
    $validator = Validator::make(
        ['password' => $password],
        ['password' => PasswordPolicy::strong()],
    );

    expect($validator->passes())->toBe($valid);
})->with([
    'completa' => ['Abcdef1!', true],
    'demasiado corta' => ['Ab1!', false],
    'sin mayúscula' => ['abcdefg1!', false],
    'sin minúscula' => ['ABCDEFG1!', false],
    'sin número' => ['Abcdefgh!', false],
    'sin símbolo' => ['Abcdefg1', false],
]);

it('usa la política relajada fuera de producción y nunca uncompromised', function () {
    $default = Password::default();

    expect(passwordRuleProperty($default, 'min'))->toBe(PasswordPolicy::RELAXED_MIN_LENGTH)
        ->and(passwordRuleProperty($default, 'uncompromised'))->toBeFalse();
});
