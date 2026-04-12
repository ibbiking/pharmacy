<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SuperAdminBusinessController extends Controller
{
    public function index(Request $request)
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        
        $title = 'Manage Businesses';
        if ($request->ajax()) {
            $businesses = \App\Models\Business::with('users');
            return \Yajra\DataTables\DataTables::of($businesses)
                ->addColumn('owner', function($row){
                    $owner = $row->users->where('pivot.role', 'owner')->first();
                    return $owner ? $owner->name . '<br><small>'.$owner->email.'</small>' : 'N/A';
                })
                ->addColumn('action', function($row){
                    return '<a href="'.route('superadmin.businesses.impersonate', $row->id).'" class="btn btn-primary btn-sm rounded-pill"><i class="fas fa-sign-in-alt"></i> Login As Owner</a>';
                })
                ->rawColumns(['owner', 'action'])
                ->make(true);
        }
        
        return view('admin.superadmin_businesses.index', compact('title'));
    }

    public function impersonate($id)
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        $business = \App\Models\Business::findOrFail($id);
        
        session(['impersonate_business_id' => $business->id, 'business_id' => $business->id]);
        
        return redirect()->route('dashboard')->with(notify("Logged in as Owner of {$business->name}"));
    }

    public function stopImpersonating()
    {
        session()->forget('impersonate_business_id');
        session()->forget('business_id'); 
        return redirect()->route('dashboard')->with(notify("Restored to Super Admin global view."));
    }
}
