<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifications internes (canal base de données). Chaque utilisateur ne voit que les siennes.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    public function ouvrir(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $lien = $notification->data['lien'] ?? null;
        // Seuls les liens internes à l'application sont suivis.
        if (! $lien || ! str_starts_with($lien, url('/'))) {
            return redirect()->route('notifications.index');
        }

        return redirect()->to($lien);
    }

    public function toutLire(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('succes', 'Toutes les notifications sont marquées comme lues.');
    }
}
