<?php

namespace App\Services\ProductImport;

use InvalidArgumentException;

final class ProductRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row, int $rowNumber): ProductImportData
    {
        $productCode = $this->stringValue($row['Product Code'] ?? null);
        $productName = $this->stringValue($row['Product Name'] ?? null);
        $description = $this->stringValue($row['Product Description'] ?? null);

        $stock = $this->normaliseStock($row['Stock'] ?? null);
        $cost = $this->normaliseCost($row['Cost in GBP'] ?? null);

        $discontinued = $this->normaliseDiscontinued($row['Discontinued'] ?? null);

        return new ProductImportData(
            rowNumber: $rowNumber,
            productCode: $productCode,
            productName: $productName,
            productDescription: $description,
            stockLevel: $stock,
            costGbp: $cost,
            discontinued: $discontinued,
        );
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normaliseStock(mixed $value): int
    {
        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            throw new InvalidArgumentException('Stock must be a valid non-negative integer.');
        }

        return (int) $value;
    }

    private function normaliseCost(mixed $value): string
    {
        $value = trim((string) $value);

        $value = str_replace(['£', '$', ',', ' '], '', $value);

        if ($value === '' || ! is_numeric($value)) {
            throw new InvalidArgumentException('Cost must be a valid numeric value.');
        }

        if ((float) $value < 0) {
            throw new InvalidArgumentException('Cost cannot be negative value.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normaliseDiscontinued(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return false;
        }

        if ($value === 'yes') {
            return true;
        }

        throw new InvalidArgumentException('Discontinued must be empty or yes.');
    }
}
