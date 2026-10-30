<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): SymfonyRedirect|RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()
                ->route('login')
                ->with('error', 'Google aún no está configurado. Pide al director las credenciales OAuth.');
        }

        $request->session()->put('google_club', $request->string('club')->toString() ?: 'amm-cartagena');

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request, GoogleAuthService $googleAuth): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->with('error', 'No se pudo completar el acceso con Google. Inténtalo de nuevo.');
        }

        $user = $googleAuth->upsertFromGoogle($googleUser);
        Auth::login($user, true);
        $request->session()->regenerate();

        if ($user->isSuperAdmin()) {
            return redirect()->route('organizations.index');
        }

        if (! $user->person_id && ! $user->organizationRoles()->exists()) {
            return redirect()->route('register.member');
        }

        $organization = $user->currentOrganization
            ?? $user->organizationRoles()->with('organization')->first()?->organization;

        return $organization
            ? redirect()->route('dashboard', $organization)
            : redirect()->route('organizations.index');
    }
}
