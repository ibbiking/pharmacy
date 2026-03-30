<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductTypeController extends Controller
{
    public function index(Request $request)
    {
        $title = 'product types';
        if ($request->ajax()) {
            $types = ProductType::query();
            return DataTables::of($types)
                ->addIndexColumn()
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(created_at, '%d %b, %Y') like ?", ["%$keyword%"]);
                })
                ->addColumn('created_at', function ($type) {
                    return date_format(date_create($type->created_at), "d M, Y");
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("product-types.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('product-types.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    return $editbtn . ' ' . $deletebtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        $allTypes = ProductType::all();
        return view('admin.product_types.index', compact('title', 'allTypes'));
    }

    public function create()
    {
        $title = 'add product type';
        return view('admin.product_types.create', compact('title'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:150|unique:product_types,name,NULL,id,deleted_at,NULL',
            'description' => 'nullable|max:255',
        ]);
        ProductType::create($request->all());
        $notification = notify('Product type has been added');
        return redirect()->route('product-types.index')->with($notification);
    }

    public function edit(ProductType $productType)
    {
        $title = 'edit product type';
        return view('admin.product_types.edit', compact('title', 'productType'));
    }

    public function update(Request $request, ProductType $productType)
    {
        $this->validate($request, [
            'name' => 'required|max:150|unique:product_types,name,' . $productType->id . ',id,deleted_at,NULL',
            'description' => 'nullable|max:255',
        ]);
        $productType->update($request->only(['name', 'description']));
        $notification = notify('Product type has been updated');
        return redirect()->route('product-types.index')->with($notification);
    }

    public function destroy(Request $request)
    {
        return ProductType::findOrFail($request->id)->delete();
    }
}