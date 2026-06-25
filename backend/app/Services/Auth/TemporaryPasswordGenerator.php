<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

class TemporaryPasswordGenerator
{
    public function generate(): string
    {
        return Str::password(length: 14, symbols: false);
    }
}
