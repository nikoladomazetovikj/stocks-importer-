<?php

namespace Tests\Unit;

use App\Services\ProductImport\ProductCsvReader;
use PHPUnit\Framework\TestCase;

class ProductCsvReaderTest extends TestCase
{
    private ProductCsvReader $reader;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->reader = new ProductCsvReader();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function createTempCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_reader_test_') . '.csv';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function standardHeader(): string
    {
        return "Product Code,Product Name,Product Description,Stock,Cost in GBP,Discontinued\n";
    }

    public function test_returns_generator(): void
    {
        $path = $this->createTempCsv($this->standardHeader());

        $result = $this->reader->read($path);

        $this->assertInstanceOf(\Generator::class, $result);
    }

    public function test_empty_csv_yields_no_rows(): void
    {
        $path = $this->createTempCsv($this->standardHeader());

        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(0, $rows);
    }

    public function test_reads_single_data_row(): void
    {
        $csv = $this->standardHeader()
            . "P001,Test Product,A description,10,9.99,\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(1, $rows);
    }

    public function test_row_number_starts_at_2_for_first_data_row(): void
    {
        // Row 1 is the header; first data row is row 2
        $csv = $this->standardHeader()
            . "P001,Test Product,A description,10,9.99,\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $this->assertSame(2, $rows[0]['row_number']);
    }

    public function test_row_numbers_increment_for_each_data_row(): void
    {
        $csv = $this->standardHeader()
            . "P001,First,Description One,10,9.99,\n"
            . "P002,Second,Description Two,20,19.99,\n"
            . "P003,Third,Description Three,30,29.99,\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $this->assertSame(2, $rows[0]['row_number']);
        $this->assertSame(3, $rows[1]['row_number']);
        $this->assertSame(4, $rows[2]['row_number']);
    }

    public function test_data_is_keyed_by_header_column_names(): void
    {
        $csv = $this->standardHeader()
            . "P001,Test Product,A description,10,9.99,yes\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $data = $rows[0]['data'];

        $this->assertArrayHasKey('Product Code', $data);
        $this->assertArrayHasKey('Product Name', $data);
        $this->assertArrayHasKey('Product Description', $data);
        $this->assertArrayHasKey('Stock', $data);
        $this->assertArrayHasKey('Cost in GBP', $data);
        $this->assertArrayHasKey('Discontinued', $data);
    }

    public function test_data_values_are_correctly_read(): void
    {
        $csv = $this->standardHeader()
            . "P001,Test Product,A description,10,9.99,yes\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $data = $rows[0]['data'];

        $this->assertSame('P001', $data['Product Code']);
        $this->assertSame('Test Product', $data['Product Name']);
        $this->assertSame('A description', $data['Product Description']);
        $this->assertSame('10', $data['Stock']);
        $this->assertSame('9.99', $data['Cost in GBP']);
        $this->assertSame('yes', $data['Discontinued']);
    }

    public function test_reads_multiple_rows_correctly(): void
    {
        $csv = $this->standardHeader()
            . "P001,First,Desc One,10,9.99,\n"
            . "P002,Second,Desc Two,20,19.99,yes\n"
            . "P003,Third,Desc Three,5,4.99,\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $this->assertCount(3, $rows);
        $this->assertSame('P001', $rows[0]['data']['Product Code']);
        $this->assertSame('P002', $rows[1]['data']['Product Code']);
        $this->assertSame('P003', $rows[2]['data']['Product Code']);
    }

    public function test_each_row_has_row_number_and_data_keys(): void
    {
        $csv = $this->standardHeader()
            . "P001,Test,Desc,10,9.99,\n";

        $path = $this->createTempCsv($csv);
        $rows = iterator_to_array($this->reader->read($path));

        $this->assertArrayHasKey('row_number', $rows[0]);
        $this->assertArrayHasKey('data', $rows[0]);
    }
}
