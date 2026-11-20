<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\BirthdayNotice;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BirthdayUpcomingNotification;
use App\Services\Permissions\PermissionService;
use App\Support\AlertSchedule;
use App\Support\BirthdayAlertRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BirthdayNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_club_saves_its_own_birthday_window(): void
    {
        [$club, $director] = $this->club('amm-cumple');
        $club->forceFill([
            'settings' => [
                'city_note' => 'conservar',
                'document_alerts' => ['licencia' => 90],
            ],
        ])->save();

        $this->actingAs($director)
            ->get("/app/{$club->slug}/settings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('birthdayAlertDays', 7)
                ->where('alertTime', '07:00'));

        $this->actingAs($director)
            ->put("/app/{$club->slug}/settings/cumpleanos", [
                'days' => 14,
                'alert_time' => '09:15',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $club->refresh();
        $this->assertSame('conservar', $club->settings['city_note']);
        $this->assertSame(90, $club->settings['document_alerts']['licencia']);
        $this->assertSame(14, $club->settings['birthday_alerts']['days']);
        $this->assertSame('09:15', $club->settings['alert_time']);

        $member = $this->memberOf($club, 'miembro-cumple@amm.test', '1990-01-02');

        $this->actingAs($member->users()->first())
            ->put("/app/{$club->slug}/settings/cumpleanos", ['days' => 30])
            ->assertForbidden();

        $this->assertSame(14, $club->refresh()->settings['birthday_alerts']['days']);
    }

    public function test_birthdays_notify_only_the_board_once(): void
    {
        Notification::fake();
        $this->travelTo('2026-12-28');

        [$club, $staff, $member] = $this->clubWithBoard('amm-cumpleanos');
        $this->memberOf($club, 'lejos@amm.test', '1990-01-20');
        $this->memberOf($club, 'sin-fecha@amm.test', null);
        $this->memberOf($club, 'inactivo@amm.test', '1990-01-03', PersonStatus::Inactive);
        $this->memberOf($club, 'pendiente@amm.test', '1990-01-03', PersonStatus::Active, MembershipStatus::Pending);

        [$otherClub, $otherDirector] = $this->club('amm-otro-cumple');
        $this->memberOf($otherClub, 'otro-miembro@amm.test', '1990-01-02', PersonStatus::Active, MembershipStatus::Active, 'Ana', 'Diaz');

        $this->artisan('birthdays:notify')->assertSuccessful();
        $this->artisan('birthdays:notify')->assertSuccessful();

        foreach (['director', 'subdirector', 'secretario', 'capellan', 'tesorero'] as $slug) {
            Notification::assertSentToTimes($staff[$slug], BirthdayUpcomingNotification::class, 1);
        }

        Notification::assertSentTo(
            $staff['director'],
            BirthdayUpcomingNotification::class,
            function (BirthdayUpcomingNotification $notification) use ($staff, $member): bool {
                $data = $notification->toArray($staff['director']);

                return $data['person_id'] === $member->id
                    && str_contains($data['body'], 'Luis Castro')
                    && str_contains($data['body'], '2 de enero')
                    && str_starts_with($data['url'], '/app/');
            }
        );

        Notification::assertNotSentTo($member->users()->first(), BirthdayUpcomingNotification::class);
        Notification::assertSentToTimes($otherDirector, BirthdayUpcomingNotification::class, 1);
        $this->assertDatabaseCount('birthday_notices', 2);
    }

    public function test_scheduled_notices_wait_until_the_club_time(): void
    {
        Notification::fake();
        $this->travelTo('2026-12-28 06:59:00');

        [$club, $staff] = $this->clubWithBoard('amm-hora');
        app(AlertSchedule::class)->save($club, '07:00');

        $this->artisan('birthdays:notify --due')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo('2026-12-28 07:00:00');
        $this->artisan('birthdays:notify --due')->assertSuccessful();
        Notification::assertSentTo($staff['director'], BirthdayUpcomingNotification::class);
    }

    public function test_a_board_member_can_turn_birthday_alerts_off(): void
    {
        Notification::fake();
        $this->travelTo('2026-12-28');

        [$club, $staff] = $this->clubWithBoard('amm-silencio-cumple');

        $this->actingAs($staff['secretario'])
            ->put("/app/{$club->slug}/avisos-personales", [
                'documents' => true,
                'birthdays' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->artisan('birthdays:notify')->assertSuccessful();

        Notification::assertNotSentTo($staff['secretario'], BirthdayUpcomingNotification::class);
        Notification::assertSentTo($staff['director'], BirthdayUpcomingNotification::class);
        Notification::assertSentTo($staff['capellan'], BirthdayUpcomingNotification::class);
    }

    public function test_a_longer_window_includes_a_later_birthday_and_leap_day_uses_february_28(): void
    {
        Notification::fake();
        $this->travelTo('2026-12-28');

        [$club] = $this->clubWithBoard('amm-plazo', '1990-06-01');
        $later = $this->memberOf($club, 'tarde@amm.test', '1990-01-10');

        $this->artisan('birthdays:notify')->assertSuccessful();
        Notification::assertNotSentTo($later->users()->first(), BirthdayUpcomingNotification::class);
        $this->assertDatabaseCount('birthday_notices', 0);

        app(BirthdayAlertRules::class)->save($club, 20);

        $this->artisan('birthdays:notify')->assertSuccessful();
        $this->assertDatabaseCount('birthday_notices', 1);

        $this->travelTo('2027-02-21');
        [$leapClub] = $this->clubWithBoard('amm-bisiesto');
        $leap = $this->memberOf($leapClub, 'bisiesto@amm.test', '2000-02-29', PersonStatus::Active, MembershipStatus::Active, 'Marta', 'Leon');

        $this->artisan('birthdays:notify')->assertSuccessful();

        $this->assertTrue(
            BirthdayNotice::query()
                ->where('organization_id', $leapClub->id)
                ->where('person_id', $leap->id)
                ->whereDate('occurs_on', '2027-02-28')
                ->exists()
        );
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function club(string $slug): array
    {
        app(PermissionService::class)->syncCatalog();

        $club = Organization::factory()->create(['slug' => $slug]);
        app(PermissionService::class)->createOrganizationRoles($club);
        $director = User::factory()->create();
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'director')->firstOrFail();
        app(PermissionService::class)->assignRole($club, $director, $role);

        return [$club, $director];
    }

    /**
     * @return array{0: Organization, 1: array<string, User>, 2: Person}
     */
    private function clubWithBoard(string $slug, string $memberBirthDate = '1990-01-02'): array
    {
        [$club] = $this->club($slug);
        $permissions = app(PermissionService::class);
        $staff = [];

        foreach (['director', 'subdirector', 'secretario', 'capellan', 'tesorero'] as $roleSlug) {
            if ($roleSlug === 'director') {
                $staff[$roleSlug] = User::query()
                    ->whereHas('organizationRoles', fn ($query) => $query->where('organization_id', $club->id))
                    ->firstOrFail();

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
            $this->memberOf($club, "miembro-{$slug}@amm.test", $memberBirthDate, PersonStatus::Active, MembershipStatus::Active, 'Luis', 'Castro'),
        ];
    }

    private function memberOf(
        Organization $club,
        string $email,
        ?string $birthDate,
        PersonStatus $status = PersonStatus::Active,
        MembershipStatus $membership = MembershipStatus::Active,
        string $firstName = 'Socio',
        string $lastName = 'Prueba',
    ): Person {
        $person = Person::factory()->create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $birthDate,
            'status' => $status,
        ]);
        $person->memberships()->create([
            'organization_id' => $club->id,
            'status' => $membership,
            'joined_at' => '2020-01-01',
        ]);
        $user = User::factory()->create(['email' => $email, 'person_id' => $person->id]);
        $role = Role::query()->where('organization_id', $club->id)->where('slug', 'miembro')->firstOrFail();
        app(PermissionService::class)->assignRole($club, $user, $role);

        return $person->fresh('users');
    }
}
