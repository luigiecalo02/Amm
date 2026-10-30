<?php

namespace App\Http\Requests\Inscription;

use Illuminate\Foundation\Http\FormRequest;

class StoreInscriptionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inscriptions.create', $this->route('organization')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'expires_in_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
