<?php

namespace Tests\Unit;

use App\Services\ProductImport\ImportFailure;
use App\Services\ProductImport\ImportResult;
use PHPUnit\Framework\TestCase;

class ImportResultTest extends TestCase
{
    public function test_initial_counts_are_all_zero(): void
    {
        $result = new ImportResult();

        $this->assertSame(0, $result->processed());
        $this->assertSame(0, $result->successful());
        $this->assertSame(0, $result->skipped());
    }

    public function test_initial_failures_array_is_empty(): void
    {
        $result = new ImportResult();

        $this->assertSame([], $result->failures());
    }

    public function test_increment_processed_increases_count_by_one(): void
    {
        $result = new ImportResult();

        $result->incrementProcessed();

        $this->assertSame(1, $result->processed());
    }

    public function test_increment_processed_multiple_times(): void
    {
        $result = new ImportResult();

        $result->incrementProcessed();
        $result->incrementProcessed();
        $result->incrementProcessed();

        $this->assertSame(3, $result->processed());
    }

    public function test_increment_successful_increases_count_by_one(): void
    {
        $result = new ImportResult();

        $result->incrementSuccessful();

        $this->assertSame(1, $result->successful());
    }

    public function test_increment_successful_multiple_times(): void
    {
        $result = new ImportResult();

        $result->incrementSuccessful();
        $result->incrementSuccessful();

        $this->assertSame(2, $result->successful());
    }

    public function test_add_failure_increases_skipped_count(): void
    {
        $result = new ImportResult();

        $result->addFailure(new ImportFailure(2, 'P001', 'Some reason'));

        $this->assertSame(1, $result->skipped());
    }

    public function test_skipped_count_equals_number_of_failures_added(): void
    {
        $result = new ImportResult();

        $result->addFailure(new ImportFailure(2, 'P001', 'Reason 1'));
        $result->addFailure(new ImportFailure(3, 'P002', 'Reason 2'));
        $result->addFailure(new ImportFailure(4, null, 'Reason 3'));

        $this->assertSame(3, $result->skipped());
    }

    public function test_failures_returns_all_added_failure_objects(): void
    {
        $result = new ImportResult();
        $failure1 = new ImportFailure(2, 'P001', 'Reason 1');
        $failure2 = new ImportFailure(3, 'P002', 'Reason 2');

        $result->addFailure($failure1);
        $result->addFailure($failure2);

        $failures = $result->failures();

        $this->assertCount(2, $failures);
        $this->assertSame($failure1, $failures[0]);
        $this->assertSame($failure2, $failures[1]);
    }

    public function test_failures_preserves_insertion_order(): void
    {
        $result = new ImportResult();

        for ($i = 1; $i <= 5; $i++) {
            $result->addFailure(new ImportFailure($i + 1, "P00{$i}", "Reason {$i}"));
        }

        $failures = $result->failures();

        $this->assertSame('P001', $failures[0]->productCode);
        $this->assertSame('P005', $failures[4]->productCode);
    }

    public function test_failure_with_null_product_code_is_accepted(): void
    {
        $result = new ImportResult();
        $result->addFailure(new ImportFailure(2, null, 'Mapping error'));

        $failures = $result->failures();

        $this->assertNull($failures[0]->productCode);
    }

    public function test_processed_and_successful_are_independent_counters(): void
    {
        $result = new ImportResult();

        $result->incrementProcessed();
        $result->incrementProcessed();
        $result->incrementProcessed();

        $result->incrementSuccessful();

        $result->addFailure(new ImportFailure(3, 'P002', 'Skipped'));
        $result->addFailure(new ImportFailure(4, 'P003', 'Also skipped'));

        $this->assertSame(3, $result->processed());
        $this->assertSame(1, $result->successful());
        $this->assertSame(2, $result->skipped());
    }
}
