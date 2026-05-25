<?php

namespace Tests\Feature;

use App\Models\ProductData;
use App\Services\ProductImport\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function createTempCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'svc_test_') . '.csv';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function csvWith(array $rows): string
    {
        $header = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n";
        $lines  = array_map(fn (array $r) => implode(',', $r), $rows);

        return $header . implode("\n", $lines) . "\n";
    }

    private function service(): ProductImportService
    {
        return $this->app->make(ProductImportService::class);
    }

    // --- Successful import ---

    public function test_imports_valid_product_and_returns_success_counts(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'A handy widget', '15', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(1, $result->processed());
        $this->assertSame(1, $result->successful());
        $this->assertSame(0, $result->skipped());
    }

    public function test_imported_product_is_persisted_to_database(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'A handy widget', '15', '10.00', ''],
        ]);

        $this->service()->import($this->createTempCsv($csv));

        $this->assertDatabaseHas('tblProductData', [
            'strProductCode' => 'P001',
            'strProductName' => 'Widget',
            'intStock'       => 15,
        ]);
    }

    public function test_imported_product_has_dtm_added_set(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'A handy widget', '15', '10.00', ''],
        ]);

        $this->service()->import($this->createTempCsv($csv));

        $product = ProductData::where('strProductCode', 'P001')->first();
        $this->assertNotNull($product->dtmAdded);
    }

    // --- Dry-run mode ---

    public function test_dry_run_counts_as_successful_without_persisting(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'A handy widget', '15', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv), dryRun: true);

        $this->assertSame(1, $result->successful());
        $this->assertDatabaseMissing('tblProductData', ['strProductCode' => 'P001']);
    }

    public function test_dry_run_skips_database_duplicate_check(): void
    {
        // Insert an existing record; in dry run, the duplicate check is skipped
        ProductData::create([
            'strProductCode' => 'P001',
            'strProductName' => 'Old Widget',
            'strProductDesc' => 'Already exists',
            'intStock'       => 5,
            'gbpCost'        => '9.99',
            'dtmAdded'       => now(),
        ]);

        $csv = $this->csvWith([
            ['P001', 'New Widget', 'Would be a duplicate', '15', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv), dryRun: true);

        $this->assertSame(1, $result->successful());
    }

    // --- Discontinued products ---

    public function test_discontinued_product_has_dtm_discontinued_set(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Old Widget', 'Being retired', '20', '15.00', 'yes'],
        ]);

        $this->service()->import($this->createTempCsv($csv));

        $product = ProductData::where('strProductCode', 'P001')->first();
        $this->assertNotNull($product->dtmDiscontinued);
    }

    public function test_active_product_has_null_dtm_discontinued(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Active Widget', 'Still selling', '20', '15.00', ''],
        ]);

        $this->service()->import($this->createTempCsv($csv));

        $product = ProductData::where('strProductCode', 'P001')->first();
        $this->assertNull($product->dtmDiscontinued);
    }

    // --- Business rules ---

    public function test_skips_product_when_cost_below_5_and_stock_below_10(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Cheap Item', 'Too cheap and too few', '5', '4.99', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->successful());
        $this->assertSame(1, $result->skipped());
        $this->assertDatabaseMissing('tblProductData', ['strProductCode' => 'P001']);
    }

    public function test_imports_product_when_cost_below_5_but_stock_at_least_10(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Cheap But Stocked', 'Enough stock', '10', '4.99', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(1, $result->successful());
    }

    public function test_skips_product_when_cost_exceeds_1000(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Luxury Item', 'Too expensive', '50', '1500.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->successful());
        $this->assertSame(1, $result->skipped());
    }

    public function test_imports_product_when_cost_is_exactly_1000(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Premium Item', 'At the limit', '50', '1000.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(1, $result->successful());
    }

    // --- Duplicate detection ---

    public function test_skips_second_occurrence_of_duplicate_product_code_in_csv(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget A', 'First entry', '15', '10.00', ''],
            ['P001', 'Widget B', 'Duplicate code', '20', '15.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(2, $result->processed());
        $this->assertSame(1, $result->successful());
        $this->assertSame(1, $result->skipped());
        $this->assertStringContainsString('Duplicate product code', $result->failures()[0]->reason);
    }

    public function test_skips_product_whose_code_already_exists_in_database(): void
    {
        ProductData::create([
            'strProductCode' => 'P001',
            'strProductName' => 'Existing Widget',
            'strProductDesc' => 'Already in DB',
            'intStock'       => 10,
            'gbpCost'        => '9.99',
            'dtmAdded'       => now(),
        ]);

        $csv = $this->csvWith([
            ['P001', 'New Widget', 'Should be rejected', '15', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->successful());
        $this->assertSame(1, $result->skipped());
        $this->assertStringContainsString('already exists in database', $result->failures()[0]->reason);
    }

    // --- Mapping and validation failures ---

    public function test_records_failure_for_row_with_invalid_stock_value(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'Description', 'NOT_A_NUMBER', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(1, $result->processed());
        $this->assertSame(0, $result->successful());
        $this->assertSame(1, $result->skipped());
        $this->assertStringContainsString('Stock must be a valid', $result->failures()[0]->reason);
    }

    public function test_records_failure_for_row_with_invalid_cost_value(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'Description', '10', 'NOTPRICE', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->successful());
        $this->assertStringContainsString('Cost must be a valid', $result->failures()[0]->reason);
    }

    public function test_records_failure_for_row_with_invalid_discontinued_value(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'Description', '10', '10.00', 'no'],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->successful());
        $this->assertStringContainsString('Discontinued must be empty or yes', $result->failures()[0]->reason);
    }

    public function test_records_failure_for_row_with_product_code_exceeding_max_length(): void
    {
        $csv = $this->csvWith([
            ['TOOLONGCODE1', 'Widget', 'Description', '10', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->successful());
        $this->assertStringContainsString('Product code may not be greater than 10 characters', $result->failures()[0]->reason);
    }

    public function test_failure_stores_row_number_and_product_code(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Widget', 'Description', 'BAD', '10.00', ''],
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $failure = $result->failures()[0];
        $this->assertSame(2, $failure->rowNumber);
        $this->assertSame('P001', $failure->productCode);
    }

    // --- Mixed scenarios ---

    public function test_processes_mixed_valid_invalid_and_skipped_rows(): void
    {
        $csv = $this->csvWith([
            ['P001', 'Good Product', 'Valid description', '15', '10.00', ''],   // success
            ['P002', 'Cheap Low Stock', 'Skipped by rule', '5', '4.99', ''],   // skip: rule
            ['P003', 'Another Good', 'Also valid', '20', '50.00', ''],          // success
            ['P004', 'Bad Stock Row', 'Invalid stock', 'abc', '10.00', ''],     // fail: mapping
        ]);

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(4, $result->processed());
        $this->assertSame(2, $result->successful());
        $this->assertSame(2, $result->skipped());
    }

    public function test_empty_csv_yields_zero_counts(): void
    {
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n";

        $result = $this->service()->import($this->createTempCsv($csv));

        $this->assertSame(0, $result->processed());
        $this->assertSame(0, $result->successful());
        $this->assertSame(0, $result->skipped());
    }
}
