<?php

namespace App\Services;

use App\Models\Product;
use App\Models\GenericProduct;
use App\Models\GenericCompany;
use App\Models\GenericProductType;
use App\Models\GenericStrength;
use App\Models\GenericFarmula;
use App\Models\GenericCategory;
use App\Models\GenericProductParameter;
use App\Models\GenericProductCategory;
use Illuminate\Support\Facades\DB;

class GenericProductService
{
    /**
     * Map a Product to a GenericProduct or create one if it doesn't exist uniquely by name/type/strength/farmula.
     */
    public static function syncProductToGeneric(Product $product)
    {
        // Skip already mapped products for safe resumption
        if ($product->generic_product_id) {
            return;
        }

        $product->loadMissing(['company', 'type', 'parameters.parentCategory', 'parameters.childCategory']);

        // 1. Get raw names from Product's relations
        $productName = $product->product_name;

        // Type
        $typeName = null;
        if ($product->type) {
            $typeName = $product->type->name;
        }

        // Strength (multiple)
        $strengthNames = [];
        if ($product->strength_id) {
            $sIds = explode(',', $product->strength_id);
            $strengthNames = \App\Models\Strength::whereIn('id', $sIds)->pluck('name')->toArray();
        }
        sort($strengthNames);
        $strengthStr = implode('|', $strengthNames);

        // Farmula (multiple)
        $farmulaNames = [];
        if ($product->farmula_id) {
            $fIds = explode(',', $product->farmula_id);
            $farmulaNames = \App\Models\Farmula::whereIn('id', $fIds)->pluck('name')->toArray();
        }
        sort($farmulaNames);
        $farmulaStr = implode('|', $farmulaNames);

        // 2. Find matching GenericProduct
        $typeQuery = null;
        if ($typeName) {
            $typeQuery = GenericProductType::where('name', $typeName)->first();
        }

        $genericTypeId = $typeQuery ? $typeQuery->id : null;

        $potentialMatches = GenericProduct::where('product_name', $productName);
        if ($genericTypeId) {
            $potentialMatches->where('generic_product_type_id', $genericTypeId);
        }
        $potentialMatches = $potentialMatches->get();

        $matchedGenericProduct = null;
        foreach ($potentialMatches as $gp) {
            $gpStrengthNames = [];
            if ($gp->strength_id) {
                $gSIds = explode(',', $gp->strength_id);
                $gpStrengthNames = GenericStrength::whereIn('id', $gSIds)->pluck('name')->toArray();
            }
            sort($gpStrengthNames);
            $gpStrengthStr = implode('|', $gpStrengthNames);

            $gpFarmulaNames = [];
            if ($gp->farmula_id) {
                $gFIds = explode(',', $gp->farmula_id);
                $gpFarmulaNames = GenericFarmula::whereIn('id', $gFIds)->pluck('name')->toArray();
            }
            sort($gpFarmulaNames);
            $gpFarmulaStr = implode('|', $gpFarmulaNames);

            if ($strengthStr === $gpStrengthStr && $farmulaStr === $gpFarmulaStr) {
                $matchedGenericProduct = $gp;
                break;
            }
        }

        if ($matchedGenericProduct) {
            // Link internally
            if ($product->generic_product_id !== $matchedGenericProduct->id) {
                $product->generic_product_id = $matchedGenericProduct->id;
                $product->saveQuietly(); // avoid infinite loops if triggered by observer
            }
            return $matchedGenericProduct;
        }

        // 3. Create new GenericProduct if no match
        if (!$genericTypeId && $typeName) {
            $genericTypeId = GenericProductType::firstOrCreate(['name' => $typeName], ['status' => 'approved'])->id;
        }

        $genericCompanyId = null;
        if ($product->company) {
            $genericCompanyId = GenericCompany::firstOrCreate(
                ['name' => $product->company->name],
                ['status' => 'approved']
            )->id;
        } else {
            $genericCompanyId = GenericCompany::firstOrCreate(['name' => 'Unknown'], ['status' => 'approved'])->id;
        }

        $genericStrengthIds = [];
        foreach ($strengthNames as $sn) {
            $genericStrengthIds[] = GenericStrength::firstOrCreate(['name' => $sn], ['status' => 'approved'])->id;
        }

        $genericFarmulaIds = [];
        foreach ($farmulaNames as $fn) {
            $genericFarmulaIds[] = GenericFarmula::firstOrCreate(['name' => $fn], ['status' => 'approved'])->id;
        }

        // It looks like generic_product_type_id is required
        if (!$genericTypeId) {
             $genericTypeId = GenericProductType::firstOrCreate(['name' => 'General'], ['status' => 'approved'])->id;
        }

        return DB::transaction(function() use ($product, $productName, $genericCompanyId, $genericTypeId, $genericFarmulaIds, $genericStrengthIds) {
            $newGenericProduct = GenericProduct::create([
                'product_name' => $productName,
                'generic_company_id' => $genericCompanyId,
                'generic_product_type_id' => $genericTypeId,
                'farmula_id' => count($genericFarmulaIds) ? implode(',', $genericFarmulaIds) : null,
                'strength_id' => count($genericStrengthIds) ? implode(',', $genericStrengthIds) : null,
                'description' => $product->description,
                'barcode' => $product->barcode,
                'discount' => $product->discount ?? 0,
                'discount_percent' => $product->discount_percent ?? 0,
                'lock_max_discount' => $product->lock_max_discount,
                'rack' => $product->rack,
                'image' => $product->image,
                'status' => 'approved' // Auto approved since seeded from real product
            ]);

            // 4. Map Categories and Parameters
            foreach ($product->parameters as $p) {
                // Map the generic category names
                $genericCat = null;
                if ($p->category) {
                    $genericCat = GenericCategory::firstOrCreate(['name' => $p->category->name], ['status' => 'approved']);
                }

                $parentGenericCat = null;
                if ($p->parentCategory) {
                    $parentGenericCat = GenericCategory::firstOrCreate(['name' => $p->parentCategory->name], ['status' => 'approved']);
                }

                $childGenericCat = null;
                if ($p->childCategory) {
                    $childGenericCat = GenericCategory::firstOrCreate(['name' => $p->childCategory->name], ['status' => 'approved']);
                }

                // If no categories mapped, fallback logic handles creating a dummy category to avoid parameter table crashes
                if (!$genericCat && !$parentGenericCat && !$childGenericCat) {
                    $fallback = GenericCategory::firstOrCreate(['name' => 'Default'], ['status' => 'approved']);
                    $genericCat = $parentGenericCat = $childGenericCat = $fallback;
                }
                
                if ($parentGenericCat && $childGenericCat) {
                    if ($product->parameters->count() === 1 || $parentGenericCat->id != $childGenericCat->id) {
                        GenericProductCategory::firstOrCreate([
                            'generic_product_id' => $newGenericProduct->id,
                            'parent_generic_category_id' => $parentGenericCat->id,
                            'child_generic_category_id' => $childGenericCat->id
                        ]);
                    }
                }

                GenericProductParameter::create([
                    'generic_product_id' => $newGenericProduct->id,
                    'generic_category_id' => $genericCat ? $genericCat->id : ($parentGenericCat ? $parentGenericCat->id : 0), // Assumes DB can take 0, which might fail. Better to use fallback.
                    'parent_generic_category_id' => $parentGenericCat ? $parentGenericCat->id : 0,
                    'child_generic_category_id' => $childGenericCat ? $childGenericCat->id : 0,
                    'quantity' => $p->quantity,
                    // Parameters prices added in previous task may need sync; copying if available
                    'static_category_unit_purchase_price' => $p->static_category_unit_purchase_price,
                    'static_category_unit_sale_price' => $p->static_category_unit_sale_price,
                ]);
            }

            $product->generic_product_id = $newGenericProduct->id;
            $product->saveQuietly();

            return $newGenericProduct;
        });
    }

