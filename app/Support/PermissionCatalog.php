<?php

namespace App\Support;

final class PermissionCatalog
{
    /**
     * @return array<int, array{name: string, slug: string, module: string, description: string}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'Ver personas', 'slug' => 'people.view', 'module' => 'people', 'description' => 'Consultar personas del club'],
            ['name' => 'Crear personas', 'slug' => 'people.create', 'module' => 'people', 'description' => 'Registrar nuevas personas'],
            ['name' => 'Editar personas', 'slug' => 'people.update', 'module' => 'people', 'description' => 'Actualizar datos de personas'],
            ['name' => 'Eliminar personas', 'slug' => 'people.delete', 'module' => 'people', 'description' => 'Eliminar personas'],

            ['name' => 'Ver documentos', 'slug' => 'documents.view', 'module' => 'documents', 'description' => 'Consultar documentos'],
            ['name' => 'Crear documentos', 'slug' => 'documents.create', 'module' => 'documents', 'description' => 'Registrar documentos'],
            ['name' => 'Editar documentos', 'slug' => 'documents.update', 'module' => 'documents', 'description' => 'Actualizar documentos'],
            ['name' => 'Eliminar documentos', 'slug' => 'documents.delete', 'module' => 'documents', 'description' => 'Eliminar documentos'],
            ['name' => 'Escanear documentos', 'slug' => 'documents.scan', 'module' => 'documents', 'description' => 'Subir escaneos para revisión'],
            ['name' => 'Descargar documentos', 'slug' => 'documents.download', 'module' => 'documents', 'description' => 'Descargar archivos privados'],

            ['name' => 'Ver motocicletas', 'slug' => 'motorcycles.view', 'module' => 'motorcycles', 'description' => 'Consultar motocicletas'],
            ['name' => 'Crear motocicletas', 'slug' => 'motorcycles.create', 'module' => 'motorcycles', 'description' => 'Registrar motocicletas'],
            ['name' => 'Editar motocicletas', 'slug' => 'motorcycles.update', 'module' => 'motorcycles', 'description' => 'Actualizar motocicletas'],
            ['name' => 'Eliminar motocicletas', 'slug' => 'motorcycles.delete', 'module' => 'motorcycles', 'description' => 'Eliminar motocicletas'],

            ['name' => 'Ver miembros', 'slug' => 'members.view', 'module' => 'members', 'description' => 'Consultar membresías'],
            ['name' => 'Crear miembros', 'slug' => 'members.create', 'module' => 'members', 'description' => 'Vincular personas al club'],
            ['name' => 'Editar miembros', 'slug' => 'members.update', 'module' => 'members', 'description' => 'Actualizar membresías'],
            ['name' => 'Eliminar miembros', 'slug' => 'members.delete', 'module' => 'members', 'description' => 'Retirar miembros'],

            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'module' => 'users', 'description' => 'Consultar usuarios del club'],
            ['name' => 'Crear usuarios', 'slug' => 'users.create', 'module' => 'users', 'description' => 'Crear cuentas de acceso'],
            ['name' => 'Editar usuarios', 'slug' => 'users.update', 'module' => 'users', 'description' => 'Actualizar usuarios'],
            ['name' => 'Eliminar usuarios', 'slug' => 'users.delete', 'module' => 'users', 'description' => 'Deshabilitar usuarios'],

            ['name' => 'Ver roles', 'slug' => 'roles.view', 'module' => 'roles', 'description' => 'Consultar roles'],
            ['name' => 'Crear roles', 'slug' => 'roles.create', 'module' => 'roles', 'description' => 'Crear roles del club'],
            ['name' => 'Editar roles', 'slug' => 'roles.update', 'module' => 'roles', 'description' => 'Modificar roles y permisos'],
            ['name' => 'Eliminar roles', 'slug' => 'roles.delete', 'module' => 'roles', 'description' => 'Eliminar roles no de sistema'],

            ['name' => 'Ver organización', 'slug' => 'organization.view', 'module' => 'organization', 'description' => 'Ver datos del club'],
            ['name' => 'Editar organización', 'slug' => 'organization.update', 'module' => 'organization', 'description' => 'Actualizar el club'],

            ['name' => 'Ver reportes', 'slug' => 'reports.view', 'module' => 'reports', 'description' => 'Consultar vencimientos y reportes'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_column(self::all(), 'slug');
    }

    /**
     * @return array<int, string>
     */
    public static function directorDefaults(): array
    {
        return self::slugs();
    }

    /**
     * @return array<int, string>
     */
    public static function secretaryDefaults(): array
    {
        return array_values(array_filter(
            self::slugs(),
            fn (string $slug): bool => ! in_array($slug, ['roles.delete', 'users.delete', 'organization.update'], true)
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function treasurerDefaults(): array
    {
        return [
            'people.view',
            'members.view',
            'documents.view',
            'documents.download',
            'motorcycles.view',
            'reports.view',
            'organization.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function memberDefaults(): array
    {
        return [
            'people.view',
            'documents.view',
            'motorcycles.view',
            'organization.view',
        ];
    }
}
