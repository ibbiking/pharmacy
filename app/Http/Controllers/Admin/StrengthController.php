<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Strength;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class StrengthController extends Controller
{
    public function index(Request $request)
    {
        $title = 'strengths';
        if ($request->ajax()) {
            $strengths = Strength::query();
            return DataTables::of($strengths)
                ->addIndexColumn()
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(created_at, '%d %b, %Y') like ?", ["%$keyword%"]);
                })
                ->addColumn('created_at', function ($strength) {
                    return date_format(date_create($strength->created_at), "d M, Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("strengths.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('strengths.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    return $editbtn . ' ' . $deletebtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        $allStrengths = Strength::all();
        return view('admin.strengths.index', compact('title', 'allStrengths'));
    }

    public function create()
    {
        $title = 'add strength';
        return view('admin.strengths.create', compact('title'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:150|unique:strengths,name,NULL,id,deleted_at,NULL',
            'description' => 'nullable|max:255',
        ]);
        Strength::create($request->all());
        $notification = notify('Strength has been added');
        return redirect()->route('strengths.index')->with($notification);
    }

    public function edit(Strength $strength)
    {
        $title = 'edit strength';
        return view('admin.strengths.edit', compact('title', 'strength'));
    }

    public function update(Request $request, Strength $strength)
    {
        $this->validate($request, [
            'name' => 'required|max:150|unique:strengths,name,' . $strength->id . ',id,deleted_at,NULL',
            'description' => 'nullable|max:255',
        ]);
        $strength->update($request->only(['name', 'description']));
        $notification = notify('Strength has been updated');
        return redirect()->route('strengths.index')->with($notification);
    }

    public function destroy(Request $request)
    {
        return Strength::findOrFail($request->id)->delete();
    }
}