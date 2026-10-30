<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;

class GoogleAuthService
{
    public function upsertFromGoogle(GoogleUser $googleUser): User
    {
        $user = User::query()
            ->where(function ($query) use ($googleUser): void {
                $query->where('google_id', $googleUser->getId())
                    ->orWhere('email', $googleUser->getEmail());
            })
            ->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $user;
        }

        return User::query()->create([
            'name' => $googleUser->getName() ?: ($googleUser->getEmail() ?? 'Motociclista AMM'),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array{first_name: string, last_name: string}
     */
    public function splitName(?string $fullName): array
    {
        $parts = collect(preg_split('/\s+/', trim((string) $fullName) ?: ''))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return ['first_name' => '', 'last_name' => ''];
        }

        return [
            'first_name' => $parts->first(),
            'last_name' => $parts->slice(1)->implode(' ') ?: $parts->first(),
        ];
    }
}
