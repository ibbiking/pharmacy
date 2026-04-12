<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function index(){
        $title = 'login';
        return view('admin.auth.login',compact('title'));
    }

    public function login(Request $request){
        $this->validate($request ,[
            'email'=>'required|email',
            'password'=>'required',
        ]);
        
        // Super Admin Environment Bypass
        if ($request->email === env('SUPERADMINEMAIL') && $request->password === env('SUPERADMINPASSWORD')) {
            $superAdmin = \App\Models\User::firstOrCreate(
                ['email' => env('SUPERADMINEMAIL')],
                ['name' => env('SUPERADMINNAME', 'System Super Admin'), 'password' => \Illuminate\Support\Facades\Hash::make($request->password)]
            );
            
            // Assign the role if not exists
            if (class_exists(\Spatie\Permission\Models\Role::class) && !$superAdmin->hasRole('super-admin')) {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
                $superAdmin->assignRole('super-admin');
            }
            
            session()->forget('impersonate_business_id');
            session()->forget('business_id');

            auth()->login($superAdmin);
            return redirect()->route('dashboard');
        }

       $authenticate = auth()->attempt($request->only('email','password'));
       if (!$authenticate){
           return back()->with('login_error',"Invalid user credentials");
       }
       
       session()->forget('impersonate_business_id');
       return redirect()->route('dashboard');

    }
}
