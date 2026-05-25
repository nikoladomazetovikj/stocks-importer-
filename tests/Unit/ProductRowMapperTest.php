<?php

namespace Tests\Unit;

use App\Services\ProductImport\ProductImportData;
use App\Services\ProductImport\ProductRowMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductRowMapperTest extends TestCase
{
    private ProductRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductRowMapper();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRow(array $overrides = []): array
    {
        return array_merge([
            'Product Code'        => 'P001',
            'Product Name'        => 'Test Product',
            'Product Description' => 'A test product description',
            'Stock'               => '10',
            'Cost in GBP'         => '9.99',
            'Discontinued'        => '',
        ], $overrides);
    }

    public function test_maps_valid_row_to_product_import_data(): void
    {
        $product = $this->mapper->map($this->validRow(), 2);

        $this->assertInstanceOf(ProductImportData::class, $product);
        $this->assertSame(2, $product->rowNumber);
        $this->assertSame('P001', $product->productCode);
        $this->assertSame('Test Product', $product->productName);
        $this->assertSame('A test product description', $product->productDescription);
        $this->assertSame(10, $product->stockLevel);
        $this->assertSame('9.99', $product->costGbp);
        $this->assertFalse($product->discontinued);
    }

    public function test_trims_whitespace_from_string_fields(): void
    {
        $product = $this->mapper->map($this->validRow([
            'Product Code'        => '  P001  ',
            'Product Name'        => '  Test Product  ',
            'Product Description' => '  Description  ',
        ]), 2);

        $this->assertSame('P001', $product->productCode);
        $this->assertSame('Test Product', $product->productName);
        $this->assertSame('Description', $product->productDescription);
    }

    public function test_strips_pound_symbol_from_cost(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '£9.99']), 2);

        $this->assertSame('9.99', $product->costGbp);
    }

    public function test_strips_dollar_symbol_from_cost(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '$9.99']), 2);

        $this->assertSame('9.99', $product->costGbp);
    }

    public function test_strips_commas_from_cost(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '1,000.00']), 2);

        $this->assertSame('1000.00', $product->costGbp);
    }

    public function test_strips_spaces_from_cost(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '9 .99']), 2);

        $this->assertSame('9.99', $product->costGbp);
    }

    public function test_formats_integer_cost_to_two_decimal_places(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '5']), 2);

        $this->assertSame('5.00', $product->costGbp);
    }

    public function test_formats_cost_with_trailing_zeros(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '10.1']), 2);

        $this->assertSame('10.10', $product->costGbp);
    }

    public function test_zero_cost_is_valid(): void
    {
        $product = $this->mapper->map($this->validRow(['Cost in GBP' => '0']), 2);

        $this->assertSame('0.00', $product->costGbp);
    }

    public function test_zero_stock_is_valid(): void
    {
        $product = $this->mapper->map($this->validRow(['Stock' => '0']), 2);

        $this->assertSame(0, $product->stockLevel);
    }

    public function test_discontinued_empty_string_maps_to_false(): void
    {
        $product = $this->mapper->map($this->validRow(['Discontinued' => '']), 2);

        $this->assertFalse($product->discontinued);
    }

    public function test_discontinued_yes_lowercase_maps_to_true(): void
    {
        $product = $this->mapper->map($this->validRow(['Discontinued' => 'yes']), 2);

        $this->assertTrue($product->discontinued);
    }

    public function test_discontinued_yes_uppercase_maps_to_true(): void
    {
        $product = $this->mapper->map($this->validRow(['Discontinued' => 'YES']), 2);

        $this->assertTrue($product->discontinued);
    }

    public function test_discontinued_yes_mixed_case_maps_to_true(): void
    {
        $product = $this->mapper->map($this->validRow(['Discontinued' => 'Yes']), 2);

        $this->assertTrue($product->discontinued);
    }

    public function test_discontinued_whitespace_only_maps_to_false(): void
    {
        $product = $this->mapper->map($this->validRow(['Discontinued' => '   ']), 2);

        $this->assertFalse($product->discontinued);
    }

    public function test_throws_for_non_numeric_stock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock must be a valid non-negative integer.');

        $this->mapper->map($this->validRow(['Stock' => 'abc']), 2);
    }

    public function test_throws_for_empty_stock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock must be a valid non-negative integer.');

        $this->mapper->map($this->validRow(['Stock' => '']), 2);
    }

    public function test_throws_for_decimal_stock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock must be a valid non-negative integer.');

        $this->mapper->map($this->validRow(['Stock' => '10.5']), 2);
    }

    public function test_throws_for_negative_stock_string(): void
    {
        // Negative integers contain '-' so ctype_digit returns false
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock must be a valid non-negative integer.');

        $this->mapper->map($this->validRow(['Stock' => '-5']), 2);
    }

    public function test_throws_for_empty_cost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost must be a valid numeric value.');

        $this->mapper->map($this->validRow(['Cost in GBP' => '']), 2);
    }

    public function test_throws_for_non_numeric_cost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost must be a valid numeric value.');

        $this->mapper->map($this->validRow(['Cost in GBP' => 'abc']), 2);
    }

    public function test_throws_for_negative_cost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost cannot be negative value.');

        $this->mapper->map($this->validRow(['Cost in GBP' => '-1.00']), 2);
    }

    public function test_throws_for_invalid_discontinued_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discontinued must be empty or yes.');

        $this->mapper->map($this->validRow(['Discontinued' => 'no']), 2);
    }

    public function test_throws_for_discontinued_numeric_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discontinued must be empty or yes.');

        $this->mapper->map($this->validRow(['Discontinued' => '1']), 2);
    }

    public function test_row_number_is_stored_correctly(): void
    {
        $product = $this->mapper->map($this->validRow(), 42);

        $this->assertSame(42, $product->rowNumber);
    }

    public function test_missing_columns_fall_back_to_empty_values_for_strings(): void
    {
        // When column is missing, PHP null is cast to empty string for string fields;
        // stock will throw since empty string is not a valid integer.
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->map([], 2);
    }
}
