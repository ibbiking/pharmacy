<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use QCod\AppSettings\Setting\AppSettings;

/**
 * Super admin: app-wide settings (Roadmap 5.11b) — name, fallback currency symbol, logo and favicon of the classic
 * pages. SA-3: the settings package's own POST /settings only checked "signed in"; SettingController now refuses it.
 * Each business's own name, currency and branding live in Business settings / Email & branding.
 */
class AppSettingsController extends Controller
{
    public function __construct(private readonly AppSettings $settings)
    {
    }

    private function url(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }

    public function show()
    {
        return Inertia::render('SuperAdmin/AppSettings', [
            'appSettings' => [
                'app_name' => (string) $this->settings->get('app_name', config('app.name')),
                'app_currency' => (string) $this->settings->get('app_currency', 'Rs'),
                'logo' => $this->url($this->settings->get('logo')),
                'favicon' => $this->url($this->settings->get('favicon')),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'min:2', 'max:40'],
            'app_currency' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg', 'max:500'],
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
        ]);

        $this->settings->set('app_name', trim($data['app_name']));
        $this->settings->set('app_currency', trim($data['app_currency']));
        foreach (['logo', 'favicon'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $this->settings->set($key, $file->storeAs('logos', $key.'-'.bin2hex(random_bytes(6)).'.'.$file->extension(), 'uploads'));
            } elseif (! empty($data['remove_'.$key])) {
                $this->settings->remove($key);
            }
        }

        return back()->with('success', 'App settings saved.');
    }
}
