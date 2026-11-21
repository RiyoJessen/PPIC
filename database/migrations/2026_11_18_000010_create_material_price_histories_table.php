<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->string('price_month', 7);
            $table->date('recorded_date');
            $table->decimal('market_price', 14, 2)->default(0);
            $table->string('source', 100)->default('Indeks Pasar Kimia');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['material_id', 'price_month'], 'idx_mat_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_price_histories');
    }
};
