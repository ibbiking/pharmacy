<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Books sync (phase 6, docs/BUSINESS_TYPES_AND_SERVICES.md): converting a business's past data into the books in the
 * background while people keep working.
 *  - books_syncs: one row per conversion run (queued → running → done / failed) with what it posted. A run log
 *    (append-only history), so no soft deletes.
 *  - pending_postings: postings of new transactions that arrived while a run was busy. Short-lived queue rows (deleted
 *    once posted, in order), so no soft deletes / timestamps beyond created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books_syncs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('status', 10)->default('queued'); // queued | running | done | failed
            $t->string('reason', 30)->default('switched_on'); // switched_on | manual | sweep
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->json('counts')->nullable();
            $t->unsignedInteger('pending_posted')->default(0);
            $t->string('error', 1000)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'status']);
        });

        Schema::create('pending_postings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);                 // sale | return | purchase | adjustment | document | payment | expense
            $t->unsignedBigInteger('ref_id');
            $t->string('extra', 100)->nullable();   // return number / purchase source
            $t->timestamp('created_at')->nullable();
            $t->index(['business_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_postings');
        Schema::dropIfExists('books_syncs');
    }
};
