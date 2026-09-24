<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Services\HibpPasswordChecker;
use Illuminate\Support\Facades\Http;

final class HibpPasswordCheckerTest extends TestCase
{
    public function test_a_matching_suffix_is_compromised(): void
    {
        $password = 'una-clave-larga';
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        Http::fake([
            'https://api.pwnedpasswords.com/range/'.$prefix => Http::response($suffix.":3\r\nFFFFF:1\r\n", 200),
        ]);

        $this->assertTrue((new HibpPasswordChecker)->isCompromised($password));
    }

    public function test_a_failed_request_does_not_block_the_password(): void
    {
        Http::fake([
            '*' => Http::response('no', 503),
        ]);

        $this->assertFalse((new HibpPasswordChecker)->isCompromised('una-clave-larga'));
    }
}
