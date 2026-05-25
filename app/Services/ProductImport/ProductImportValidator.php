<?php

namespace App\Services\ProductImport;

final class ProductImportValidator
{
    /**
     * @return string[]
     */
    public function validate(ProductImportData $product): array
    {
        $errors = [];

        if ($product->productCode === '') {
            $errors[] = 'Product code is required.';
        }

        if (mb_strlen($product->productCode) > 10) {
            $errors[] = 'Product code may not be greater than 10 characters.';
        }

        if ($product->productName === '') {
            $errors[] = 'Product name is required.';
        }

        if (mb_strlen($product->productName) > 50) {
            $errors[] = 'Product name may not be greater than 50 characters.';
        }

        if ($product->productDescription === '') {
            $errors[] = 'Product description is required.';
        }

        if (mb_strlen($product->productDescription) > 255) {
            $errors[] = 'Product description may not be greater than 255 characters.';
        }

        if ($product->stockLevel < 0) {
            $errors[] = 'Stock level cannot be negative.';
        }

        if ((float) $product->costGbp < 0) {
            $errors[] = 'Cost cannot be negative.';
        }

        return $errors;
    }
}
