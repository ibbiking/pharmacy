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
    public function index(Request $request)
    {
        $title = 'Generic Products Masterlist';
        if ($request->ajax()) {
            // All users see only approved in index
            $products = GenericProduct::with(['genericCompany', 'genericType'])->where('status', 'approved');

            // Exclude already imported products for business owners
            $business_id = session('business_id');
            if (!auth()->user()->hasRole('super-admin') || session()->has('impersonate_business_id')) {
                if($business_id) {
                    $products->whereNotExists(function ($query) use ($business_id) {
                        $query->select(DB::raw(1))
                              ->from('products')
                              ->whereColumn('products.generic_product_id', 'generic_products.id')
                              ->where('products.business_id', $business_id);
                    });
                }
            }

            // Get queued IDs
            $queuedIds = [];
            if ($business_id) {
                $queuedIds = DB::table('generic_product_import_batches')
                    ->where('business_id', $business_id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->pluck('product_ids')
                    ->flatMap(function ($json) { return json_decode($json, true); })
                    ->unique()
                    ->toArray();
            }

            return DataTables::of($products)
                ->filterColumn('company', function ($query, $keyword) {
                    $query->whereHas('genericCompany', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('type', function ($query, $keyword) {
                    $query->whereHas('genericType', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('strength', function ($query, $keyword) {
                    $strengthIds = \App\Models\GenericStrength::where('name', 'like', "%{$keyword}%")->pluck('id');
                    if ($strengthIds->count()) {
                        $query->where(function ($q) use ($strengthIds) {
                            foreach ($strengthIds as $id) {
                                $q->orWhere('strength_id', 'like', $id . ',%')
                                  ->orWhere('strength_id', 'like', '%,' . $id . ',%')
                                  ->orWhere('strength_id', 'like', '%,' . $id)
                                  ->orWhere('strength_id', (string)$id);
                            }
                        });
                    } else {
                        $query->whereRaw("1 = 0");
                    }
                })
                ->filterColumn('farmula', function ($query, $keyword) {
                    $farmulaIds = \App\Models\GenericFarmula::where('name', 'like', "%{$keyword}%")->pluck('id');
                    if ($farmulaIds->count()) {
                        $query->where(function ($q) use ($farmulaIds) {
                            foreach ($farmulaIds as $id) {
                                $q->orWhere('farmula_id', 'like', $id . ',%')
                                  ->orWhere('farmula_id', 'like', '%,' . $id . ',%')
                                  ->orWhere('farmula_id', 'like', '%,' . $id)
                                  ->orWhere('farmula_id', (string)$id);
                            }
                        });
                    } else {
                        $query->whereRaw("1 = 0");
                    }
                })
                ->addColumn('checkbox', function($row) use ($queuedIds) {
                    if(!auth()->user()->hasRole('super-admin') || session()->has('impersonate_business_id')) {
                        if (in_array($row->id, $queuedIds)) {
                            return '<input type="checkbox" disabled title="Already queued for import">';
                        }
                        return '<input type="checkbox" class="generic-checkbox" value="'.$row->id.'">';
                    }
                    return '';
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
                ->addColumn('status_badge', function ($row) {
                    if($row->status == 'approved') return '<span class="badge badge-success">Approved</span>';
                    if($row->status == 'pending') return '<span class="badge badge-warning">Pending</span>';
                    return '<span class="badge badge-danger">Rejected</span>';
                })
                ->addColumn('action', function ($row) use ($queuedIds) {
                    $buttons = '';
                    $buttons .= '<button class="btn btn-secondary btn-sm btn-view-generic-details ml-1" data-id="'.$row->id.'"><i class="fas fa-eye"></i> Details</button>';
                    if (!auth()->user()->hasRole('super-admin') || session()->has('impersonate_business_id')) {
                        if (in_array($row->id, $queuedIds)) {
                            $buttons .= '<button class="btn btn-warning btn-sm ml-1" disabled><i class="fas fa-spinner fa-spin"></i> Queued</button>';
                        } else {
                            $buttons .= '<button class="btn btn-primary btn-sm import-generic ml-1" data-id="'.$row->id.'"><i class="fas fa-download"></i> Import</button>';
                        }
                    }
                    if (auth()->user()->hasRole('super-admin') && !session()->has('impersonate_business_id')) {
                        // Natively, Super admin gets access to strictly manage generic pricing and parameters
                        $buttons .= '<button class="btn btn-info btn-sm btn-setup-wizard ml-1" data-id="'.$row->id.'"><i class="fas fa-cogs"></i> Master Setup</button>';
                    } elseif (!auth()->user()->hasRole('super-admin') && $row->suggested_by_business_id == session('business_id')) {
                        // Business Owner who suggested it ALSO gets to define its generic params while pending
                        $buttons .= '<button class="btn btn-info btn-sm btn-setup-wizard ml-1" data-id="'.$row->id.'"><i class="fas fa-cogs"></i> Setup Wizard</button>';
                    }
                    return $buttons;
                })
                ->rawColumns(['checkbox', 'status_badge', 'action', 'strength', 'farmula'])
                ->make(true);
        }

        return view('admin.generic_products.index', compact('title'));
    }

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

    /**
     * Import the generic product into the local business database.
     */
    public function import(Request $request)
    {
        $generic = GenericProduct::with(['parameters', 'genericCompany', 'genericType'])->findOrFail($request->product_id);
        $business_id = session('business_id');

        if (Product::where('generic_product_id', $generic->id)->where('business_id', $business_id)->exists()) {
            return response()->json(['error' => 'Product already exists in your business.']);
        }

        $localProduct = $this->importGenericProductToBusiness($generic, $business_id);

        return response()->json([
            'success' => 'Imported successfully.',
            'product_id' => $localProduct->id ?? null
        ]);
    }

    public function bulkImport(Request $request)
    {
        $ids = $request->product_ids;
        if (!$ids || !is_array($ids)) {
            return response()->json(['error' => 'No products selected']);
        }

        $business_id = session('business_id');
        if (!$business_id) {
            return response()->json(['error' => 'No business selected for import.'], 422);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        DB::table('generic_product_import_batches')->insert([
            'business_id' => $business_id,
            'requested_by' => auth()->id(),
            'product_ids' => json_encode($ids),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => 'Items queued for import. They will be imported soon (runs every 15 minutes).'
        ]);
    }

    public function importAll(Request $request)
    {
        $business_id = session('business_id');
        if (!$business_id) {
            return response()->json(['error' => 'No business selected for import.'], 422);
        }

        // Find all approved products not yet imported by this business
        $query = GenericProduct::where('status', 'approved')
            ->whereNotExists(function ($q) use ($business_id) {
                $q->select(DB::raw(1))
                  ->from('products')
                  ->whereColumn('products.generic_product_id', 'generic_products.id')
                  ->where('products.business_id', $business_id);
            });

        // Also exclude those already pending/processing to avoid duplicates in batches
        $queuedIds = DB::table('generic_product_import_batches')
            ->where('business_id', $business_id)
            ->whereIn('status', ['pending', 'processing'])
            ->pluck('product_ids')
            ->flatMap(function ($json) { return json_decode($json, true); })
            ->unique()
            ->toArray();

        if (!empty($queuedIds)) {
            $query->whereNotIn('id', $queuedIds);
        }

        $allIds = $query->pluck('id')->toArray();

        if (empty($allIds)) {
            return response()->json(['error' => 'No new products available to import.']);
        }

        // Chunking the ids if too large (e.g. 500 per batch)
        $chunks = array_chunk($allIds, 500);
        foreach ($chunks as $chunk) {
            DB::table('generic_product_import_batches')->insert([
                'business_id' => $business_id,
                'requested_by' => auth()->id(),
                'product_ids' => json_encode($chunk),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => count($allIds) . ' total items queued for import in the background.'
        ]);
    }

    public function details($id)
    {
        $product = GenericProduct::with([
            'genericCompany',
            'genericType',
            'parameters.parentCategory',
            'parameters.childCategory',
            'parameters.genericCategory',
        ])->findOrFail($id);

        $strengthNames = [];
        if (!empty($product->strength_id)) {
            $strengthIds = array_filter(explode(',', $product->strength_id));
            if (!empty($strengthIds)) {
                $strengthNames = \App\Models\GenericStrength::whereIn('id', $strengthIds)->pluck('name')->toArray();
            }
        }

        $farmulaNames = [];
        if (!empty($product->farmula_id)) {
            $farmulaIds = array_filter(explode(',', $product->farmula_id));
            if (!empty($farmulaIds)) {
                $farmulaNames = \App\Models\GenericFarmula::whereIn('id', $farmulaIds)->pluck('name')->toArray();
            }
        }

        $parameters = $product->parameters->map(function ($param) {
            return [
                'parent_category' => $param->parentCategory->name ?? '-',
                'child_category' => $param->childCategory->name ?? '-',
                'quantity' => (float) $param->quantity,
                'purchase_price' => (float) $param->static_category_unit_purchase_price,
                'sale_price' => (float) $param->static_category_unit_sale_price,
            ];
        });

        return view('admin.products.partials.details-modal-content', [
            'detailsTitle' => 'Generic Product Details',
            'itemName' => $product->product_name,
            'itemId' => $product->id,
            'statusLabel' => ucfirst($product->status),
            'statusClass' => $product->status === 'approved' ? 'badge-success' : ($product->status === 'pending' ? 'badge-warning' : 'badge-danger'),
            'barcode' => $product->barcode,
            'description' => $product->description,
            'companyName' => $product->genericCompany->name ?? '-',
            'typeName' => $product->genericType->name ?? '-',
            'rack' => $product->rack,
            'discountAmount' => (float) ($product->discount ?? 0),
            'discountPercent' => (float) ($product->discount_percent ?? 0),
            'strengthNames' => $strengthNames,
            'farmulaNames' => $farmulaNames,
            'parameters' => $parameters,
        ]);
    }

    public static function processPendingBulkImportBatches(): array
    {
        $processedBatches = 0;
        $importedTotal = 0;
        $skippedTotal = 0;

        DB::table('generic_product_import_batches')
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(25, function ($batches) use (&$processedBatches, &$importedTotal, &$skippedTotal) {
                foreach ($batches as $batch) {
                    $importedCount = 0;
                    $skippedCount = 0;

                    DB::transaction(function () use ($batch, &$importedCount, &$skippedCount) {
                        DB::table('generic_product_import_batches')
                            ->where('id', $batch->id)
                            ->update([
                                'status' => 'processing',
                                'updated_at' => now(),
                            ]);

                        $ids = json_decode($batch->product_ids, true) ?: [];
                        foreach ($ids as $id) {
                            $generic = GenericProduct::with(['parameters', 'genericCompany', 'genericType'])->find((int) $id);
                            if (!$generic) {
                                $skippedCount++;
                                continue;
                            }

                            if (Product::where('generic_product_id', $generic->id)->where('business_id', $batch->business_id)->exists()) {
                                $skippedCount++;
                                continue;
                            }

                            app(self::class)->importGenericProductToBusiness($generic, (int) $batch->business_id);
                            $importedCount++;
                        }

                        DB::table('generic_product_import_batches')
                            ->where('id', $batch->id)
                            ->update([
                                'status' => 'completed',
                                'imported_count' => $importedCount,
                                'skipped_count' => $skippedCount,
                                'processed_at' => Carbon::now(),
                                'updated_at' => now(),
                            ]);
                    });

                    $processedBatches++;
                    $importedTotal += $importedCount;
                    $skippedTotal += $skippedCount;
                }
            });

        return [
            'processed' => $processedBatches,
            'imported' => $importedTotal,
            'skipped' => $skippedTotal,
        ];
    }

    private function importGenericProductToBusiness(GenericProduct $generic, int $business_id): Product
    {
        return DB::transaction(function () use ($generic, $business_id) {
            $previousBusinessId = session('business_id');
            session(['business_id' => $business_id]);

            try {
            $localCompany = Company::where('business_id', $business_id)
                ->where('name', $generic->genericCompany->name)->first();
            if (!$localCompany) {
                $localCompany = Company::create(['name' => $generic->genericCompany->name]);
            }

            $localType = ProductType::where('business_id', $business_id)
                ->where('name', $generic->genericType->name)->first();
            if (!$localType) {
                $localType = ProductType::create(['name' => $generic->genericType->name]);
            }

            $localStrengthIds = [];
            if ($generic->strength_id) {
                foreach (explode(',', $generic->strength_id) as $s_id) {
                    $genStrength = \App\Models\GenericStrength::find($s_id);
                    if ($genStrength) {
                        $localStrength = \App\Models\Strength::where('business_id', $business_id)
                            ->where('name', $genStrength->name)->first();
                        if (!$localStrength) $localStrength = \App\Models\Strength::create(['name' => $genStrength->name]);
                        $localStrengthIds[] = $localStrength->id;
                    }
                }
            }

            $localFarmulaIds = [];
            if ($generic->farmula_id) {
                foreach (explode(',', $generic->farmula_id) as $f_id) {
                    $genFarmula = \App\Models\GenericFarmula::find($f_id);
                    if ($genFarmula) {
                        $localFarmula = Farmula::where('business_id', $business_id)
                            ->where('name', $genFarmula->name)->first();
                        if (!$localFarmula) $localFarmula = Farmula::create(['name' => $genFarmula->name]);
                        $localFarmulaIds[] = $localFarmula->id;
                    }
                }
            }

            $localProduct = Product::create([
                'product_name' => $generic->product_name,
                'generic_product_id' => $generic->id,
                'company_id' => $localCompany->id,
                'product_type_id' => $localType->id,
                'strength_id' => count($localStrengthIds) ? implode(',', $localStrengthIds) : null,
                'farmula_id' => count($localFarmulaIds) ? implode(',', $localFarmulaIds) : null,
                'description' => $generic->description,
                'barcode' => $generic->barcode,
                'rack' => $generic->rack,
                'is_draft' => false,
                'sale_price_preference_id' => null,
            ]);

            foreach ($generic->parameters as $p) {
                $genericCat = \App\Models\GenericCategory::find($p->generic_category_id);
                $localCat = null;
                if ($genericCat) {
                    $localCat = Category::where('business_id', $business_id)
                        ->where('name', $genericCat->name)->first();
                    if (!$localCat) $localCat = Category::create(['name' => $genericCat->name]);
                }

                $parentLocalCat = null;
                if ($p->parentCategory) {
                    $parentLocalCat = Category::where('business_id', $business_id)
                        ->where('name', $p->parentCategory->name)->first();
                    if (!$parentLocalCat) $parentLocalCat = Category::create(['name' => $p->parentCategory->name]);
                }

                $childLocalCat = null;
                if ($p->childCategory) {
                    $childLocalCat = Category::where('business_id', $business_id)
                        ->where('name', $p->childCategory->name)->first();
                    if (!$childLocalCat) $childLocalCat = Category::create(['name' => $p->childCategory->name]);
                }

                if ($parentLocalCat && $childLocalCat) {
                    if (count($generic->parameters) === 1 || $parentLocalCat->id != $childLocalCat->id) {
                        ProductCategory::firstOrCreate([
                            'product_id' => $localProduct->id,
                            'parent_category_id' => $parentLocalCat->id,
                            'child_category_id' => $childLocalCat->id,
                        ]);
                    }
                }

                ProductParameter::create([
                    'product_id' => $localProduct->id,
                    'category_id' => $localCat ? $localCat->id : 0,
                    'parent_category_id' => $parentLocalCat ? $parentLocalCat->id : 0,
                    'child_category_id' => $childLocalCat ? $childLocalCat->id : 0,
                    'quantity' => $p->quantity,
                    'static_category_unit_purchase_price' => $p->static_category_unit_purchase_price,
                    'static_category_unit_sale_price' => $p->static_category_unit_sale_price,
                ]);
            }

            return $localProduct;
            } finally {
                if ($previousBusinessId) {
                    session(['business_id' => $previousBusinessId]);
                } else {
                    session()->forget('business_id');
                }
            }
        });
    }

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
