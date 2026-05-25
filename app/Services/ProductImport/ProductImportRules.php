<?php

namespace App\Services\ProductImport;

final class ProductImportRules
{
    public function skipReason(ProductImportData $product): ?string
    {
        if ((float) $product->costGbp < 5 && $product->stockLevel < 10) {
            return 'Product costs less than 5 and has stock below 10.';
        }

        if ((float) $product->costGbp > 1000) {
            return 'Product costs more than 1000.';
        }

        return null;
    }
}
