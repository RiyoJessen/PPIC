<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_histories')) {
            Schema::create('sales_histories', function (Blueprint $table) {
                $table->id();
                $table->string('doc_number', 80)->unique();
                $table->string('doc_type', 30)->default('history');
                $table->string('customer_name', 180);
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('sale_month', 7);
                $table->date('sale_date');
                $table->decimal('quantity', 12, 2)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('total_amount', 16, 2)->default(0);
                $table->string('status', 30)->default('fulfilled');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['product_id', 'sale_month'], 'idx_sales_prod_month');
            });
        }

        if (!Schema::hasTable('sales_forecasts')) {
            Schema::create('sales_forecasts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->integer('forecast_year');
                $table->string('method', 30)->default('manual');
                for ($m = 1; $m <= 12; $m++) {
                    $table->decimal('m' . $m, 12, 2)->default(0);
                }
                $table->decimal('total_12m_qty', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['product_id', 'forecast_year'], 'idx_fc_prod_year');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_forecasts');
        Schema::dropIfExists('sales_histories');
    }
};
