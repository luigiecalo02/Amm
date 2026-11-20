<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $organization = $this->resolveOrganization($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_platform_admin' => $user->isSuperAdmin(),
                ] : null,
            ],
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'city' => $organization->city,
            ] : null,
            'current_role' => $this->resolveCurrentRole($user, $organization),
            'can_switch_clubs' => $user?->canSwitchClubs() ?? false,
            'permissions' => $user && $organization ? $user->permissionSlugs($organization) : [],
            'googleEnabled' => filled(config('services.google.client_id')),
            'vapidPublicKey' => config('services.webpush.public_key'),
            'notifications' => $this->notifications($user),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'analyzed' => $request->session()->get('analyzed'),
            ],
        ];
    }

    /**
     * El contexto se setea en un middleware posterior; aquí leemos la ruta
     * para que Vue nunca reciba organization = null dentro de /app/{club}.
     */
    private function resolveOrganization(Request $request): ?Organization
    {
        $context = app(OrganizationContext::class);

        if ($context->current()) {
            return $context->current();
        }

        $routed = $request->route('organization');

        if ($routed instanceof Organization) {
            $context->set($routed);

            return $routed;
        }

        if (is_string($routed) && $routed !== '') {
            $organization = Organization::query()->where('slug', $routed)->first();

            if ($organization) {
                $context->set($organization);
            }

            return $organization;
        }

        return null;
    }

    /**
     * @return array{unread_count: int, items: list<array{id: string, title: string, body: string, url: string}>}
     */
    private function notifications(?User $user): array
    {
        if (! $user) {
            return ['unread_count' => 0, 'items' => []];
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => (string) $notification->id,
                    'title' => (string) ($notification->data['title'] ?? 'Aviso'),
                    'body' => (string) ($notification->data['body'] ?? ''),
                    'url' => (string) ($notification->data['url'] ?? '/'),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{name: string, scope: string}|null
     */
    private function resolveCurrentRole(?User $user, ?Organization $organization): ?array
    {
        if (! $user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return [
                'name' => 'Superadministrador',
                'scope' => 'platform',
            ];
        }

        if (! $organization) {
            return null;
        }

        $role = $user->roleIn($organization);

        if (! $role) {
            return null;
        }

        return [
            'name' => $role->name,
            'scope' => 'organization',
        ];
    }
}
