<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()?->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $url = $item->data['url'] ?? '/';

        if (! is_string($url) || ! str_starts_with($url, '/')) {
            $url = '/';
        }

        return redirect()->to($url);
    }
}
