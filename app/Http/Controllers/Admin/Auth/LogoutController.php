<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        auth()->logout();

        if ($userId) {
            \App\Models\PosCartReservation::releaseForUser($userId);
        }
        $request->session()->forget('pos_cart');

        return redirect()->route('login');
    }
}
