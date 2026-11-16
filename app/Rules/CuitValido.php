<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Arca\Cuit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Once dígitos con el verificador correcto. Acepta guiones: la normalización
 * la hace quien guarda (ver `prepareForValidation` de cada FormRequest).
 */
class CuitValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cuit::esValido($value)) {
            $fail('El :attribute no es válido: revisá los 11 números.');
        }
    }
}
