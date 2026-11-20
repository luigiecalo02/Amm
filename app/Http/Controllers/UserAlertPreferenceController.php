<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\UpdateUserAlertPreferencesRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\UserAlertPreferences;
use Illuminate\Http\RedirectResponse;

class UserAlertPreferenceController extends Controller
{
    public function __construct(private readonly UserAlertPreferences $preferences) {}

    public function update(UpdateUserAlertPreferencesRequest $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->preferences->save(
            $user,
            $organization,
            $request->boolean('documents'),
            $request->boolean('birthdays'),
        );

        $referer = $request->headers->get('referer');
        $stayHere = is_string($referer) && str_contains($referer, '/app/'.$organization->slug);

        return redirect()
            ->to($stayHere ? $referer : route('organizations.edit', $organization))
            ->with('success', 'Tus avisos quedaron actualizados.');
    }
}
