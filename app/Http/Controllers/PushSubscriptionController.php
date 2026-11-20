<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\RedirectResponse;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $endpoint = $request->string('endpoint')->toString();

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'public_key' => $request->string('public_key')->toString(),
                'auth_token' => $request->string('auth_token')->toString(),
                'content_encoding' => $request->string('content_encoding')->toString() ?: 'aes128gcm',
            ]
        );

        return back()->with('success', 'Avisos activados en este dispositivo.');
    }

    public function destroy(DestroyPushSubscriptionRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($request->string('endpoint')->toString()))
            ->delete();

        return back()->with('success', 'Avisos desactivados en este dispositivo.');
    }
}
