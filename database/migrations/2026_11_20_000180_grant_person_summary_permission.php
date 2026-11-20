<?php

use App\Models\Permission;
use App\Services\Permissions\PermissionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = app(PermissionService::class);
        $permissions->syncCatalog();
        $permissions->grantDefaultPermission('people.summary.update');
    }

    public function down(): void
    {
        $permission = Permission::query()->where('slug', 'people.summary.update')->first();

        if ($permission === null) {
            return;
        }

        $permission->roles()->detach();
        $permission->delete();
    }
};
