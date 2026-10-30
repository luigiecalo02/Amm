<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if ($user && ! $user->isSuperAdmin() && ! $user->person_id && ! $user->organizationRoles()->exists()) {
            return redirect()->route('register.member');
        }

        if ($user?->isSuperAdmin()) {
            return redirect()->route('organizations.index');
        }

        $organization = $user?->currentOrganization
            ?? $user?->organizationRoles()->with('organization')->first()?->organization;

        if ($organization) {
            return redirect()->route('dashboard', $organization);
        }

        return redirect()->route('organizations.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
