<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GenericProduct;
use App\Models\Product;
use App\Services\GenericProductService;

class GenericProductDataSyncSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info("Starting Generic Product Parameter Synchronization...");

        // Fetch generic products that do NOT have any parameters map
        // We'll just look for any generic product missing its parameters
        $genericProducts = GenericProduct::doesntHave('parameters')->get();
        $this->command->info("Found {$genericProducts->count()} generic products missing parameters.");

        $syncedCount = 0;

        foreach ($genericProducts as $gp) {
            // Find a local product that maps to this generic product and HAS parameters
            $localProduct = Product::where('generic_product_id', $gp->id)
                ->has('parameters')
                ->latest()
                ->first();

            if ($localProduct) {
                GenericProductService::syncParametersToGeneric($localProduct, $gp->id);
                $syncedCount++;
                $this->command->info(" - Synced parameters for Generic Product ID {$gp->id} from Product ID {$localProduct->id}");
            } else {
                // If we didn't find any mapped product, we check if there's any product with the same name that has parameters
                // but hasn't been mapped to anything else
                $unmappedProduct = Product::where('product_name', $gp->product_name)
                    ->where(function ($query) use ($gp) {
                        $query->whereNull('generic_product_id')
                              ->orWhere('generic_product_id', $gp->id);
                    })
                    ->has('parameters')
                    ->latest()
                    ->first();

                if ($unmappedProduct) {
                    $unmappedProduct->generic_product_id = $gp->id;
                    $unmappedProduct->saveQuietly();
                    
                    GenericProductService::syncParametersToGeneric($unmappedProduct, $gp->id);
                    $syncedCount++;
                    $this->command->info(" - Synced parameters for Generic Product ID {$gp->id} from matching Product ID {$unmappedProduct->id}");
                } else {
                     $this->command->warn(" - Skipped Generic Product ID {$gp->id}: No local product with parameters found.");
                }
            }
        }

        $this->command->info("Synchronization Complete. Total Generic Products healed: {$syncedCount}.");
    }
}
