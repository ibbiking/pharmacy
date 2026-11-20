<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform owner (super admin) can block a business's access to a whole module or to one sub-module (a permission
 * key such as "pos.returns.manage"). A block wins over everything: the business's owner can't switch it back on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_access_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('key', 120);                 // module key ("pos") or permission key ("pos.returns.manage")
            $t->string('reason', 255)->nullable();
            $t->foreignId('blocked_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_access_blocks');
    }
};
