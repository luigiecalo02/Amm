<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return false;
        }

        return $this->user()?->can('update', [$user, $this->route('organization')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'role_id' => ['required', 'exists:roles,id'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Escribe el correo de ingreso.',
            'email.email' => 'Escribe un correo válido.',
            'email.unique' => 'Ese correo ya lo usa otra cuenta.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }
}
