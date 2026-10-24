<?php

namespace App\Http\Controllers\GossipSlip;

use App\Http\Controllers\Controller;
use App\Models\GossipSlip\GsAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('gs_admin_id')) {
            return redirect()->route('gs.admin.dashboard');
        }
        return view('gossip-slip.admin.login', ['title' => 'Admin Login — Gossip Slip']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = GsAdmin::where('username', $request->username)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return back()
                ->withInput()
                ->with('error', 'Yaar, username ya password galat hai! Dhyan se daalo 😅');
        }

        session(['gs_admin_id' => $admin->id, 'gs_admin_name' => $admin->display_name]);

        return redirect()->route('gs.admin.dashboard')
            ->with('success', "Marhaba {$admin->display_name}! Khel shuru karte hain 🎉");
    }

    public function showRegister()
    {
        if (session()->has('gs_admin_id')) {
            return redirect()->route('gs.admin.dashboard');
        }
        return view('gossip-slip.admin.register', ['title' => 'Admin Register — Gossip Slip']);
    }

    public function register(Request $request)
    {
        $request->validate([
            'display_name' => 'required|string|max:100',
            'username'     => 'required|string|max:50|unique:gs_admins,username|alpha_dash',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        $admin = GsAdmin::create([
            'display_name' => $request->display_name,
            'username'     => $request->username,
            'password'     => Hash::make($request->password),
        ]);

        session(['gs_admin_id' => $admin->id, 'gs_admin_name' => $admin->display_name]);

        return redirect()->route('gs.admin.dashboard')
            ->with('success', "Welcome {$admin->display_name}! Ab pehla game banao! 🚀");
    }

    public function logout(Request $request)
    {
        session()->forget(['gs_admin_id', 'gs_admin_name', 'gs_player_token']);
        return redirect()->route('gs.admin.login')
            ->with('success', 'Alvida! Phir milenge 👋');
    }
}
