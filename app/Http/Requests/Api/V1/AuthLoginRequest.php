<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AuthLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = (string) $this->input('phone', '');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits !== '') {
            if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
                $digits = '0' . $digits;
            } elseif (strlen($digits) === 12 && str_starts_with($digits, '2519')) {
                $digits = '0' . substr($digits, 3);
            }

            $this->merge(['phone' => $digits]);
        }

        if ($this->filled('email')) {
            $this->merge(['email' => trim((string) $this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:phone', 'email'],
            'phone' => ['required_without:email', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ];
    }
}
