<?php

namespace App\Providers;

use App\Events\DocumentCreated;
use App\Events\DocumentDownloaded;
use App\Events\MemberAdded;
use App\Events\PersonCreated;
use App\Events\UserAssignedToOrganization;
use App\Listeners\WriteAuditLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Person;
use App\Policies\DocumentPolicy;
use App\Policies\MotorcyclePolicy;
use App\Policies\OrganizationMemberPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PersonPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\Documents\DocumentValidationService;
use App\Services\OCR\NullOcrProvider;
use App\Services\OCR\OcrProviderInterface;
use App\Services\Permissions\PermissionService;
use App\Support\OrganizationContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrganizationContext::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(DocumentValidationService::class);
        $this->app->bind(OcrProviderInterface::class, NullOcrProvider::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'organization' => Organization::class,
            'person' => Person::class,
            'member' => OrganizationMember::class,
            'document' => Document::class,
        ]);

        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(OrganizationMember::class, OrganizationMemberPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(\App\Models\Motorcycle::class, MotorcyclePolicy::class);
        Gate::policy(\App\Models\Role::class, RolePolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);

        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
                return true;
            }

            return null;
        });

        Event::listen([
            PersonCreated::class,
            MemberAdded::class,
            DocumentCreated::class,
            DocumentDownloaded::class,
            UserAssignedToOrganization::class,
        ], WriteAuditLog::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        $this->registerTenantBindings();
    }

    private function registerTenantBindings(): void
    {
        $resolveOrganization = function ($route): ?Organization {
            $organization = $route->parameter('organization');

            if ($organization instanceof Organization) {
                return $organization;
            }

            if (is_string($organization)) {
                return Organization::query()->where('slug', $organization)->first();
            }

            return null;
        };

        Route::bind('person', function (string $value, $route) use ($resolveOrganization) {
            $organization = $resolveOrganization($route);

            return Person::query()
                ->when($organization, fn ($query) => $query->inOrganization($organization->id))
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('document', function (string $value, $route) use ($resolveOrganization) {
            $organization = $resolveOrganization($route);

            return \App\Models\Document::query()
                ->when($organization, fn ($query) => $query->inOrganization($organization->id))
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('role', function (string $value, $route) use ($resolveOrganization) {
            $organization = $resolveOrganization($route);

            return \App\Models\Role::query()
                ->when($organization, fn ($query) => $query->forOrganization($organization->id))
                ->whereKey($value)
                ->firstOrFail();
        });
    }
}
