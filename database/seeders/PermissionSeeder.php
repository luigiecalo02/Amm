<?php

namespace Database\Seeders;

use App\Services\Permissions\PermissionService;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(PermissionService $permissions): void
    {
        $permissions->syncCatalog();
    }
}
