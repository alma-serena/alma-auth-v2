<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\AuthServiceProvider;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

final class AuthServiceProviderTest extends PhpUnitTestCase
{
    public function test_provider_class_is_loadable(): void
    {
        $this->assertTrue(class_exists(AuthServiceProvider::class));
        $this->assertTrue(is_subclass_of(AuthServiceProvider::class, ServiceProvider::class));
    }
}
