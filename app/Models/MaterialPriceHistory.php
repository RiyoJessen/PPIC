<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialPriceHistory extends Model
{
    public $timestamps = false;

    protected $table = 'material_price_histories';

    protected $fillable = [
        'material_id',
        'price_month',
        'recorded_date',
        'market_price',
        'source',
        'notes',
        'created_at',
    ];
}
