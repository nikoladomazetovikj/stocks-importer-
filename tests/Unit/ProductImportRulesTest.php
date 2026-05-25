<?php

namespace Tests\Unit;

use App\Services\ProductImport\ProductImportData;
use App\Services\ProductImport\ProductImportRules;
use PHPUnit\Framework\TestCase;

class ProductImportRulesTest extends TestCase
{
    private ProductImportRules $rules;

    protected function setUp(): void
    {
        $this->rules = new ProductImportRules();
    }

    private function product(string $cost, int $stock, bool $discontinued = false): ProductImportData
    {
        return new ProductImportData(
            rowNumber: 2,
            productCode: 'P001',
            productName: 'Test Product',
            productDescription: 'A test description',
            stockLevel: $stock,
            costGbp: $cost,
            discontinued: $discontinued,
        );
    }

    // --- Low cost + low stock rule ---

    public function test_product_is_allowed_when_cost_above_5_and_stock_at_least_10(): void
    {
        $this->assertNull($this->rules->skipReason($this->product('6.00', 10)));
    }

    public function test_product_is_skipped_when_cost_below_5_and_stock_below_10(): void
    {
        $reason = $this->rules->skipReason($this->product('4.99', 9));

        $this->assertNotNull($reason);
        $this->assertStringContainsString('less than 5', $reason);
        $this->assertStringContainsString('stock below 10', $reason);
    }

    public function test_product_is_allowed_when_cost_below_5_but_stock_at_least_10(): void
    {
        // Only skipped when BOTH conditions are true
        $this->assertNull($this->rules->skipReason($this->product('4.99', 10)));
    }

    public function test_product_is_allowed_when_cost_at_least_5_but_stock_below_10(): void
    {
        // Only skipped when BOTH conditions are true
        $this->assertNull($this->rules->skipReason($this->product('5.00', 9)));
    }

    public function test_product_is_skipped_when_cost_is_zero_and_stock_is_zero(): void
    {
        $reason = $this->rules->skipReason($this->product('0.00', 0));

        $this->assertNotNull($reason);
    }

    public function test_product_is_skipped_when_cost_just_below_5_and_stock_is_0(): void
    {
        $reason = $this->rules->skipReason($this->product('4.99', 0));

        $this->assertNotNull($reason);
    }

    public function test_product_is_allowed_when_cost_exactly_5_and_stock_is_0(): void
    {
        // Cost = 5.00 does NOT satisfy cost < 5, so it passes
        $this->assertNull($this->rules->skipReason($this->product('5.00', 0)));
    }

    public function test_product_is_skipped_when_cost_is_1_and_stock_is_9(): void
    {
        $reason = $this->rules->skipReason($this->product('1.00', 9));

        $this->assertNotNull($reason);
    }

    // --- High cost rule ---

    public function test_product_is_skipped_when_cost_exceeds_1000(): void
    {
        $reason = $this->rules->skipReason($this->product('1000.01', 100));

        $this->assertNotNull($reason);
        $this->assertStringContainsString('more than 1000', $reason);
    }

    public function test_product_is_allowed_when_cost_exactly_1000(): void
    {
        // Cost = 1000.00 does NOT satisfy cost > 1000, so it passes
        $this->assertNull($this->rules->skipReason($this->product('1000.00', 100)));
    }

    public function test_product_is_allowed_when_cost_just_below_1000(): void
    {
        $this->assertNull($this->rules->skipReason($this->product('999.99', 100)));
    }

    public function test_product_is_skipped_when_cost_is_very_high(): void
    {
        $reason = $this->rules->skipReason($this->product('9999.00', 100));

        $this->assertNotNull($reason);
    }

    // --- Discontinued does not affect skip rules ---

    public function test_discontinued_product_is_still_subject_to_cost_rule(): void
    {
        $reason = $this->rules->skipReason($this->product('1500.00', 50, discontinued: true));

        $this->assertNotNull($reason);
        $this->assertStringContainsString('more than 1000', $reason);
    }

    public function test_discontinued_product_with_valid_cost_and_stock_is_allowed(): void
    {
        $this->assertNull($this->rules->skipReason($this->product('10.00', 20, discontinued: true)));
    }

    // --- Boundary conditions ---

    public function test_cost_just_above_1000_is_skipped(): void
    {
        $reason = $this->rules->skipReason($this->product('1000.01', 50));

        $this->assertNotNull($reason);
    }

    public function test_stock_exactly_10_with_low_cost_is_allowed(): void
    {
        // Stock = 10 does NOT satisfy stock < 10, so the rule doesn't apply
        $this->assertNull($this->rules->skipReason($this->product('4.00', 10)));
    }

    public function test_skip_reason_returns_null_for_normal_product(): void
    {
        $this->assertNull($this->rules->skipReason($this->product('25.00', 100)));
    }
}
