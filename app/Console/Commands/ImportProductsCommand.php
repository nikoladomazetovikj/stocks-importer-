<?php

namespace App\Console\Commands;

use App\Services\ProductImport\ProductImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('products:import {file : Path to the supplier CSV file} {--test : Run the import without inserting data into the database}')]
#[Description('Import supplier products from a CSV file')]
class ImportProductsCommand extends Command
{

    public function handle(ProductImportService $importService): int
    {
        $filePath = (string) $this->argument('file');
        $dryRun = (bool) $this->option('test');

        if (! file_exists($filePath)) {
            $this->error("File does not exist: {$filePath}");

            return self::FAILURE;
        }

        if (! is_readable($filePath)) {
            $this->error("File is not readable: {$filePath}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Running in test mode. No data will be inserted.');
            $this->newLine();
        }

        $result = $importService->import($filePath, $dryRun);

        $this->info('Import completed.');
        $this->newLine();

        $this->line("Processed: {$result->processed()}");
        $this->line("Successful: {$result->successful()}");
        $this->line("Skipped: {$result->skipped()}");

        if ($result->failures() !== []) {
            $this->newLine();
            $this->warn('Skipped / failed rows:');

            foreach ($result->failures() as $failure) {
                $productCode = $failure->productCode ?? 'N/A';

                $this->line("- Row {$failure->rowNumber} [{$productCode}]: {$failure->reason}");
            }
        }

        return self::SUCCESS;
    }
}
