<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MapProductsToGenericSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Detect and patch legacy environment (pre-tenancy)
        $usersWithoutBusiness = \App\Models\User::doesntHave('businesses')->get();
        if ($usersWithoutBusiness->count() > 0) {
            $this->command->info("Detected legacy user(s) without a Business Context. Automating conversion...");
            $business = \App\Models\Business::firstOrCreate(
                ['name' => 'Legacy Pharmacy System'],
                ['address' => 'N/A', 'phone' => 'N/A', 'created_by' => $usersWithoutBusiness->first()->id]
            );

            $superAdminEmail = env('SUPERADMINEMAIL', 'admin@mail.com');

            foreach ($usersWithoutBusiness as $u) {
               // Protect Super Admin from being mapped to a business natively (strictly by configured Email) 
               if ($u->email === $superAdminEmail) {
                   if (class_exists(\Spatie\Permission\Models\Role::class) && !$u->hasRole('super-admin')) {
                       $u->assignRole('super-admin');
                   }
                   $this->command->info("Skipped designated Super Admin mapping for {$u->email}");
                   continue;
               }

               // Strip any legacy super-admin roles from regular converted users!
               if (class_exists(\Spatie\Permission\Models\Role::class) && $u->hasRole('super-admin')) {
                   $u->removeRole('super-admin');
               }

               $u->businesses()->attach($business->id, ['role' => 'owner']);
               if (class_exists(\Spatie\Permission\Models\Role::class) && !$u->hasRole('Business Owner')) {
                   $u->assignRole('Business Owner');
               }
            }

            // Map all tables to this business
            $models = [
                \App\Models\Tax::class, \App\Models\Supplier::class, \App\Models\Strength::class, 
                \App\Models\StockPrices::class, \App\Models\SaleTax::class, \App\Models\Sale::class, 
                \App\Models\ReturnHistory::class, \App\Models\PurchaseTax::class, \App\Models\Purchase::class, 
                \App\Models\ProductType::class, \App\Models\ProductStock::class, \App\Models\ProductParameter::class, 
                \App\Models\ProductCategory::class, \App\Models\Product::class, \App\Models\Preference::class, 
                \App\Models\Pharmacy::class, \App\Models\InvoiceItemReturn::class, \App\Models\InvoiceItem::class, 
                \App\Models\InvoiceHistory::class, \App\Models\Invoice::class, \App\Models\Farmula::class, 
                \App\Models\Company::class, \App\Models\Category::class, \App\Models\BaseStockSalePrice::class
            ];

            foreach($models as $modelClass) {
               if(class_exists($modelClass)) {
                   $table = (new $modelClass)->getTable();
                   // Fix legacy empty bounds smoothly
                   \Illuminate\Support\Facades\DB::table($table)
                       ->whereNull('business_id')->orWhere('business_id', 0)
                       ->update(['business_id' => $business->id]);
               }
            }
            $this->command->info("Legacy database converted successfully to tenancy for Business ID: {$business->id}");
        }

        // Bypass global scopes to ensure we get all tenant's products
        $products = \App\Models\Product::withoutGlobalScopes()->get();
        $count = 0;
        
        $this->command->info(str_repeat('-', 70));
        $this->command->info(str_pad("STARTING LEGACY PRODUCT MAPPING & SYNC", 70, " ", STR_PAD_BOTH));
        $this->command->info(str_repeat('-', 70));

        foreach ($products as $product) {
            $generic = \App\Services\GenericProductService::syncProductToGeneric($product);
            $draftState = $product->is_draft ? '<fg=yellow>DRAFT</>' : '<fg=green>ACTIVE</>';
            $prefState = $product->sale_price_preference_id ? "Pref: {$product->sale_price_preference_id}" : "Pref: None";
            
            if ($generic) {
                // If it successfully linked or created a generic masterlist entry
                $this->command->line("<fg=green>[\u{2714}] Mapped:</> <fg=cyan>{$product->product_name}</>");
                $this->command->line("    └─> <fg=gray>Local ID: {$product->id} | Masterlist ID: {$generic->id} | {$draftState} | {$prefState}</>");
                $count++;
            } else {
                // If the product was already linked cleanly
                $this->command->line("<bg=blue;fg=white> [\u{21BB}] Already Synced: </> <fg=cyan>{$product->product_name}</>");
                $this->command->line("    └─> <fg=gray>Local ID: {$product->id} | {$draftState} | {$prefState}</>");
                $count++;
            }
        }
        
        $this->command->info(str_repeat('-', 70));
        $this->command->info(str_pad("SUCCESSFULLY PROCESSED {$count} PRODUCTS", 70, " ", STR_PAD_BOTH));
        $this->command->info(str_repeat('-', 70));
    }
}
