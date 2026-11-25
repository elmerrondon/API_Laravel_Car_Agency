<?php

declare(strict_types=1);

use Dedoc\Scramble\OpenApiVersion;

it('expone la configuración base esperada del API', function () {
    expect(config('sanctum.expiration'))->toBeInt()->toBe(120)
        ->and(config('docs.enabled'))->toBeBool()->toBeFalse()
        ->and(config('scramble.openapi_version'))->toBe(OpenApiVersion::V3_1)
        ->and(config('scramble.info.version'))->toBeString()->not->toBeEmpty();
});
