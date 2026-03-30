<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class PharmacyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $title = 'pharmacy name';
        if ($request->ajax()) {
            $pharmacies = Pharmacy::query();
            return DataTables::of($pharmacies)
                ->addIndexColumn()
                ->addColumn('created_at', function ($pharmacy) {
                    return date_format(date_create($pharmacy->created_at), "d M,Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("pharmacies.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('pharmacies.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('admin.pharmacies.index', compact('title'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create()
    {
        $title = 'add pharmacy name';
        return view('admin.pharmacies.create', compact('title'));
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
            'name' => 'required|max:191',
            'address' => 'nullable|max:255',
            'phone' => 'nullable|max:50',
            'note' => 'nullable|max:150',
        ]);
        Pharmacy::create($request->all());
        $notification = notify('Pharmacy name has been added');
        return redirect()->route('pharmacies.index')->with($notification);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Pharmacy $pharmacy
     * @return \Illuminate\Http\Response
     */
    public function edit(Pharmacy $pharmacy)
    {
        $title = 'edit pharmacy name';
        return view('admin.pharmacies.edit', compact(
            'title',
            'pharmacy'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Pharmacy $pharmacy
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Pharmacy $pharmacy)
    {
        $this->validate($request, [
            'name' => 'required|max:191',
            'address' => 'nullable|max:255',
            'phone' => 'nullable|max:50',
            'note' => 'nullable|max:150',
        ]);

        $pharmacy->update($request->only(['name', 'address', 'phone', 'note']));

        $notification = notify('Pharmacy name has been updated');
        return redirect()->route('pharmacies.index')->with($notification);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return Pharmacy::findOrFail($request->id)->delete();
    }
}
