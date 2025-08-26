<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'categories';
        if ($request->ajax()) {
            $categories = Category::get();
            return DataTables::of($categories)
                ->addIndexColumn()
                ->addColumn('parent', function ($category) {
                    return $category->parent ? $category->parent->name : '-';
                })
                ->addColumn('created_at', function ($category) {
                    return date_format(date_create($category->created_at), "d M,Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a data-id="'.$row->id.'" data-name="'.$row->name.'" data-parent="'.($row->parent_category_id ?? '').'" href="javascript:void(0)" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('categories.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    if (!auth()->user()->hasPermissionTo('edit-category')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-category')) {
                        $deletebtn = '';
                    }
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        $allCategories = Category::all();
        return view('admin.products.categories', compact(
            'title',
            'allCategories'
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
            'name' => 'required|max:100',
            'parent_category_id' => 'nullable|exists:categories,id',
        ]);
        Category::create($request->all());
        $notification = array("Category has been added");
        return back()->with($notification);
    }




    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * 
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
{
    // 1️⃣ Validate fields
    $request->validate([
        'id'                 => ['required', 'exists:categories,id'],
        'name'               => ['required', 'string', 'max:100'],
        'parent_category_id' => ['nullable', 'exists:categories,id', 'different:id'],
    ]);

    // 2️⃣ Retrieve the category
    $category = Category::findOrFail($request->id);

    // 3️⃣ Extra guard (prevents setting itself or a child as its own parent)
    if ($request->parent_category_id == $category->id) {
        return back()->withErrors([
            'parent_category_id' => 'A category cannot be its own parent.',
        ]);
    }

    // 4️⃣ Update the record
    $category->update([
        'name'               => $request->name,
        'parent_category_id' => $request->parent_category_id, // may be null
    ]);

    // 5️⃣ Return with notification
    $notification = notify('Category has been updated');
    return back()->with($notification);
}

    /**
     * Remove the specified resource from storage.
     *
     * @param  Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return Category::findOrFail($request->id)->delete();
    }
}
