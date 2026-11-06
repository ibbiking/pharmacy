<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Target of the qcod/laravel-app-settings package routes (GET/POST /settings, registered by the package outside our
 * middleware stack). SA-3: those routes only checked "signed in", so any user could change app-wide settings.
 * App settings now live in AppSettingsController (super admin only); the package routes go nowhere.
 */
class SettingController extends Controller
{
    public function index()
    {
        return redirect()->route('superadmin.settings');
    }

    public function store()
    {
        abort(404);
    }
}
