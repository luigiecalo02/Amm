<?php

namespace App\Http\Requests\Motorcycle;

use App\Models\Motorcycle;
use Illuminate\Foundation\Http\FormRequest;

class StoreMotorcycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Motorcycle::class, $this->route('organization')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'exists:people,id'],
            'plate' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'color' => ['nullable', 'string', 'max:40'],
            'engine_number' => ['nullable', 'string', 'max:80'],
            'chassis_number' => ['nullable', 'string', 'max:80'],
            'vin' => ['nullable', 'string', 'max:80'],
            'engine_displacement' => ['nullable', 'integer', 'min:50', 'max:3000'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'vehicle_type' => ['nullable', 'in:motorcycle,car'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