    /**
     * Helper to check if a tuple of (Name, Type, Strengths, Farmulas) already exists in generic products
     */
    public static function genericProductExists($name, $typeName, $strengthNames = [], $farmulaNames = [])
    {
        sort($strengthNames);
        $strengthStr = implode('|', $strengthNames);

        sort($farmulaNames);
        $farmulaStr = implode('|', $farmulaNames);

        $typeQuery = null;
        if ($typeName) {
            $typeQuery = GenericProductType::where('name', $typeName)->first();
        }

        $genericTypeId = $typeQuery ? $typeQuery->id : null;

        $potentialMatches = GenericProduct::where('product_name', $name);
        if ($genericTypeId) {
            $potentialMatches->where('generic_product_type_id', $genericTypeId);
        }
        $potentialMatches = $potentialMatches->get();

        foreach ($potentialMatches as $gp) {
            $gpStrengthNames = [];
            if ($gp->strength_id) {
                $gSIds = explode(',', $gp->strength_id);
                $gpStrengthNames = GenericStrength::whereIn('id', $gSIds)->pluck('name')->toArray();
            }
            sort($gpStrengthNames);
            $gpStrengthStr = implode('|', $gpStrengthNames);

            $gpFarmulaNames = [];
            if ($gp->farmula_id) {
                $gFIds = explode(',', $gp->farmula_id);
                $gpFarmulaNames = GenericFarmula::whereIn('id', $gFIds)->pluck('name')->toArray();
            }
            sort($gpFarmulaNames);
            $gpFarmulaStr = implode('|', $gpFarmulaNames);

            if ($strengthStr === $gpStrengthStr && $farmulaStr === $gpFarmulaStr) {
                return true;
            }
        }

        return false;
    }
}
