<?php

namespace Tests\Unit;

use App\Services\ProductImport\ProductImportData;
use App\Services\ProductImport\ProductImportValidator;
use PHPUnit\Framework\TestCase;

class ProductImportValidatorTest extends TestCase
{
    private ProductImportValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProductImportValidator();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function validProduct(array $overrides = []): ProductImportData
    {
        return new ProductImportData(
            rowNumber: $overrides['rowNumber'] ?? 2,
            productCode: $overrides['productCode'] ?? 'P001',
            productName: $overrides['productName'] ?? 'Test Product',
            productDescription: $overrides['productDescription'] ?? 'A valid description',
            stockLevel: $overrides['stockLevel'] ?? 10,
            costGbp: $overrides['costGbp'] ?? '9.99',
            discontinued: $overrides['discontinued'] ?? false,
        );
    }

    public function test_valid_product_returns_no_errors(): void
    {
        $errors = $this->validator->validate($this->validProduct());

        $this->assertSame([], $errors);
    }

    public function test_empty_product_code_returns_required_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productCode' => '']));

        $this->assertContains('Product code is required.', $errors);
    }

    public function test_product_code_over_10_chars_returns_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productCode' => 'TOOLONGCODE1']));

        $this->assertContains('Product code may not be greater than 10 characters.', $errors);
    }

    public function test_product_code_exactly_10_chars_is_valid(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productCode' => 'EXACTLY10C']));

        $this->assertNotContains('Product code may not be greater than 10 characters.', $errors);
    }

    public function test_product_code_11_chars_fails(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productCode' => '12345678901']));

        $this->assertContains('Product code may not be greater than 10 characters.', $errors);
    }

    public function test_empty_product_name_returns_required_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productName' => '']));

        $this->assertContains('Product name is required.', $errors);
    }

    public function test_product_name_over_50_chars_returns_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productName' => str_repeat('A', 51)]));

        $this->assertContains('Product name may not be greater than 50 characters.', $errors);
    }

    public function test_product_name_exactly_50_chars_is_valid(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productName' => str_repeat('A', 50)]));

        $this->assertNotContains('Product name may not be greater than 50 characters.', $errors);
    }

    public function test_empty_product_description_returns_required_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productDescription' => '']));

        $this->assertContains('Product description is required.', $errors);
    }

    public function test_product_description_over_255_chars_returns_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productDescription' => str_repeat('A', 256)]));

        $this->assertContains('Product description may not be greater than 255 characters.', $errors);
    }

    public function test_product_description_exactly_255_chars_is_valid(): void
    {
        $errors = $this->validator->validate($this->validProduct(['productDescription' => str_repeat('A', 255)]));

        $this->assertNotContains('Product description may not be greater than 255 characters.', $errors);
    }

    public function test_negative_stock_level_returns_error(): void
    {
        $errors = $this->validator->validate($this->validProduct(['stockLevel' => -1]));

        $this->assertContains('Stock level cannot be negative.', $errors);
    }

    public function test_zero_stock_level_is_valid(): void
    {
        $errors = $this->validator->validate($this->validProduct(['stockLevel' => 0]));

        $this->assertNotContains('Stock level cannot be negative.', $errors);
    }

    public function test_negative_cost_returns_error(): void
    {
        // The validator checks (float) costGbp < 0
        $errors = $this->validator->validate($this->validProduct(['costGbp' => '-1.00']));

        $this->assertContains('Cost cannot be negative.', $errors);
    }

    public function test_zero_cost_is_valid(): void
    {
        $errors = $this->validator->validate($this->validProduct(['costGbp' => '0.00']));

        $this->assertNotContains('Cost cannot be negative.', $errors);
    }

    public function test_multiple_errors_are_all_returned(): void
    {
        $product = new ProductImportData(
            rowNumber: 2,
            productCode: '',
            productName: '',
            productDescription: '',
            stockLevel: 10,
            costGbp: '9.99',
            discontinued: false,
        );

        $errors = $this->validator->validate($product);

        $this->assertContains('Product code is required.', $errors);
        $this->assertContains('Product name is required.', $errors);
        $this->assertContains('Product description is required.', $errors);
        $this->assertCount(3, $errors);
    }

    public function test_all_fields_invalid_returns_multiple_errors(): void
    {
        $product = new ProductImportData(
            rowNumber: 2,
            productCode: '',
            productName: str_repeat('X', 51),
            productDescription: '',
            stockLevel: 10,
            costGbp: '9.99',
            discontinued: false,
        );

        $errors = $this->validator->validate($product);

        // Empty code + name too long + empty description
        $this->assertGreaterThanOrEqual(3, count($errors));
    }

    public function test_multibyte_product_code_length_is_checked_correctly(): void
    {
        // 11 multibyte characters should exceed the 10-char limit
        $errors = $this->validator->validate($this->validProduct(['productCode' => 'AAAAAAAAAAA']));

        $this->assertContains('Product code may not be greater than 10 characters.', $errors);
    }
}
