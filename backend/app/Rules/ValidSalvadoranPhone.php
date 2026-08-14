<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSalvadoranPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = (string) $value;

        if (! preg_match('/^[267]\d{3}-\d{4}$/', $phone)) {
            $fail('El telefono debe tener el formato ####-#### y comenzar con 2, 6 o 7.');
            return;
        }

        $digits = str_replace('-', '', $phone);

        if (count(array_unique(str_split($digits))) === 1) {
            $fail('El telefono no puede estar formado por un solo digito repetido.');
        }
    }
}
