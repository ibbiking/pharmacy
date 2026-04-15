<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
        $title = 'packaging';
        // Return DataTables JSON only for actual table draws, not Inertia navigations.
        if ($request->has('draw')) {
            $categories = Category::query();

            return DataTables::of($categories)
                ->addIndexColumn()
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(created_at, '%d %b,%Y') like ?", ["%$keyword%"]);
                })
                ->addColumn('created_at', function ($category) {
                    return date_format(date_create($category->created_at), "d M,Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("categories.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('categories.destroy', $row->id) . '" href="javascript:void(0)" class="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    if (!auth()->user()->hasPermissionTo('edit-category')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-category')) {
                        $deletebtn = '';
                    }

                    return $editbtn . ' ' . $deletebtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return Inertia::render('Categories/Index', [
            'title' => $title,
            'permissions' => [
                'create' => auth()->user()->can('create-category'),
                'edit' => auth()->user()->can('edit-category'),
                'destroy' => auth()->user()->can('destroy-category'),
            ],
            'routes' => [
                'index' => route('categories.index'),
                'create' => route('categories.create'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $title = 'add packaging';
        return Inertia::render('Categories/Form', [
            'title' => $title,
            'mode' => 'create',
            'category' => null,
            'routes' => [
                'index' => route('categories.index'),
                'store' => route('categories.store'),
                'autocomplete' => route('categories.autocomplete'),
            ],
        ]);
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
            'name' => 'required|max:100|unique:categories,name',
            'description' => 'nullable|max:255',
        ]);
        Category::create($request->all());
        $notification = array("Packaging has been added");
        return redirect()->route('categories.index')->with($notification);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\Category $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Category $category)
    {
        $title = 'edit packaging';
        return Inertia::render('Categories/Form', [
            'title' => $title,
            'mode' => 'edit',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ],
            'routes' => [
                'index' => route('categories.index'),
                'update' => route('categories.update', $category->id),
                'autocomplete' => route('categories.autocomplete'),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Category $category
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Category $category)
{
    $this->validate($request, [
        'name' => 'required|max:200|unique:categories,name,' . $category->id,
        'description' => 'nullable|max:255',
    ]);

    $category->update($request->only(['name', 'description']));

    $notification = notify('Packaging has been updated');
    return redirect()->route('categories.index')->with($notification);
}

    /**
     * Autocomplete category names for Select2/frontend
     */
    public function autocompleteName(Request $request)
    {
        $query = $request->get('term');

        $categories = Category::where('name', 'like', "%{$query}%")
            ->select('name')
            ->distinct()
            ->orderBy('name', 'asc')
            ->simplePaginate(10);
            
        $formattedCategories = [];
        foreach ($categories as $category) {
            $formattedCategories[] = [
                'id' => $category->name, 
                'text' => $category->name
            ];
        }
        
        return response()->json([
            'results' => $formattedCategories,
            'pagination' => [
                'more' => $categories->hasMorePages()
            ]
        ]);
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
    public function destroy(Category $category)
    {
        $category->delete();

        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Packaging deleted successfully']);
        }

        return redirect()->route('categories.index')->with(notify('Packaging deleted successfully'));
    }
}
