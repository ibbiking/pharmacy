<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Platform admins: add, switch off / on, archive. Nobody can switch off or archive themselves. */
class AdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Platform/Admins', [
            'admins' => PlatformAdmin::query()->orderBy('name')->paginate(20)->through(fn (PlatformAdmin $a) => [
                'id' => $a->id, 'name' => $a->name, 'email' => $a->email, 'active' => $a->is_active,
                'last_login_at' => $a->last_login_at?->format('d M Y H:i'), 'me' => $a->id === auth('platform')->id(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('platform_admins', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'max:200', Password::min(8)->letters()->numbers()],
        ]);
        $admin = PlatformAdmin::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'], 'is_active' => true]);
        PlatformAuditLog::record('admin.add', null, ['admin' => $admin->email]);

        return back()->with('success', "{$admin->name} can now sign in to the platform.");
    }

    public function toggle(int $id)
    {
        $admin = PlatformAdmin::query()->findOrFail($id);
        if ($admin->id === auth('platform')->id()) {
            return back()->with('error', 'You can’t switch off your own account.');
        }
        $admin->forceFill(['is_active' => ! $admin->is_active])->save();
        PlatformAuditLog::record($admin->is_active ? 'admin.on' : 'admin.off', null, ['admin' => $admin->email]);

        return back()->with('success', "{$admin->name} ".($admin->is_active ? 'switched on.' : 'switched off.'));
    }

    public function destroy(int $id)
    {
        $admin = PlatformAdmin::query()->findOrFail($id);
        if ($admin->id === auth('platform')->id()) {
            return back()->with('error', 'You can’t archive your own account.');
        }
        $admin->delete();
        PlatformAuditLog::record('admin.archive', null, ['admin' => $admin->email]);

        return back()->with('success', "{$admin->name} archived.");
    }
}
