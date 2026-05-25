<?php

namespace App\Services\ProductImport;

use Spatie\SimpleExcel\SimpleExcelReader;
use Generator;

final class ProductCsvReader
{
    /**
     * @return Generator<int, array{row_number: int, data: array<string, mixed>}>
     */
    public function read(string $filePath): Generator
    {
        $rowNumber = 1;

        foreach (SimpleExcelReader::create($filePath)->getRows() as $row) {
            $rowNumber++;

            yield [
                'row_number' => $rowNumber,
                'data' => $row,
            ];
        }
    }
}
