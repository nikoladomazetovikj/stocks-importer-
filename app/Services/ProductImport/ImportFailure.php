<?php

namespace App\Services\ProductImport;

final readonly class ImportFailure
{
    public function __construct(
        public int $rowNumber,
        public ?string $productCode,
        public string $reason,
    ) {
    }
}
