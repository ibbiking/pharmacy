<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosCartReservationsTable extends Migration
{
    public function up()
    {
        Schema::create('pos_cart_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Selling unit picked at POS (Box/Strip/Tablet) — display metadata only,
            // the netting math below keys on base_stock_sale_price_id/price_group.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreignId('base_stock_sale_price_id')->nullable()->constrained('base_stock_sale_price')->cascadeOnDelete();
            $table->string('price_group', 20)->nullable();
            $table->double('base_qty');
            $table->double('quantity')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'product_id']);
            $table->index(['user_id']);
            $table->index(['updated_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pos_cart_reservations');
    }
}
