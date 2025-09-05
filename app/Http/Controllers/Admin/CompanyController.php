<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'companies';
        if ($request->ajax()) {
            $companies = Company::get();
            return DataTables::of($companies)
                ->addIndexColumn()
                // ->addColumn('parent', function ($category) {
                //     return $category->parent ? $category->parent->name : '-';
                // })
                ->addColumn('created_at', function ($company) {
                    return date_format(date_create($company->created_at), "d M,Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("companies.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    // $editbtn = '<a data-id="'.$row->id.'" data-name="'.$row->name.'" data-parent="'.($row->parent_category_id ?? '').'" href="javascript:void(0)" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('companies.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    // if (!auth()->user()->hasPermissionTo('edit-company')) {
                        // $editbtn = '';
                    // }
                    // if (!auth()->user()->hasPermissionTo('destroy-company')) {
                        // $deletebtn = '';
                    // }
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        $allCompanies = Company::all();
        return view('admin.companies.index', compact(
            'title',
            'allCompanies'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $title = 'add company';
        return view('admin.companies.create', compact(
            'title',
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
        $this->validate($request, [
            'name' => 'required|max:100|unique:companies,name',
            'description' => 'nullable|max:255',
        ]);
        Company::create($request->all());
        $notification = array("Company has been added");
        return redirect()->route('companies.index')->with($notification);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\Company $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Company $company)
    {
        $title = 'edit company';
        return view('admin.companies.edit', compact(
            'title',
            'company',
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Company $company
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Company $company)
{
    $this->validate($request, [
        'name' => 'required|max:200|unique:companies,name,' . $company->id,
        'description' => 'nullable|max:255',
    ]);

    $company->update($request->only(['name', 'description']));

    $notification = notify('Company has been updated');
    return redirect()->route('companies.index')->with($notification);
}


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * 
     * @return \Illuminate\Http\Response
     */
//     public function update(Request $request)
// {
//     // 1️⃣ Validate fields
//     $request->validate([
//         'id'                 => ['required', 'exists:categories,id'],
//         'name'               => ['required', 'string', 'max:100'],
//         'parent_category_id' => ['nullable', 'exists:categories,id', 'different:id'],
//     ]);

//     // 2️⃣ Retrieve the category
//     $category = Category::findOrFail($request->id);

//     // 3️⃣ Extra guard (prevents setting itself or a child as its own parent)
//     if ($request->parent_category_id == $category->id) {
//         return back()->withErrors([
//             'parent_category_id' => 'A category cannot be its own parent.',
//         ]);
//     }

//     // 4️⃣ Update the record
//     $category->update([
//         'name'               => $request->name,
//         'parent_category_id' => $request->parent_category_id, // may be null
//     ]);

//     // 5️⃣ Return with notification
//     $notification = notify('Category has been updated');
//     return back()->with($notification);
// }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return Company::findOrFail($request->id)->delete();
    }
}
