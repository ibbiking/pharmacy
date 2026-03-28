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

        // Fetch existing relations for this product
        $relations = ProductCategory::with(['parentCategory', 'childCategory'])
            ->where('product_id', $productId)
            ->get();

        $lastChildId = null;

        if ($relations->count()) {
            // build parent => child map (keys & values are integers)
            $map = [];
            foreach ($relations as $r) {
                // ensure integer keys and values
                $map[(int)$r->parent_category_id] = (int)$r->child_category_id;
            }

            // find a starting parent that is not a child anywhere (top of chain)
            $parents = array_keys($map);
            $children = array_values($map);

            $start = null;
            foreach ($parents as $p) {
                if (! in_array($p, $children, true)) {
                    $start = $p;
                    break;
                }
            }

            // fallback: if we couldn't find such parent (shouldn't happen with your constraints),
            // fallback to any parent from the first relation
            if ($start === null) {
                $start = (int)$relations->first()->parent_category_id;
            }

            // walk the chain until there's no next child
            $current = $start;
            while (isset($map[$current])) {
                $current = $map[$current];
            }

            // $current is the last child id
            $lastChildId = $current;
        }

        // Parent dropdown → only last child OR all (if no relation yet)
        $parentCategories = $lastChildId
            ? Category::where('id', $lastChildId)->get()
            : Category::all();

        // Child dropdown → exclude categories already used as parent or child for this product
        $usedParentIds = $relations->pluck('parent_category_id')->filter()->toArray();
        $usedChildIds  = $relations->pluck('child_category_id')->filter()->toArray();

        $exclude = array_merge($usedParentIds, $usedChildIds);
        // remove duplicates and nulls
        $exclude = array_values(array_filter(array_unique($exclude)));

        $childCategories = Category::when(count($exclude), function ($q) use ($exclude) {
            $q->whereNotIn('id', $exclude);
        })->get();

        return view('admin.product_categories.create', compact(
            'product',
            'relations',
            'parentCategories',
            'childCategories',
            'lastChildId'
        ));
    }

    public function store(Request $request)
    {
        if ($request->has('single_packaging') && $request->single_packaging == 1) {
            $request->merge(['child_category_id' => $request->parent_category_id]);
            $request->validate([
                'product_id'         => 'required|exists:products,id',
                'parent_category_id' => 'required|exists:categories,id',
                'child_category_id'  => 'required|exists:categories,id',
            ]);
        } else {
            $request->validate([
                'product_id'         => 'required|exists:products,id',
                'parent_category_id' => 'required|different:child_category_id|exists:categories,id',
                'child_category_id'  => 'required|different:parent_category_id|exists:categories,id',
            ]);
        }

        // Rule 1: prevent duplicates (same parent+child)
        $exists = ProductCategory::where('product_id', $request->product_id)
            ->where('parent_category_id', $request->parent_category_id)
            ->where('child_category_id', $request->child_category_id)
            ->exists();

        if ($exists) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => 'This parent-child relation already exists.'], 422);
            return back()->withErrors([
                'child_category_id' => 'This parent-child relation already exists for this product.'
            ])->withInput();
        }

        if (!$request->has('single_packaging') || $request->single_packaging != 1) {
            // Rule 2: prevent reverse relationships (no cycle)
            $reverse = ProductCategory::where('product_id', $request->product_id)
                ->where('parent_category_id', $request->child_category_id)
                ->where('child_category_id', $request->parent_category_id)
                ->exists();

            if ($reverse) {
                if ($request->ajax()) return response()->json(['success' => false, 'message' => 'Invalid relationship (would create a cycle).'], 422);
                return back()->withErrors([
                    'child_category_id' => 'Invalid relationship (would create a cycle).'
                ])->withInput();
            }
        }

        // Rule 3: prevent same parent being assigned twice (regardless of child)
        $parentUsed = ProductCategory::where('product_id', $request->product_id)
            ->where('parent_category_id', $request->parent_category_id)
            ->exists();

        if ($parentUsed) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => 'This parent category is already assigned.'], 422);
            return back()->withErrors([
                'parent_category_id' => 'This parent category is already assigned to this product.'
            ])->withInput();
        }

        // Rule 4: prevent same child being assigned twice (regardless of parent)
        $childUsed = ProductCategory::where('product_id', $request->product_id)
            ->where('child_category_id', $request->child_category_id)
            ->exists();

        if ($childUsed) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => 'This child category is already assigned.'], 422);
            return back()->withErrors([
                'child_category_id' => 'This child category is already assigned to this product.'
            ])->withInput();
        }

        // Create relation
        ProductCategory::create($request->only(['product_id', 'parent_category_id', 'child_category_id']));

        if ($request->ajax()) return response()->json(['success' => true, 'message' => 'Product Category saved successfully.']);
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

        // Prevent same parent being assigned twice
        $parentUsed = ProductCategory::where('product_id', $productCategory->product_id)
            ->where('parent_category_id', $request->parent_category_id)
            ->where('id', '!=', $productCategory->id)
            ->exists();

        if ($parentUsed) {
            return back()->withErrors(['parent_category_id' => 'This parent category is already assigned to this product.']);
        }

        // Prevent same child being assigned twice
        $childUsed = ProductCategory::where('product_id', $productCategory->product_id)
            ->where('child_category_id', $request->child_category_id)
            ->where('id', '!=', $productCategory->id)
            ->exists();

        if ($childUsed) {
            return back()->withErrors(['child_category_id' => 'This child category is already assigned to this product.']);
        }

        $productCategory->update($request->only(['parent_category_id', 'child_category_id']));

        return redirect()->route('product-categories.index')->with('success', 'Product category relation updated.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        $productId = $productCategory->product_id;
        
        // Remove the dependent product parameter entry
        \App\Models\ProductParameter::where('product_id', $productId)
            ->where('parent_category_id', $productCategory->parent_category_id)
            ->where('child_category_id', $productCategory->child_category_id)
            ->delete();

        // If this is the last relation being deleted, we should also clean up the top-level base parameter 
        // and any stray parameters to prevent orphaned data from persisting.
        $remainingRelations = ProductCategory::where('product_id', $productId)
            ->where('id', '!=', $productCategory->id)
            ->count();
            
        if ($remainingRelations === 0) {
            \App\Models\ProductParameter::where('product_id', $productId)->delete();
        }

        $productCategory->delete();

        $redirect = route('product-categories.create', ['product_id' => $productId]);

        return response()->json(['success' => true, 'redirect' => $redirect]);
    }
}
