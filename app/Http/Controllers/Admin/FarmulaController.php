<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farmula;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class FarmulaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'farmulas';
        if ($request->ajax()) {
            $farmulas = Farmula::query();
            return DataTables::of($farmulas)
                ->addIndexColumn()
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(created_at, '%d %b,%Y') like ?", ["%$keyword%"]);
                })
                // ->addColumn('parent', function ($category) {
                //     return $category->parent ? $category->parent->name : '-';
                // })
                ->addColumn('created_at', function ($farmula) {
                    return date_format(date_create($farmula->created_at), "d M,Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("farmulas.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    // $editbtn = '<a data-id="'.$row->id.'" data-name="'.$row->name.'" data-parent="'.($row->parent_category_id ?? '').'" href="javascript:void(0)" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('farmulas.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    // if (!auth()->user()->hasPermissionTo('edit-farmula')) {
                        // $editbtn = '';
                    // }
                    // if (!auth()->user()->hasPermissionTo('destroy-farmula')) {
                        // $deletebtn = '';
                    // }
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        $allFarmulas = Farmula::all();
        return view('admin.farmulas.index', compact(
            'title',
            'allFarmulas'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $title = 'add farmula';
        return view('admin.farmulas.create', compact(
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
            'name' => 'required|max:100|unique:farmulas,name',
            'description' => 'nullable|max:255',
        ]);
        Farmula::create($request->all());
        $notification = array("Farmula has been added");
        return redirect()->route('farmulas.index')->with($notification);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\Farmula $farmula
     * @return \Illuminate\Http\Response
     */
    public function edit(Farmula $farmula)
    {
        $title = 'edit farmula';
        return view('admin.farmulas.edit', compact(
            'title',
            'farmula',
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Farmula $farmula
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Farmula $farmula)
{
    $this->validate($request, [
        'name' => 'required|max:200|unique:farmulas,name,' . $farmula->id,
        'description' => 'nullable|max:255',
    ]);

    $farmula->update($request->only(['name', 'description']));

    $notification = notify('Farmula has been updated');
    return redirect()->route('farmulas.index')->with($notification);
}

    /**
     * Autocomplete farmula names for Select2/frontend
     */
    public function autocompleteName(Request $request)
    {
        $query = $request->get('term');

        $farmulas = Farmula::where('name', 'like', "%{$query}%")
            ->select('name')
            ->distinct()
            ->orderBy('name', 'asc')
            ->simplePaginate(10);
            
        $formattedFarmulas = [];
        foreach ($farmulas as $farmula) {
            $formattedFarmulas[] = [
                'id' => $farmula->name, 
                'text' => $farmula->name
            ];
        }
        
        return response()->json([
            'results' => $formattedFarmulas,
            'pagination' => [
                'more' => $farmulas->hasMorePages()
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
    public function destroy(Request $request)
    {
        return Farmula::findOrFail($request->id)->delete();
    }
}
