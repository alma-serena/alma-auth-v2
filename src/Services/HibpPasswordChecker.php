<?php

declare(strict_types=1);

namespace Alma\Auth\Services;

use Alma\Auth\Contracts\CompromisedPasswordChecker;
use Illuminate\Support\Facades\Http;

final class HibpPasswordChecker implements CompromisedPasswordChecker
{
    public function isCompromised(string $password): bool
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $response = Http::timeout(2)
                ->withHeaders(['Add-Padding' => 'true'])
                ->get('https://api.pwnedpasswords.com/range/'.$prefix);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->ok()) {
            return false;
        }

        foreach (preg_split("/\r\n|\n|\r/", $response->body()) ?: [] as $line) {
            $line = strtoupper(trim((string) $line));
            if (str_starts_with($line, $suffix.':')) {
                return true;
            }
        }

        return false;
    }
}
