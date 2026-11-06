<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosCartReservation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    /** Save the UI theme for the logged-in user. */
    public function theme(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'system'])]]);

        $request->user()->forceFill(['theme' => $data['theme']])->save();

        return back();
    }

    /** Switch the active business (only to a business the user belongs to). */
    public function switchBusiness(Request $request)
    {
        $data = $request->validate(['business_id' => ['required', 'integer']]);
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            // Super-admins move between businesses through impersonation (audited separately).
            return redirect()->route('superadmin.businesses');
        }

        abort_unless($user->activeBusinesses()->whereKey($data['business_id'])->exists(), 403);

        if ((int) session('business_id') !== (int) $data['business_id']) {
            // An in-progress POS cart belongs to the business it was started in.
            PosCartReservation::releaseForUser($user->id, (int) session('business_id'));
            $request->session()->forget('pos_cart');
            session(['business_id' => (int) $data['business_id']]);
        }

        $home = $user->isSalesPersonIn((int) $data['business_id']) ? 'pos.index' : 'dashboard';

        return redirect()->route($home)->with('success', 'Switched business.');
    }
}
