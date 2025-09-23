<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $records = ProductCategory::with(['parentCategory', 'childCategory'])
                ->latest()
                ->get();

            return DataTables::of($records)
                ->addIndexColumn()
                ->addColumn('parent', fn($row) => $row->parentCategory->name ?? '-')
                ->addColumn('child', fn($row) => $row->childCategory->name ?? '-')
                ->addColumn('action', function ($row) {
                    $edit = '<a href="' . route("product-categories.edit", $row->id) . '" class="btn btn-info btn-sm"><i class="fas fa-edit"></i></a>';
                    $delete = '<a data-id="' . $row->id . '" data-route="' . route("product-categories.destroy", $row->id) . '" href="javascript:void(0)" id="deletebtn" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>';
                    return $edit . ' ' . $delete;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.product_categories.index');
    }

    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = Product::findOrFail($productId);

        // Fetch existing relations
        $relations = ProductCategory::with(['parentCategory', 'childCategory'])
            ->where('product_id', $productId)
            ->get();

        // Collect already used parent & child IDs
        $usedParentIds = $relations->pluck('parent_category_id')->toArray();
        $usedChildIds  = $relations->pluck('child_category_id')->toArray();

        // Parent dropdown → exclude used parents
        $parentCategories = Category::whereNotIn('id', $usedParentIds)->get();

        // Child dropdown → exclude used children + exclude used parents
        $childCategories = Category::whereNotIn('id', array_merge($usedChildIds, $usedParentIds))->get();

        return view('admin.product_categories.create', compact(
            'product',
            'relations',
            'parentCategories',
            'childCategories'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'         => 'required|exists:products,id',
            'parent_category_id' => 'required|different:child_category_id|exists:categories,id',
            'child_category_id'  => 'required|different:parent_category_id|exists:categories,id',
        ]);

        // Rule 1: prevent duplicates (same parent+child)
        $exists = ProductCategory::where('product_id', $request->product_id)
            ->where('parent_category_id', $request->parent_category_id)
            ->where('child_category_id', $request->child_category_id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'child_category_id' => 'This parent-child relation already exists for this product.'
            ])->withInput();
        }

        // Rule 2: prevent reverse relationships (no cycle)
        $reverse = ProductCategory::where('product_id', $request->product_id)
            ->where('parent_category_id', $request->child_category_id)
            ->where('child_category_id', $request->parent_category_id)
            ->exists();

        if ($reverse) {
            return back()->withErrors([
                'child_category_id' => 'Invalid relationship (would create a cycle).'
            ])->withInput();
        }

        // Rule 3: prevent same parent being assigned twice (regardless of child)
        $parentUsed = ProductCategory::where('product_id', $request->product_id)
            ->where('parent_category_id', $request->parent_category_id)
            ->exists();

        if ($parentUsed) {
            return back()->withErrors([
                'parent_category_id' => 'This parent category is already assigned to this product.'
            ])->withInput();
        }

        // Create relation
        ProductCategory::create($request->only(['product_id', 'parent_category_id', 'child_category_id']));

        return redirect()->back()->with('success', 'Product Category saved successfully.');
    }


    public function edit(ProductCategory $productCategory)
    {
        $categories = Category::all();
        return view('admin.product_categories.edit', compact('productCategory', 'categories'));
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $request->validate([
            'parent_category_id' => 'required|different:child_category_id|exists:categories,id',
            'child_category_id'  => 'required|different:parent_category_id|exists:categories,id',
        ]);

        // Prevent duplicate
        $exists = ProductCategory::where('product_id', $productCategory->product_id)
            ->where('parent_category_id', $request->parent_category_id)
            ->where('child_category_id', $request->child_category_id)
            ->where('id', '!=', $productCategory->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['child_category_id' => 'This relation already exists for this product.']);
        }

        // Prevent reverse relation
        $reverse = ProductCategory::where('product_id', $productCategory->product_id)
            ->where('parent_category_id', $request->child_category_id)
            ->where('child_category_id', $request->parent_category_id)
            ->exists();

        if ($reverse) {
            return back()->withErrors(['child_category_id' => 'Invalid relationship (would create cycle).']);
        }

        $productCategory->update($request->only(['parent_category_id', 'child_category_id']));

        return redirect()->route('product-categories.index')->with('success', 'Product category relation updated.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productId = $productCategory->product_id;
        $productCategory->delete();

        return response()->json([
            'success' => true,
            'redirect' => route('product-categories.create', ['product_id' => $productId])
        ]);
    }
}
