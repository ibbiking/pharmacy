<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Taxes';

        if ($request->ajax()) {
            $taxes = Tax::get();
            return DataTables::of($taxes)
                ->addIndexColumn()
                ->addColumn('created_at', fn($tax) => $tax->created_at->format("d M, Y"))
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("taxes.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('taxes.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    return $editbtn . ' ' . $deletebtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $allTaxes = Tax::all();
        return view('admin.taxes.index', compact('title', 'allTaxes'));
    }

    public function create()
    {
        $title = 'Add Tax';
        return view('admin.taxes.create', compact('title'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:100|unique:taxes,name',
            'rate' => 'required|numeric|min:0',
        ]);

        Tax::create($request->only(['name', 'rate']));

        return redirect()->route('taxes.index')->with('success', 'Tax has been added');
    }

    public function edit(Tax $tax)
    {
        $title = 'Edit Tax';
        return view('admin.taxes.edit', compact('title', 'tax'));
    }

    public function update(Request $request, Tax $tax)
    {
        $request->validate([
            'name' => 'required|max:100|unique:taxes,name,' . $tax->id,
            'rate' => 'required|numeric|min:0',
        ]);

        $tax->update($request->only(['name', 'rate']));

        return redirect()->route('taxes.index')->with('success', 'Tax has been updated');
    }

    public function destroy(Request $request)
    {
        return Tax::findOrFail($request->id)->delete();
    }
}