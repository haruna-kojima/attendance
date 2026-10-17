<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function passwordRules(): array
    {
        // 💡 Fortify標準の複雑なルールを解除し、純粋な「最少8文字(min:8)」のシンプルなルールに上書きします
        return ['required', 'string', 'min:8', 'confirmed'];
    }
}
