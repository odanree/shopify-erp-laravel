<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->integer('shopify_qty');
            $table->integer('erp_qty');
            $table->boolean('corrected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_corrections');
    }
};
