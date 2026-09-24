<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Cédula', 'slug' => 'cedula', 'entity_type' => 'person', 'requires_expiration' => false, 'icon' => 'id', 'color' => '#F59E0B'],
            ['name' => 'Licencia de conducción', 'slug' => 'licencia', 'entity_type' => 'person', 'requires_expiration' => true, 'icon' => 'license', 'color' => '#EA580C'],
            ['name' => 'EPS', 'slug' => 'eps', 'entity_type' => 'person', 'requires_expiration' => false, 'icon' => 'health', 'color' => '#22C55E'],
            ['name' => 'SOAT', 'slug' => 'soat', 'entity_type' => 'motorcycle', 'requires_expiration' => true, 'icon' => 'shield', 'color' => '#38BDF8'],
            ['name' => 'Técnico-mecánica', 'slug' => 'tecnomecanica', 'entity_type' => 'motorcycle', 'requires_expiration' => true, 'icon' => 'wrench', 'color' => '#A78BFA'],
            ['name' => 'Tarjeta de propiedad', 'slug' => 'tarjeta-propiedad', 'entity_type' => 'motorcycle', 'requires_expiration' => false, 'icon' => 'file', 'color' => '#F472B6'],
        ];

        foreach ($types as $type) {
            DocumentType::query()->updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
