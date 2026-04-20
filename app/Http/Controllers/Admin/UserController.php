<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Business;
use App\Mail\SalesPersonWelcomeMail;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'users';
        if ($request->ajax()) {
            if (auth()->user()->hasRole('super-admin')) {
                $users = User::with('roles');
            } else {
                $users = User::with('roles')->whereHas('businesses', function ($query) {
                    $query->where('business_id', session('business_id'));
                });
            }
            return DataTables::of($users)
                ->filterColumn('role', function($query, $keyword) {
                    $query->whereHas('roles', function($q) use($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('created_at', function($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(users.created_at, '%d %b,%Y') like ?", ["%$keyword%"]);
                })
                ->addIndexColumn()
                ->addColumn('created_at', function ($category) {
                    return date_format(date_create($category->created_at), "d M,Y");
                })
                ->addColumn('avatar', function ($user) {
                    $src = asset('assets/img/avatar_1nn.png');
                    if (!empty($user->avatar)) {
                        $src = asset('storage/users/'.$user->avatar);
                    }
                    return '<img src="'.$src.'" class="avatar-img rounded-circle" width="50" />';
                })
                ->addColumn('role', function ($row) {
                    foreach ($row->getRoleNames() as $role) {
                        return '<span>'.$role.'</span>';
                    }
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="'.route("users.edit", $row->id).'" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="'.$row->id.'" data-route="'.route('users.destroy', $row->id).'" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    if (!auth()->user()->hasPermissionTo('edit-user')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-user')) {
                        $deletebtn = '';
                    }
                    $btn = $editbtn.' '.$deletebtn;
                    return $btn;
                })
                ->rawColumns(['avatar','role','action'])
                ->make(true);
        }
        return view('admin.users.index', compact(
            'title'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $title = 'create user';
        $roles = Role::where('name', 'sales-person')->get();
        return view('admin.users.create', compact('title','roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'name'=>'required|max:100',
            'email'=>'required|email|unique:users,email',
            'password'=>'required|confirmed|max:200',
            'avatar'=>'nullable|file|image|mimes:jpg,jpeg,gif,png',
        ]);
        $imageName = null;
        if ($request->hasFile('avatar')) {
            $imageName = time().'.'.$request->avatar->extension();
            $request->avatar->move(public_path('storage/users'), $imageName);
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'avatar' => $imageName,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole('sales-person');

        $businessId = session('business_id');
        if ($businessId && Business::where('id', $businessId)->exists()) {
            $user->businesses()->syncWithoutDetaching([$businessId => ['role' => 'sales-person']]);
        }

        try {
            Mail::to($user->email)->send(new SalesPersonWelcomeMail($user, $request->password));
        } catch (\Throwable $th) {
            \Log::warning('Failed to send sales person onboarding email: ' . $th->getMessage());
        }

        $notifiation = notify('user created successfully');
        return redirect()->route('users.index')->with($notifiation);
    }

   
    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        $title = "edit user";
        $roles = Role::get();
        return view('admin.users.edit',compact(
            'title','roles','user'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\User $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $this->validate($request,[
            'name'=>'required|max:100',
            'email'=>'required|email',
            'role'=>'required',
            'password'=>'nullable|confirmed|max:200',
            'avatar'=>'nullable|file|image|mimes:jpg,jpeg,gif,png',
        ]);
        $imageName = $user->avatar;
        $password = $user->password;
        if($request->hasFile('avatar')){
            $imageName = time().'.'.$request->avatar->extension();
            $request->avatar->move(public_path('storage/users'), $imageName);
        }
        if(!empty($request->password) && ($user->password != $request->password)){
            $password = Hash::make($request->password);
        }
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'avatar' => $imageName,
            'password' => $password,
        ]);
        foreach($user->getRoleNames() as $userRole){
            $user->removeRole($userRole);
        }
        $user->assignRole($request->role);
        $notification = notify('user updated successfully');
        return redirect()->route('users.index')->with($notification);
    }

    public function profile(){
        $title = 'user profile';
        $roles = Role::get();
        return view('admin.users.profile',compact(
            'title','roles'
        ));
    }

    public function updateProfile(Request $request,User $user){
        $isSalesPerson = $user->hasRole('sales-person');
        $rules = [
            'name' => 'required|min:5|max:200',
            'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png,gif'
        ];
        if (!$isSalesPerson) {
            $rules['email'] = 'required|email';
        }
        $this->validate($request, $rules);
        $imageName = $user->avatar;
        if($request->hasFile('avatar')){
            $imageName = time().'.'.$request->avatar->extension();
            $request->avatar->move(public_path('storage/users'), $imageName);
        }
        $email = $isSalesPerson ? $user->email : $request->email;
        $user->update([
            'name' => $request->name,
            'email' => $email,
            'avatar' => $imageName,
        ]);
        $notification = notify('profile updated successfully');
        return redirect()->route('profile')->with($notification);
    }

    /**
     * Update current user password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updatePassword(Request $request, User $user)
    {
        $this->validate($request, [
            'current_password'=>'required',
            'password'=>'required|max:200|confirmed',
        ]);
        $verify_password = password_verify($request->current_password, $user->password);
        if ($verify_password) {
            $user->update(['password'=>Hash::make($request->password)]);
            $notification = notify('User password updated successfully!!!');
            $logout = auth()->logout();
            return back()->with($notification, $logout);
        } elseif(!$verify_password) {
            $notification = notify("Incorrect Old Password!!!",'danger');
            return back()->with($notification);
        }
    }

    /**
    * Remove the specified resource from storage.
    *
    * @param  \Illuminate\Http\Request $request
    * @return \Illuminate\Http\Response
    */
    public function destroy(Request $request)
    {
        return User::findOrFail($request->id)->delete();
    }
}
