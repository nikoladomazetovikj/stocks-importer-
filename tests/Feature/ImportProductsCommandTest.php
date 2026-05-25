<?php

namespace Tests\Feature;

use App\Models\ProductData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportProductsCommandTest extends TestCase
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
        $path = tempnam(sys_get_temp_dir(), 'cmd_test_') . '.csv';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function singleValidProductCsv(): string
    {
        return "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P001,Test Widget,A reliable widget,15,10.00,\n";
    }

    // --- File validation ---

    public function test_exits_with_failure_when_file_does_not_exist(): void
    {
        $this->artisan('products:import', ['file' => '/path/to/nonexistent.csv'])
            ->assertExitCode(1)
            ->expectsOutputToContain('File does not exist');
    }

    // --- Normal mode output ---

    public function test_exits_with_success_when_import_completes(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path])
            ->assertExitCode(0);
    }

    public function test_outputs_import_completed_message(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Import completed.');
    }

    public function test_outputs_processed_count(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Processed: 1');
    }

    public function test_outputs_successful_count(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Successful: 1');
    }

    public function test_outputs_skipped_count(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Skipped: 0');
    }

    // --- Database insertion ---

    public function test_inserts_product_into_database(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path]);

        $this->assertDatabaseHas('tblProductData', ['strProductCode' => 'P001']);
    }

    // --- Dry-run (--test flag) ---

    public function test_dry_run_outputs_test_mode_warning(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path, '--test' => true])
            ->expectsOutputToContain('test mode');
    }

    public function test_dry_run_does_not_insert_into_database(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path, '--test' => true]);

        $this->assertDatabaseMissing('tblProductData', ['strProductCode' => 'P001']);
    }

    public function test_dry_run_still_reports_successful_count(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path, '--test' => true])
            ->expectsOutputToContain('Successful: 1');
    }

    public function test_dry_run_exits_with_success(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path, '--test' => true])
            ->assertExitCode(0);
    }

    // --- Failure reporting ---

    public function test_displays_skipped_failed_rows_section_when_failures_exist(): void
    {
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P001,Cheap Item,Too cheap and too few,5,4.99,\n";

        $path = $this->createTempCsv($csv);

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Skipped / failed rows');
    }

    public function test_displays_product_code_in_failure_line(): void
    {
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P001,Cheap Item,Too cheap and too few,5,4.99,\n";

        $path = $this->createTempCsv($csv);

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('P001');
    }

    public function test_displays_row_number_in_failure_line(): void
    {
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P001,Cheap Item,Too cheap and too few,5,4.99,\n";

        $path = $this->createTempCsv($csv);

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Row 2');
    }

    public function test_does_not_display_failures_section_when_no_failures(): void
    {
        $path = $this->createTempCsv($this->singleValidProductCsv());

        $this->artisan('products:import', ['file' => $path])
            ->doesntExpectOutputToContain('Skipped / failed rows');
    }

    // --- Mixed rows ---

    public function test_reports_correct_counts_for_mixed_rows(): void
    {
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P001,Good Product,Valid description,15,10.00,\n"
            . "P002,Cheap Low Stock,Skipped by rule,5,4.99,\n"
            . "P003,Another Good,Also valid,20,50.00,\n";

        $path = $this->createTempCsv($csv);

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Processed: 3')
            ->expectsOutputToContain('Successful: 2')
            ->expectsOutputToContain('Skipped: 1');
    }

    public function test_uses_na_for_product_code_when_mapping_fails_before_code_is_extracted(): void
    {
        // Row with no product code but invalid stock triggers mapping error before code assignment
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . ",Bad Stock Row,Description,not_a_number,10.00,\n";

        $path = $this->createTempCsv($csv);

        // Should display N/A for missing product code
        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('N/A');
    }

    // --- Discontinued in command context ---

    public function test_discontinued_product_is_imported_and_marked(): void
    {
        $csv = "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n"
            . "P001,Old Widget,Being retired,20,15.00,yes\n";

        $path = $this->createTempCsv($csv);

        $this->artisan('products:import', ['file' => $path])
            ->expectsOutputToContain('Successful: 1');

        $product = ProductData::where('strProductCode', 'P001')->first();
        $this->assertNotNull($product->dtmDiscontinued);
    }
}
