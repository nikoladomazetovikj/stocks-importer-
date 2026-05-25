<?php

namespace App\Services\ProductImport;

final readonly class ProductImportData
{
    public function __construct(
        public int $rowNumber,
        public string $productCode,
        public string $productName,
        public string $productDescription,
        public int $stockLevel,
        public string $costGbp,
        public bool $discontinued,
    ) {
    }
}
