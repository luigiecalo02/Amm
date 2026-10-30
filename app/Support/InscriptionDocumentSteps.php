<?php

namespace App\Support;

final class InscriptionDocumentSteps
{
    /**
     * @return array<int, array{key: string, label: string, hint: string, type: string|null, analyze: bool, camera: bool, icon: string, frame: string, group: string, group_label: string, side: string|null, validate: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'cedula_front',
                'label' => 'Cédula · frente',
                'hint' => 'Encaja el lado de la foto en el marco. Vamos a leer nombres y número.',
                'type' => 'cedula',
                'analyze' => true,
                'camera' => true,
                'icon' => 'cedula',
                'frame' => 'id-card',
                'group' => 'cedula',
                'group_label' => 'Cédula',
                'side' => 'front',
                'validate' => true,
            ],
            [
                'key' => 'cedula_back',
                'label' => 'Cédula · reverso',
                'hint' => 'Encaja el lado de la firma y el grupo sanguíneo.',
                'type' => 'cedula',
                'analyze' => true,
                'camera' => true,
                'icon' => 'cedula',
                'frame' => 'id-card',
                'group' => 'cedula',
                'group_label' => 'Cédula',
                'side' => 'back',
                'validate' => true,
            ],
            [
                'key' => 'licencia_front',
                'label' => 'Licencia · frente',
                'hint' => 'Encaja el frente de la licencia. Vamos a leer vigencia.',
                'type' => 'licencia',
                'analyze' => true,
                'camera' => true,
                'icon' => 'licencia',
                'frame' => 'id-card',
                'group' => 'licencia',
                'group_label' => 'Licencia de conducción',
                'side' => 'front',
                'validate' => true,
            ],
            [
                'key' => 'licencia_back',
                'label' => 'Licencia · reverso',
                'hint' => 'Encaja el reverso de la licencia (categorías).',
                'type' => 'licencia',
                'analyze' => true,
                'camera' => true,
                'icon' => 'licencia',
                'frame' => 'id-card',
                'group' => 'licencia',
                'group_label' => 'Licencia de conducción',
                'side' => 'back',
                'validate' => true,
            ],
            [
                'key' => 'tarjeta_propiedad_front',
                'label' => 'Tarjeta de propiedad · frente',
                'hint' => 'Encaja el frente de la tarjeta. Vamos a leer placa y datos del vehículo.',
                'type' => 'tarjeta-propiedad',
                'analyze' => true,
                'camera' => true,
                'icon' => 'tarjeta',
                'frame' => 'id-card',
                'group' => 'tarjeta',
                'group_label' => 'Tarjeta de propiedad',
                'side' => 'front',
                'validate' => true,
            ],
            [
                'key' => 'tarjeta_propiedad_back',
                'label' => 'Tarjeta de propiedad · reverso',
                'hint' => 'Encaja el reverso de la tarjeta de propiedad.',
                'type' => 'tarjeta-propiedad',
                'analyze' => true,
                'camera' => true,
                'icon' => 'tarjeta',
                'frame' => 'id-card',
                'group' => 'tarjeta',
                'group_label' => 'Tarjeta de propiedad',
                'side' => 'back',
                'validate' => true,
            ],
            [
                'key' => 'photo',
                'label' => 'Foto del registro',
                'hint' => 'Esta foto queda solo en la ficha de inscripción. La foto del usuario la coloca la administración.',
                'type' => null,
                'analyze' => false,
                'camera' => true,
                'icon' => 'photo',
                'frame' => 'portrait',
                'group' => 'photo',
                'group_label' => 'Foto del registro',
                'side' => null,
                'validate' => false,
            ],
            [
                'key' => 'soat',
                'label' => 'SOAT',
                'hint' => 'Toma una foto o adjunta el archivo del SOAT.',
                'type' => 'soat',
                'analyze' => false,
                'camera' => true,
                'icon' => 'soat',
                'frame' => 'sheet',
                'group' => 'soat',
                'group_label' => 'SOAT',
                'side' => null,
                'validate' => true,
            ],
            [
                'key' => 'tecnomecanica',
                'label' => 'Tecnomecánica',
                'hint' => 'Toma una foto o adjunta el archivo de la tecnomecánica.',
                'type' => 'tecnomecanica',
                'analyze' => false,
                'camera' => true,
                'icon' => 'tecnomecanica',
                'frame' => 'sheet',
                'group' => 'tecnomecanica',
                'group_label' => 'Tecnomecánica',
                'side' => null,
                'validate' => true,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, hint: string, type: string|null, analyze: bool, camera: bool, icon: string, frame: string, group: string, group_label: string, side: string|null, validate: bool}>
     */
    public static function forRole(?string $role): array
    {
        if ($role === 'passenger') {
            return array_values(array_filter(
                self::all(),
                fn (array $step) => $step['group'] === 'cedula'
            ));
        }

        return self::all();
    }

    /**
     * @return array<int, string>
     */
    public static function keys(?string $role = null): array
    {
        return array_column($role === null ? self::all() : self::forRole($role), 'key');
    }

    /**
     * @return array{key: string, label: string, hint: string, type: string|null, analyze: bool, camera: bool, icon: string, frame: string, group: string, group_label: string, side: string|null, validate: bool}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $step) {
            if ($step['key'] === $key) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, hint: string, type: string|null, analyze: bool, camera: bool, icon: string, frame: string, group: string, group_label: string, side: string|null, validate: bool}|null
     */
    public static function mate(string $key): ?array
    {
        $step = self::find($key);

        if (! $step || $step['side'] === null) {
            return null;
        }

        $wanted = $step['side'] === 'front' ? 'back' : 'front';

        foreach (self::all() as $candidate) {
            if ($candidate['group'] === $step['group'] && $candidate['side'] === $wanted) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $uploads
     * @return array{key: string, label: string, hint: string, type: string|null, analyze: bool, camera: bool, icon: string, frame: string, group: string, group_label: string, side: string|null, validate: bool}|null
     */
    public static function nextIncomplete(array $uploads, ?string $role = null): ?array
    {
        foreach (self::forRole($role) as $step) {
            if (empty($uploads[$step['key']])) {
                return $step;
            }
        }

        return null;
    }
}
