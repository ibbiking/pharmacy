<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        if ($userId) {
            \App\Models\PosCartReservation::releaseForUser($userId);
        }
        auth()->logout();
        // Security review 13e: end the session completely (shared tills: nothing carries over to the next person).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
