<?php

namespace App\Services\ProductImport;

use App\Models\ProductData;
use InvalidArgumentException;
use Throwable;

final readonly class ProductImportService
{
    public function __construct(
        private ProductCsvReader       $reader,
        private ProductRowMapper       $mapper,
        private ProductImportValidator $validator,
        private ProductImportRules     $rules,
        private ProductImporter        $importer,
    ) {
    }

    public function import(string $filePath, bool $dryRun = false): ImportResult
    {
        $result = new ImportResult();

        $seenProductCodes = [];

        foreach ($this->reader->read($filePath) as $csvRow) {
            $result->incrementProcessed();

            $rowNumber = $csvRow['row_number'];
            $row = $csvRow['data'];

            try {
                $product = $this->mapper->map($row, $rowNumber);
            } catch (InvalidArgumentException $exception) {
                $result->addFailure(new ImportFailure(
                    rowNumber: $rowNumber,
                    productCode: $this->extractProductCode($row),
                    reason: $exception->getMessage(),
                ));

                continue;
            }

            $validationErrors = $this->validator->validate($product);

            if ($validationErrors !== []) {
                $result->addFailure(new ImportFailure(
                    rowNumber: $product->rowNumber,
                    productCode: $product->productCode,
                    reason: implode(' ', $validationErrors),
                ));

                continue;
            }

            if (isset($seenProductCodes[$product->productCode])) {
                $result->addFailure(new ImportFailure(
                    rowNumber: $product->rowNumber,
                    productCode: $product->productCode,
                    reason: 'Duplicate product code in CSV file.',
                ));

                continue;
            }

            $seenProductCodes[$product->productCode] = true;

            if (! $dryRun && ProductData::where('strProductCode', $product->productCode)->exists()) {
                $result->addFailure(new ImportFailure(
                    rowNumber: $product->rowNumber,
                    productCode: $product->productCode,
                    reason: 'Product code already exists in database.',
                ));

                continue;
            }

            $skipReason = $this->rules->skipReason($product);

            if ($skipReason !== null) {
                $result->addFailure(new ImportFailure(
                    rowNumber: $product->rowNumber,
                    productCode: $product->productCode,
                    reason: $skipReason,
                ));

                continue;
            }

            try {
                $this->importer->import($product, $dryRun);
                $result->incrementSuccessful();
            } catch (Throwable $exception) {
                $result->addFailure(new ImportFailure(
                    rowNumber: $product->rowNumber,
                    productCode: $product->productCode,
                    reason: 'Database insert failed: ' . $exception->getMessage(),
                ));
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractProductCode(array $row): ?string
    {
        $productCode = trim((string) ($row['Product Code'] ?? ''));

        return $productCode !== '' ? $productCode : null;
    }
}
