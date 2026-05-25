<?php

namespace App\Services\ProductImport;

use App\Models\ProductData;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Throwable;

final class ProductImporter
{
    /**
     * @throws Throwable
     */
    public function import(ProductImportData $product, bool $dryRun = false): void
    {
        if ($dryRun) {
            return;
        }

        ProductData::create([
            'strProductCode' => $product->productCode,
            'strProductName' => $product->productName,
            'strProductDesc' => $product->productDescription,
            'intStock' => $product->stockLevel,
            'gbpCost' => $product->costGbp,
            'dtmAdded' => Carbon::now(),
            'dtmDiscontinued' => $product->discontinued ? Carbon::now() : null,
        ]);
    }
}
