<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Document;
use App\Models\DocumentExpirationNotice;
use App\Models\DocumentType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentExpiringNotification;
use App\Services\Permissions\PermissionService;
use App\Support\DocumentAlertRules;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentExpirationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_club_saves_its_own_alert_window(): void
    {
        [$club, $director] = $this->club('amm-avisos');
        $club->forceFill(['settings' => ['city_note' => 'conservar']])->save();

        $this->actingAs($director)
            ->put("/app/{$club->slug}/settings/avisos", [
                'licencia' => 120,
                'soat' => 10,
                'tecnomecanica' => 45,
                'alert_time' => '18:30',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $club->refresh();
        $this->assertSame('conservar', $club->settings['city_note']);
        $this->assertSame(120, $club->settings['document_alerts']['licencia']);
        $this->assertSame(10, $club->settings['document_alerts']['soat']);
        $this->assertSame(45, $club->settings['document_alerts']['tecnomecanica']);
        $this->assertSame('18:30', $club->settings['alert_time']);

        $this->actingAs($director)
            ->get("/app/{$club->slug}/settings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('documentAlerts.0.days', 120)
                ->where('documentAlerts.1.days', 10)
                ->where('documentAlerts.2.days', 45)
                ->where('alertTime', '18:30')
                ->where('documentAlertInterval', 1)
                ->where('alertPreferences.documents', true)
                ->where('alertPreferences.birthdays', true));
    }

    public function test_club_notice_settings_save_days_and_time_together(): void
    {
        [$club, $director] = $this->club('amm-avisos-juntos');
        $club->forceFill(['settings' => ['city_note' => 'conservar']])->save();

        $this->actingAs($director)
            ->put("/app/{$club->slug}/settings/avisos-club", [
                'licencia' => 60,
                'soat' => 15,
                'tecnomecanica' => 20,
                'birthday_days' => 10,
                'repeat_every' => 2,
                'alert_time' => '08:45',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $club->refresh();
        $this->assertSame('conservar', $club->settings['city_note']);
        $this->assertSame(60, $club->settings['document_alerts']['licencia']);
        $this->assertSame(15, $club->settings['document_alerts']['soat']);
        $this->assertSame(20, $club->settings['document_alerts']['tecnomecanica']);
        $this->assertSame(2, $club->settings['document_alerts']['repeat_every']);
        $this->assertSame(10, $club->settings['birthday_alerts']['days']);
        $this->assertSame('08:45', $club->settings['alert_time']);

        $member = $this->memberOf($club, 'socio-avisos@amm.test');
        $this->actingAs($member->users()->first())
            ->put("/app/{$club->slug}/settings/avisos-club", [
                'licencia' => 1,
                'soat' => 1,
                'tecnomecanica' => 1,
                'birthday_days' => 1,
            ])
            ->assertForbidden();
    }

    public function test_a_recipient_can_turn_document_alerts_off(): void
    {
        Notification::fake();

        [$club, $staff, $member] = $this->clubWithStaff('amm-silencio');
        app(DocumentAlertRules::class)->save($club, [
            'licencia' => 90,
            'soat' => 30,
            'tecnomecanica' => 30,
        ]);
        $this->document($member, 'licencia', now()->addDays(10)->toDateString());

        $memberUser = $member->users()->first();
        $this->actingAs($memberUser)
            ->put("/app/{$club->slug}/avisos-personales", [
                'documents' => false,
                'birthdays' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($memberUser)
            ->get("/app/{$club->slug}/settings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('alertPreferences.documents', false)
                ->where('alertPreferences.birthdays', true));

        $this->artisan('documents:notify-expiring')->assertSuccessful();

        Notification::assertNotSentTo($memberUser, DocumentExpiringNotification::class);
        Notification::assertSentTo($staff['director'], DocumentExpiringNotification::class);
        Notification::assertSentTo($staff['tesorero'], DocumentExpiringNotification::class);
    }

    public function test_reminders_repeat_until_the_document_expires_or_is_updated(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-20 08:00:00');

        [$club, $staff, $member] = $this->clubWithStaff('amm-repite');
        app(DocumentAlertRules::class)->save($club, [
            'licencia' => 90,
            'soat' => 30,
            'tecnomecanica' => 30,
            'repeat_every' => 2,
        ]);

        $document = $this->document($member, 'licencia', '2026-11-25');
        $director = $staff['director'];

        $this->artisan('documents:notify-expiring')->assertSuccessful();
        Notification::assertSentToTimes($director, DocumentExpiringNotification::class, 1);

        $this->travelTo('2026-11-21 08:00:00');
        $this->artisan('documents:notify-expiring')->assertSuccessful();
        Notification::assertSentToTimes($director, DocumentExpiringNotification::class, 1);

        $this->travelTo('2026-11-22 08:00:00');
        $this->artisan('documents:notify-expiring')->assertSuccessful();
        Notification::assertSentToTimes($director, DocumentExpiringNotification::class, 2);

        $document->update(['document_number' => 'ACTUALIZADA']);

        $this->travelTo('2026-11-24 08:00:00');
        $this->artisan('documents:notify-expiring')->assertSuccessful();
        Notification::assertSentToTimes($director, DocumentExpiringNotification::class, 2);
        $this->assertSame(1, DocumentExpirationNotice::query()->where('document_id', $document->id)->count());

        $document->update(['expiration_date' => '2026-11-28']);
        $this->artisan('documents:notify-expiring')->assertSuccessful();
        Notification::assertSentToTimes($director, DocumentExpiringNotification::class, 3);

        $this->travelTo('2026-11-29 08:00:00');
        $this->artisan('documents:notify-expiring')->assertSuccessful();
        Notification::assertSentToTimes($director, DocumentExpiringNotification::class, 3);
    }

    public function test_expiring_documents_notify_staff_and_the_member_once(): void
    {
        Notification::fake();

        [$club, $staff, $member, $outsider] = $this->clubWithStaff('amm-vence');
        app(DocumentAlertRules::class)->save($club, [
            'licencia' => 90,
            'soat' => 30,
            'tecnomecanica' => 30,
        ]);

        $this->document($member, 'licencia', now()->addDays(80)->toDateString());
        $this->document($member, 'licencia', now()->addDays(100)->toDateString());
        $this->document($member, 'licencia', null);
        $motorcycle = $member->motorcycles()->create([
            'plate' => 'ABC12D',
            'brand' => 'Honda',
            'model' => 'XR',
        ]);
        $soat = DocumentType::query()->where('slug', 'soat')->firstOrFail();
        $tecnomecanica = DocumentType::query()->where('slug', 'tecnomecanica')->firstOrFail();
        Document::query()->create([
            'document_type_id' => $soat->id,
            'motorcycle_id' => $motorcycle->id,
            'expiration_date' => now()->addDays(20)->toDateString(),
        ]);
        Document::query()->create([
            'document_type_id' => $soat->id,
            'motorcycle_id' => $motorcycle->id,
            'expiration_date' => now()->addDays(40)->toDateString(),
        ]);
        Document::query()->create([
            'document_type_id' => $tecnomecanica->id,
            'motorcycle_id' => $motorcycle->id,
            'expiration_date' => now()->addDays(40)->toDateString(),
        ]);

        [$otherClub, $otherDirector] = $this->club('amm-otro');
        $otherPerson = $this->memberOf($otherClub, 'otro-miembro@amm.test');
        $this->document($otherPerson, 'licencia', now()->addDays(10)->toDateString());

        $this->artisan('documents:notify-expiring')->assertSuccessful();
        $this->artisan('documents:notify-expiring')->assertSuccessful();

        foreach (['director', 'subdirector', 'secretario', 'tesorero'] as $slug) {
            Notification::assertSentToTimes($staff[$slug], DocumentExpiringNotification::class, 2);
        }

        Notification::assertSentToTimes($member->users()->first(), DocumentExpiringNotification::class, 2);
        Notification::assertNotSentTo($outsider->users()->first(), DocumentExpiringNotification::class);
        Notification::assertSentToTimes($otherDirector, DocumentExpiringNotification::class, 1);
        $this->assertDatabaseCount('document_expiration_notices', 3);
    }

    public function test_a_push_subscription_is_removed_on_logout(): void
    {
        [$club, $director] = $this->club('amm-push');

        $this->actingAs($director)
            ->post('/push/suscripcion', [
                'endpoint' => 'https://push.example.test/subscription/1',
                'public_key' => str_repeat('a', 80),
                'auth_token' => 'auth-token',
                'content_encoding' => 'aes128gcm',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $director->id,
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/1'),
        ]);

        $this->actingAs($director)->post('/logout')->assertRedirect();

        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $director->id]);
        unset($club);
    }

    public function test_a_push_subscription_can_be_removed_without_logging_out(): void
    {
        [$club, $director] = $this->club('amm-push-off');

        $this->actingAs($director)
            ->post('/push/suscripcion', [
                'endpoint' => 'https://push.example.test/subscription/2',
                'public_key' => str_repeat('b', 80),
                'auth_token' => 'auth-token-2',
                'content_encoding' => 'aes128gcm',
            ])
            ->assertRedirect();

        $this->actingAs($director)
            ->delete('/push/suscripcion', [
                'endpoint' => 'https://push.example.test/subscription/2',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $director->id,
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/2'),
        ]);
        unset($club);
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function club(string $slug): array
    {
        $this->prepareCatalog();

        $club = Organization::factory()->create(['slug' => $slug]);
        app(PermissionService::class)->createOrganizationRoles($club);
        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        app(PermissionService::class)->assignRole($club, $director, $role);

        return [$club, $director];
    }

    /**
     * @return array{0: Organization, 1: array<string, User>, 2: Person, 3: Person}
     */
    private function clubWithStaff(string $slug): array
    {
        [$club] = $this->club($slug);
        $permissions = app(PermissionService::class);
        $staff = [];

        foreach (['director', 'subdirector', 'secretario', 'tesorero'] as $roleSlug) {
            if ($roleSlug === 'director') {
                $staff[$roleSlug] = User::query()->whereHas('organizationRoles', fn ($query) => $query->where('organization_id', $club->id))->firstOrFail();

                continue;
            }

            $user = User::factory()->create();
            $role = Role::query()->where('organization_id', $club->id)->where('slug', $roleSlug)->firstOrFail();
            $permissions->assignRole($club, $user, $role);
            $staff[$roleSlug] = $user;
        }

        return [
            $club,
            $staff,
            $this->memberOf($club, 'miembro@amm.test'),
            $this->memberOf($club, 'otro@amm.test'),
        ];
    }

    private function memberOf(Organization $club, string $email): Person
    {
        $person = Person::factory()->create(['email' => $email]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
        ]);
        $user = User::factory()->create(['email' => $email, 'person_id' => $person->id]);
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        app(PermissionService::class)->assignRole($club, $user, $role);

        return $person->fresh('users');
    }

    private function document(Person $person, string $slug, ?string $expiration): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('slug', $slug)->firstOrFail()->id,
            'person_id' => $person->id,
            'expiration_date' => $expiration,
        ]);
    }

    private function prepareCatalog(): void
    {
        if (DocumentType::query()->exists()) {
            return;
        }

        $this->seed(DocumentTypeSeeder::class);
        app(PermissionService::class)->syncCatalog();
    }
}
