<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'suppliers';
        if($request->ajax()){
            $suppliers = Supplier::query();
            return DataTables::of($suppliers)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="'.route("suppliers.edit", $row->id).'" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="'.$row->id.'" data-route="'.route('suppliers.destroy', $row->id).'" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    if (!auth()->user()->hasPermissionTo('edit-supplier')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-supplier')) {
                        $deletebtn = '';
                    }
                    $btn = $editbtn.' '.$deletebtn;
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    
        return view('admin.suppliers.index',compact(
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
        $title = 'create supplier';
        $companies = \App\Models\Company::all();
        return view('admin.suppliers.create',compact(
            'title', 'companies'
        ));
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
            'name'=>'required|string|max:20|unique:suppliers,name',
            'email'=>'nullable|email|string',
            'phone'=>'nullable|min:10|max:20',
            'company'=>'nullable|max:200',
            'address'=>'nullable|max:200',
            'comment' =>'nullable|max:255',
        ]);
        Supplier::create([
            'name'=>$request->name,
            'email'=>$request->email,
            'phone'=>$request->phone,
            'company'=>$request->company,
            'address'=>$request->address,
            'comment'=>$request->comment,
        ]);
        $notification = notify("Supplier has been added");
        return redirect()->route('suppliers.index')->with($notification);
    }

    
    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\Supplier $supplier
     * @return \Illuminate\Http\Response
     */
    public function edit(Supplier $supplier)
    {
        $title = 'edit supplier';
        $companies = \App\Models\Company::all();
        return view('admin.suppliers.edit',compact(
            'title','supplier','companies'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Supplier $supplier
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Supplier $supplier)
    {
        $this->validate($request,[
            'name'=>'required|string|max:20|unique:suppliers,name,' . $supplier->id,
            'email'=>'nullable|email|string',
            'phone'=>'nullable|min:10|max:20',
            'company'=>'nullable|max:200',
            'address'=>'nullable|max:200',
            'comment' =>'nullable|max:255',
        ]);
        $supplier->update([
            'name'=>$request->name,
            'email'=>$request->email,
            'phone'=>$request->phone,
            'company'=>$request->company,
            'address'=>$request->address,
            'comment'=>$request->comment,
        ]);
        $notification = notify("Supplier has been added");
        return redirect()->route('suppliers.index')->with($notification);
    }

    /**
     * Autocomplete supplier names for Select2/frontend
     */
    public function autocompleteName(Request $request)
    {
        $query = $request->get('term');

        $suppliers = Supplier::where('name', 'like', "%{$query}%")
            ->select('name')
            ->distinct()
            ->orderBy('name', 'asc')
            ->simplePaginate(10);
            
        $formattedSuppliers = [];
        foreach ($suppliers as $supplier) {
            $formattedSuppliers[] = [
                'id' => $supplier->name, 
                'text' => $supplier->name
            ];
        }
        
        return response()->json([
            'results' => $formattedSuppliers,
            'pagination' => [
                'more' => $suppliers->hasMorePages()
            ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return Supplier::findOrFail($request->id)->delete();
    }
}
