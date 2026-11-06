<?php

namespace App\Http\Controllers\Admin;

use App\Models\GenericProduct;
use App\Models\GenericCategory;
use App\Models\GenericCompany;
use App\Models\GenericFarmula;
use App\Models\GenericStrength;
use App\Models\GenericProductType;
use App\Models\Product;
use App\Models\Category;
use App\Models\Company;
use App\Models\Farmula;
use App\Models\Strength;
use App\Models\ProductType;
use App\Models\ProductParameter;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class GenericProductController extends Controller
{
    public function suggestions(Request $request)
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        $title = 'Suggested Generic Products';
        
        if ($request->ajax()) {
            $products = GenericProduct::with(['genericCompany', 'genericType'])->where('status', 'pending');
            return DataTables::of($products)
                ->addColumn('checkbox', function($row){
                    return '<input type="checkbox" class="generic-checkbox" value="'.$row->id.'">';
                })
                ->addColumn('strength', function ($product) {
                    if (!$product->strength_id) return '-';
                    $ids = explode(',', $product->strength_id);
                    $names = \App\Models\GenericStrength::whereIn('id', $ids)->pluck('name', 'id');
                    $spans = '';
                    foreach ($ids as $id) {
                        if (isset($names[$id])) {
                            $spans .= '<span class="badge badge-info mr-1 mb-1">' . $names[$id] . '</span>';
                        }
                    }
                    return $spans ? '<div style="max-width: 200px; white-space: normal; line-height: 2;">' . $spans . '</div>' : '-';
                })
                ->addColumn('farmula', function ($product) {
                    if (!$product->farmula_id) return '-';
                    $ids = explode(',', $product->farmula_id);
                    $names = \App\Models\GenericFarmula::whereIn('id', $ids)->pluck('name', 'id');
                    $spans = '';
                    foreach ($ids as $id) {
                        if (isset($names[$id])) {
                            $spans .= '<span class="badge badge-info mr-1 mb-1">' . $names[$id] . '</span>';
                        }
                    }
                    return $spans ? '<div style="max-width: 200px; white-space: normal; line-height: 2;">' . $spans . '</div>' : '-';
                })
                ->addColumn('company', function ($product) {
                    return $product->genericCompany->name ?? '-';
                })
                ->addColumn('type', function ($product) {
                    return $product->genericType->name ?? '-';
                })
                ->addColumn('action', function ($row) {
                    $buttons = '';
                    $buttons .= '<a href="'.route('generic_products.edit', $row->id).'" class="btn btn-warning btn-sm ml-1"><i class="fas fa-edit"></i> Edit</a>';
                    $buttons .= '<button class="btn btn-info btn-sm btn-setup-wizard ml-1" data-id="'.$row->id.'"><i class="fas fa-cogs"></i> Master Setup Wizard</button>';
                    $buttons .= '<a href="'.route('generic_products.approve', $row->id).'" class="btn btn-success btn-sm ml-1"><i class="fas fa-check"></i> Approve Selection</a>';
                    return $buttons;
                })
                ->rawColumns(['checkbox', 'strength', 'farmula', 'action'])
                ->make(true);
        }
        return view('admin.generic_products.suggestions', compact('title'));
    }

    public function bulkApprove(Request $request)
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        $request->validate(['ids' => 'required|array']);
        
        GenericProduct::whereIn('id', $request->ids)->update([
            'status' => 'approved',
            'approved_by' => auth()->id()
        ]);
        
        return response()->json(['success' => true, 'message' => count($request->ids) . ' products approved directly into masterlist.']);
    }

    public function autocompleteName(Request $request)
    {
        $term = $request->input('q');
        $page = $request->input('page', 1);

        $query = GenericProduct::where('status', 'approved');
        
        // Exclude generic products already imported by this business via highly optimized subquery
        $business_id = session('business_id');
        if($business_id) {
            $query->whereNotExists(function ($q) use ($business_id) {
                $q->select(DB::raw(1))
                  ->from('products')
                  ->whereColumn('products.generic_product_id', 'generic_products.id')
                  ->where('products.business_id', $business_id);
            });
        }

        if ($term) {
            $query->where('product_name', 'LIKE', '%' . $term . '%');
        }

        $products = $query->select('id', 'product_name as text')
            ->paginate(20, ['*'], 'page', $page);

        return response()->json([
            'results' => $products->items(),
            'pagination' => ['more' => $products->hasMorePages()]
        ]);
    }

    public function suggest()
    {
        $title = 'Suggest Generic Product';
        $companies   = \App\Models\GenericCompany::where('status', 'approved')->get();
        $farmulas    = \App\Models\GenericFarmula::where('status', 'approved')->get();
        $productTypes = \App\Models\GenericProductType::where('status', 'approved')->get();
        $strengths    = \App\Models\GenericStrength::where('status', 'approved')->get();

        return view('admin.generic_products.suggest', compact('title', 'companies', 'farmulas', 'productTypes', 'strengths'));
    }

    public function storeSuggestion(Request $request)
    {
        $request->validate([
            'product_name'     => 'required|max:200',
            'description'      => 'nullable|max:255',
            'company_id'       => 'required',
            'farmula_id'       => 'nullable|array',
            'product_type_id'  => 'required',
            'strength_id'      => 'nullable|array',
            'barcode'          => 'nullable|max:100',
            'discount'         => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack'          => 'nullable',
        ]);

        $productName = $request->product_name;

        // Resolve Type Name
        $typeId = $request->product_type_id;
        $typeName = null;
        if (is_numeric($typeId) && $type = \App\Models\GenericProductType::find($typeId)) {
            $typeName = $type->name;
        } else {
            $typeName = (string)$typeId;
        }

        // Resolve Strengths
        $strengthNames = [];
        if ($request->strength_id) {
            foreach ($request->strength_id as $s_id) {
                if (is_numeric($s_id) && $strength = \App\Models\GenericStrength::find($s_id)) {
                    $strengthNames[] = $strength->name;
                } else {
                    $strengthNames[] = (string)$s_id;
                }
            }
        }

        // Resolve Farmulas
        $farmulaNames = [];
        if ($request->farmula_id) {
            foreach ($request->farmula_id as $f_id) {
                if (is_numeric($f_id) && $farmula = \App\Models\GenericFarmula::find($f_id)) {
                    $farmulaNames[] = $farmula->name;
                } else {
                    $farmulaNames[] = (string)$f_id;
                }
            }
        }

        if (\App\Services\GenericProductService::genericProductExists($productName, $typeName, $strengthNames, $farmulaNames)) {
            return redirect()->back()->withInput()->with('error', 'A Generic Product with this exact configuration (Name, Type, Strengths, Farmulas) already exists.');
        }

        $product = DB::transaction(function() use ($request) {
            $business_id = session('business_id');

            $status = 'pending';
            $approvedBy = null;
            if (auth()->user()->hasRole('super-admin') && !session()->has('impersonate_business_id')) {
                $status = 'approved';
                $approvedBy = auth()->id();
            }

            // Find or create company
            $companyId = $request->company_id;
            if (!is_numeric($companyId) || !\App\Models\GenericCompany::find($companyId)) {
                $companyId = \App\Models\GenericCompany::firstOrCreate(
                    ['name' => $companyId],
                    ['status' => $status, 'suggested_by_business_id' => $business_id, 'approved_by' => $approvedBy]
                )->id;
            }

            // Find or create type
            $typeId = $request->product_type_id;
            if (!is_numeric($typeId) || !\App\Models\GenericProductType::find($typeId)) {
                $typeId = \App\Models\GenericProductType::firstOrCreate(
                    ['name' => $typeId],
                    ['status' => $status, 'suggested_by_business_id' => $business_id, 'approved_by' => $approvedBy]
                )->id;
            }

            // Farmula
            $processedFarmulaIds = [];
            if ($request->farmula_id) {
                foreach ($request->farmula_id as $f_id) {
                    if (is_numeric($f_id) && \App\Models\GenericFarmula::find($f_id)) {
                        $processedFarmulaIds[] = $f_id;
                    } else {
                        $newFarmula = \App\Models\GenericFarmula::firstOrCreate(
                            ['name' => $f_id],
                            ['status' => $status, 'suggested_by_business_id' => $business_id, 'approved_by' => $approvedBy]
                        );
                        $processedFarmulaIds[] = $newFarmula->id;
                    }
                }
            }

            // Strength
            // Process Strength
            if ($request->strength_id) {
                foreach ($request->strength_id as $s_id) {
                    if (is_numeric($s_id) && \App\Models\GenericStrength::find($s_id)) {
                        $processedStrengthIds[] = $s_id;
                    } else {
                        $exists = \App\Models\GenericStrength::where('name', $s_id)->exists();
                        if ($exists) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'strength_id' => "The strength '{$s_id}' already exists. Please select it from the dropdown."
                            ]);
                        }
                        $newStrength = \App\Models\GenericStrength::create([
                            'name' => $s_id, 'status' => $status, 'suggested_by_business_id' => $business_id, 'approved_by' => $approvedBy
                        ]);
                        $processedStrengthIds[] = $newStrength->id;
                    }
                }
            }

            // Create product suggestion
            $createdProduct = GenericProduct::create([
                'product_name' => $request->product_name,
                'generic_company_id' => $companyId,
                'generic_product_type_id' => $typeId,
                'farmula_id' => !empty($processedFarmulaIds) ? implode(',', $processedFarmulaIds) : null,
                'strength_id' => !empty($processedStrengthIds) ? implode(',', $processedStrengthIds) : null,
                'description' => $request->description,
                'barcode' => $request->barcode,
                'discount' => $request->discount ?? 0,
                'discount_percent' => $request->discount_percent ?? 0,
                'lock_max_discount' => $request->has('lock_max_discount'),
                'rack' => $request->rack,
                'status' => $status,
                'approved_by' => $approvedBy,
                'suggested_by_business_id' => $business_id
            ]);
            
            return $createdProduct;
        });

        return redirect()->route('generic_products.index')->with(notify('Product saved successfully.'))->with('auto_open_generic_wizard', $product->id);
    }

    public function edit($id)
    {
        $title = 'Edit Generic Product';
        $product = GenericProduct::findOrFail($id);
        $companies   = \App\Models\GenericCompany::where('status', 'approved')->get();
        $farmulas    = \App\Models\GenericFarmula::where('status', 'approved')->get();
        $productTypes = \App\Models\GenericProductType::where('status', 'approved')->get();
        $strengths    = \App\Models\GenericStrength::where('status', 'approved')->get();

        return view('admin.generic_products.edit', compact('title', 'product', 'companies', 'farmulas', 'productTypes', 'strengths'));
    }

    public function update(Request $request, $id)
    {
        $product = GenericProduct::findOrFail($id);
        if(!auth()->user()->hasRole('super-admin') && $product->suggested_by_business_id != session('business_id')) {
            abort(403);
        }

        $request->validate([
            'product_name'     => 'required|max:200',
            'description'      => 'nullable|max:255',
            'company_id'       => 'required',
            'farmula_id'       => 'nullable|array',
            'product_type_id'  => 'required',
            'strength_id'      => 'nullable|array',
            'barcode'          => 'nullable|max:100',
            'discount'         => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack'          => 'nullable',
        ]);

        // Resolve Company
        $companyId = $request->company_id;
        if (!is_numeric($companyId) || !\App\Models\GenericCompany::find($companyId)) {
            $companyId = \App\Models\GenericCompany::firstOrCreate(['name' => $companyId])->id;
        }

        // Resolve Type
        $typeId = $request->product_type_id;
        if (!is_numeric($typeId) || !\App\Models\GenericProductType::find($typeId)) {
            $typeId = \App\Models\GenericProductType::firstOrCreate(['name' => $typeId])->id;
        }

        // Farmula
        $processedFarmulaIds = [];
        if ($request->farmula_id) {
            foreach ($request->farmula_id as $f_id) {
                if (is_numeric($f_id) && \App\Models\GenericFarmula::find($f_id)) {
                    $processedFarmulaIds[] = $f_id;
                } else {
                    $newFarmula = \App\Models\GenericFarmula::firstOrCreate(['name' => $f_id]);
                    $processedFarmulaIds[] = $newFarmula->id;
                }
            }
        }

        // Strength
        $processedStrengthIds = [];
        if ($request->strength_id) {
            foreach ($request->strength_id as $s_id) {
                if (is_numeric($s_id) && \App\Models\GenericStrength::find($s_id)) {
                    $processedStrengthIds[] = $s_id;
                } else {
                    $newStrength = \App\Models\GenericStrength::firstOrCreate(['name' => $s_id]);
                    $processedStrengthIds[] = $newStrength->id;
                }
            }
        }

        $product->update([
            'product_name' => $request->product_name,
            'generic_company_id' => $companyId,
            'generic_product_type_id' => $typeId,
            'farmula_id' => !empty($processedFarmulaIds) ? implode(',', $processedFarmulaIds) : null,
            'strength_id' => !empty($processedStrengthIds) ? implode(',', $processedStrengthIds) : null,
            'description' => $request->description,
            'barcode' => $request->barcode,
            'discount' => $request->discount ?? 0,
            'discount_percent' => $request->discount_percent ?? 0,
            'lock_max_discount' => $request->has('lock_max_discount'),
            'rack' => $request->rack,
        ]);

        return redirect()->route('generic_products.index')->with(notify('Product updated successfully.'));
    }

    public function approve($id)
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        $product = GenericProduct::findOrFail($id);
        $product->update(['status' => 'approved']);
        return redirect()->back()->with(notify('Product Approved'));
    }

    public function reject($id)
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        $product = GenericProduct::findOrFail($id);
        $product->update(['status' => 'rejected']);
        return redirect()->back()->with(notify('Product Rejected', 'danger'));
    }

    public function syncAll()
    {
        if(!auth()->user()->hasRole('super-admin')) abort(403);
        
        $products = Product::withoutGlobalScopes()->get();
        $count = 0;
        foreach ($products as $product) {
            \App\Services\GenericProductService::syncProductToGeneric($product);
            $count++;
        }
        
        return redirect()->route('generic_products.index')->with(notify("Successfully synced {$count} products to masterlist."));
    }

    // index / import / bulkImport / importAll / details and the batch processor moved to
    // Catalogue\GenericCatalogController + Services\Catalogue\GenericImportService (Roadmap 5.6a).

    public function setupWizard($id)
    {
        $product = GenericProduct::findOrFail($id);

        $relations = \App\Models\GenericProductCategory::with(['parentCategory', 'childCategory'])
            ->where('generic_product_id', $product->id)
            ->get();

        $productCategory = $relations->first();
        $parentCategoryId = $productCategory ? $productCategory->parent_generic_category_id : null;
        $childCategoryId  = $productCategory ? $productCategory->child_generic_category_id : null;

        $lastChildId = null;

        if ($relations->count()) {
            $map = [];
            foreach ($relations as $r) {
                $map[(int)$r->parent_generic_category_id] = (int)$r->child_generic_category_id;
            }
            $parents = array_keys($map);
            $children = array_values($map);
            $start = null;
            foreach ($parents as $p) {
                if (! in_array($p, $children, true)) {
                    $start = $p;
                    break;
                }
            }
            if ($start === null) {
                $start = (int)$relations->first()->parent_generic_category_id;
            }
            $current = $start;
            while (isset($map[$current])) {
                if ($map[$current] == $current) break; // prevent infinite loop
                $current = $map[$current];
            }
            $lastChildId = $current;
        }

        $parentCategories = $lastChildId ? \App\Models\GenericCategory::where('id', $lastChildId)->get() : \App\Models\GenericCategory::all();

        $usedParentIds = $relations->pluck('parent_generic_category_id')->filter()->toArray();
        $usedChildIds  = $relations->pluck('child_generic_category_id')->filter()->toArray();
        $exclude = array_values(array_filter(array_unique(array_merge($usedParentIds, $usedChildIds))));
        $childCategories = \App\Models\GenericCategory::when(count($exclude), function ($q) use ($exclude) {
            $q->whereNotIn('id', $exclude);
        })->get();

        $baseCategory = $productCategory ? $productCategory->parentCategory : null;
        while ($baseCategory && method_exists($baseCategory, 'parentCategory') && $baseCategory->parentCategory) {
            $baseCategory = $baseCategory->parentCategory;
        }

        $children = $relations->map(function($r) {
            if ($r->parent_generic_category_id == $r->child_generic_category_id) {
                return null;
            }
            if ($r->childCategory) {
                $r->childCategory->parent_id = $r->parent_generic_category_id;
                $r->childCategory->setRelation('parent', $r->parentCategory);
                return $r->childCategory;
            }
            return null;
        })->filter();

        $parameters = \App\Models\GenericProductParameter::where('generic_product_id', $product->id)->with(['parentCategory', 'childCategory'])->get()->keyBy('child_generic_category_id');

        return view('admin.generic_products.generic_wizard_slider', compact(
            'product', 'productCategory', 'parentCategoryId', 'childCategoryId',
            'relations', 'parentCategories', 'childCategories', 'lastChildId',
            'baseCategory', 'children', 'parameters'
        ));
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:generic_products,id',
            'parent_category_id' => 'required'
        ]);

        $productId = $request->product_id;
        $parentId = $request->parent_category_id;
        $childId = $request->child_category_id;

        if (!is_numeric($parentId) || !\App\Models\GenericCategory::find($parentId)) {
            $parentId = \App\Models\GenericCategory::firstOrCreate(['name' => $parentId])->id;
        }
        
        if ($request->has('single_packaging') && $request->single_packaging == "1") {
            $childId = $parentId;
        } else {
            $request->validate(['child_category_id' => 'required']);
            if (!is_numeric($childId) || !\App\Models\GenericCategory::find($childId)) {
                $childId = \App\Models\GenericCategory::firstOrCreate(['name' => $childId])->id;
            }
            if ($parentId == $childId) {
                return response()->json(['message' => 'Parent and child categories must be different.'], 422);
            }
        }

        $singleExists = \App\Models\GenericProductCategory::where('generic_product_id', $productId)->whereColumn('parent_generic_category_id', 'child_generic_category_id')->exists();
        if ($singleExists) {
            return response()->json(['message' => 'Single packaging is already defined. Delete it first.'], 422);
        }

        \App\Models\GenericProductCategory::create([
            'generic_product_id' => $productId,
            'parent_generic_category_id' => $parentId,
            'child_generic_category_id' => $childId
        ]);

        return response()->json(['message' => 'Hierarchy relation added successfully.']);
    }

    public function destroyCategory($id)
    {
        $relation = \App\Models\GenericProductCategory::findOrFail($id);
        $productId = $relation->generic_product_id;
        
        \App\Models\GenericProductParameter::where('generic_product_id', $productId)->delete();
        $relation->delete();

        return response()->json(['message' => 'Relation and downstream pricing deleted.']);
    }

    public function storeParameters(Request $request, $id)
    {
        $product = GenericProduct::findOrFail($id);
        \App\Models\GenericProductParameter::where('generic_product_id', $id)->delete();

        if (is_array($request->parameters)) {
            foreach ($request->parameters as $index => $param) {
                \App\Models\GenericProductParameter::create([
                    'generic_product_id' => $id,
                    'generic_category_id' => $param['category_id'] ?? $param['child_category_id'],
                    'parent_generic_category_id' => $param['parent_category_id'],
                    'child_generic_category_id' => $param['child_category_id'],
                    'quantity' => $param['quantity'] ?? 1,
                    'static_category_unit_purchase_price' => $param['static_category_unit_purchase_price'] ?? 0,
                    'static_category_unit_sale_price' => $param['static_category_unit_sale_price'] ?? 0,
                ]);
            }
        }
        
        return response()->json(['message' => 'Generic packaging & pricing parameters saved successfully.', 'promoted' => true]);
    }
}
