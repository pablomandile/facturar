<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = ['password' => $this->passwordRules()];

        // Quien entró con Google no tiene contraseña actual: exigirla le
        // impediría definirse una nunca.
        if (filled($this->user()?->getAuthPassword())) {
            $reglas['current_password'] = $this->currentPasswordRules();
        }

        return $reglas;
    }
}
