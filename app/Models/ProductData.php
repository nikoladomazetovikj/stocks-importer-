<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 * @property int                             $intProductDataId  Primary key.
 * @property string                          $strProductName    Product display name (max 50 chars).
 * @property string                          $strProductDesc    Product description (max 255 chars).
 * @property string                          $strProductCode    Unique supplier SKU (max 10 chars).
 * @property int                             $intStock          Current stock quantity.
 * @property string                          $gbpCost           Unit cost in GBP, stored as decimal(10,2).
 * @property \Illuminate\Support\Carbon|null $dtmAdded          Timestamp of when the record was first imported.
 * @property \Illuminate\Support\Carbon|null $dtmDiscontinued   Set to the import date when the product is discontinued; null otherwise.
 * @property \Illuminate\Support\Carbon      $stmTimestamp      Auto-updated server timestamp (managed by MySQL).
 */
class ProductData extends Model
{
    /**
     * The underlying database table.
     *
     * Overrides the default Eloquent convention (which would resolve to
     * "product_data") to match the pre-existing schema table name.
     *
     * @var string
     */
    protected $table = 'tblProductData';

    /**
     * The primary key column.
     *
     * Overrides the Eloquent default of "id" to match the legacy column name.
     *
     * @var string
     */
    protected $primaryKey = 'intProductDataId';

    /**
     * Disable Eloquent's automatic created_at / updated_at management.
     *
     * The table uses its own stmTimestamp column (handled by MySQL) instead
     * of Laravel's timestamp pair, so Eloquent must not attempt to write them.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Columns that may be mass-assigned via Model::create() or Model::fill().
     *
     * stmTimestamp is intentionally excluded — MySQL sets it automatically via
     * DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.
     *
     * @var list<string>
     */
    protected $fillable = [
        'strProductName',
        'strProductDesc',
        'strProductCode',
        'intStock',
        'gbpCost',
        'dtmAdded',
        'dtmDiscontinued',
    ];

    /**
     * Attribute type casts applied when reading values from the database.
     *
     * - intStock    → cast to PHP int so arithmetic comparisons work correctly.
     * - gbpCost     → cast to decimal string with 2 decimal places to avoid
     *                 floating-point rounding when displaying or comparing prices.
     * - date fields → cast to Carbon instances for convenient date handling.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'intStock'         => 'integer',
        'gbpCost'          => 'decimal:2',
        'dtmAdded'         => 'datetime',
        'dtmDiscontinued'  => 'datetime',
        'stmTimestamp'     => 'datetime',
    ];
}
