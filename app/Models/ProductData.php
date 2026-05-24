<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductData extends Model
{
    protected $table = 'tblProductData';

    protected $primaryKey = 'intProductDataId';

    public $timestamps = false;

    protected $fillable = [
        'strProductName',
        'strProductDesc',
        'strProductCode',
        'intStock',
        'gbpCost',
        'dtmAdded',
        'dtmDiscontinued',
    ];

    protected $casts = [
        'intStock' => 'integer',
        'gbpCost' => 'decimal:2',
        'dtmAdded' => 'datetime',
        'dtmDiscontinued' => 'datetime',
        'stmTimestamp' => 'datetime',
    ];
}
