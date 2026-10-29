<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Environment variables declared in phpunit.xml.
     *
     * @var list<string>
     */
    private const PHPUNIT_ENV_KEYS = [
        'APP_ENV',
        'APP_MAINTENANCE_DRIVER',
        'BCRYPT_ROUNDS',
        'BROADCAST_CONNECTION',
        'CACHE_STORE',
        'DB_CONNECTION',
        'DB_DATABASE',
        'DB_URL',
        'MAIL_MAILER',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
    ];

    public function createApplication()
    {
        // Docker loads the app .env into $_SERVER and Laravel's Env repository
        // prefers it, overriding the values PHPUnit only writes to $_ENV.
        foreach (self::PHPUNIT_ENV_KEYS as $key) {
            if (array_key_exists($key, $_ENV)) {
                $_SERVER[$key] = $_ENV[$key];
            }
        }

        return parent::createApplication();
    }
}
