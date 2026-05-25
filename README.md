# Stocks Importer

A Laravel CLI application that imports product stock data from a supplier CSV file into a MySQL database, applying configurable business rules and reporting import results.

---

## Requirements

### Local (without Docker)

| Requirement    | Version  |
|----------------|----------|
| PHP            | >= 8.3   |
| Composer       | >= 2.x   |
| MySQL          | >= 8.0   |

### Docker

| Requirement     | Version |
|-----------------|---------|
| Docker          | >= 24.x |
| Docker Compose  | >= 2.x  |

---

## Local Setup (without Docker)

### 1. Install PHP dependencies

```bash
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database credentials:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stocks_importer
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Create the database

Log in to MySQL and create the database:

```sql
CREATE DATABASE stocks_importer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations

```bash
php artisan migrate
```

This creates the `tblProductData` table with the following schema:

| Column             | Type          | Notes                       |
|--------------------|---------------|-----------------------------|
| `intProductDataId` | int (PK)      | Auto-increment              |
| `strProductName`   | varchar(50)   |                             |
| `strProductDesc`   | varchar(255)  |                             |
| `strProductCode`   | varchar(10)   | Unique                      |
| `dtmAdded`         | datetime      | Nullable                    |
| `dtmDiscontinued`  | datetime      | Nullable                    |
| `stmTimestamp`     | timestamp     | Auto-set on insert/update   |
| `intStock`         | int unsigned  | Added by migration 2        |
| `gbpCost`          | decimal(10,2) | Added by migration 2        |

### 5. Run the import

```bash
php artisan products:import /path/to/stock.csv
```

Run in **test/dry-run mode** (processes the file but makes no database writes):

```bash
php artisan products:import /path/to/stock.csv --test
```

---

## Docker Setup (Laravel Sail)

### 1. Install PHP dependencies

If you have PHP installed locally:

```bash
composer install
```

If you do not have PHP locally:

```bash
docker run --rm -v "$(pwd)":/app composer install --ignore-platform-reqs
```

### 2. Configure environment

```bash
cp .env.example .env
```

The default Sail values in `.env.example` already match the Docker Compose service names, so no edits are required unless you want to change ports or credentials.

### 3. Start containers

```bash
./vendor/bin/sail up -d
```

This starts:

| Container      | Description                    | Default port |
|----------------|--------------------------------|--------------|
| `laravel.test` | PHP 8.5 application            | 80           |
| `mysql`        | MySQL 8.4 database             | 3306         |

### 4. Generate application key

```bash
./vendor/bin/sail artisan key:generate
```

### 5. Run migrations

```bash
./vendor/bin/sail artisan migrate
```

### 6. Copy the CSV into the container-accessible path

The application directory is mounted at `/var/www/html` inside the container. The easiest location to use is `storage/`:

```bash
cp /your/local/stock.csv storage/stock.csv
```

### 7. Run the import

```bash
./vendor/bin/sail artisan products:import /var/www/html/storage/stock.csv
```

Dry-run mode:

```bash
./vendor/bin/sail artisan products:import /var/www/html/storage/stock.csv --test
```

### 8. Stop containers

```bash
./vendor/bin/sail down
```

---

## CSV Format

The importer expects a CSV file with the following header row (column order is flexible):

| Header                | Description                              |
|-----------------------|------------------------------------------|
| `Product Code`        | Unique code, max 10 characters           |
| `Product Name`        | Product name, max 50 characters          |
| `Product Description` | Description, max 255 characters          |
| `Stock`               | Non-negative integer                     |
| `Cost in GBP`         | Numeric; `£`, `$`, `,`, spaces stripped  |
| `Discontinued`        | Empty or `yes` (case-insensitive)        |

---

## Business Rules

| Rule                                              | Behaviour                                           |
|---------------------------------------------------|-----------------------------------------------------|
| Cost < £5 **and** stock < 10                      | Row is **skipped** and reported as failed           |
| Cost > £1000                                      | Row is **skipped** and reported as failed           |
| `Discontinued` = `yes`                            | Imported with `dtmDiscontinued` set to current date |
| Duplicate `Product Code` within the CSV           | Second occurrence is **skipped**                    |
| `Product Code` already exists in the database     | Row is **skipped**                                  |

---

## Import Output

```
Import completed.

Processed: 10
Successful: 7
Skipped: 3

Skipped / failed rows:
- Row 3 [P002]: Product costs less than 5 and has stock below 10.
- Row 6 [P005]: Product costs more than 1000.
- Row 9 [P008]: Duplicate product code in CSV file.
```

---

## Running Tests

Tests use an **in-memory SQLite** database — no separate test database setup is required.

### All tests

```bash
php artisan test
```

or directly via PHPUnit:

```bash
./vendor/bin/phpunit
```

### Unit tests only

```bash
php artisan test --testsuite=Unit
```

### Feature tests only

```bash
php artisan test --testsuite=Feature
```

### With Docker

```bash
./vendor/bin/sail artisan test
```

### Test coverage

| Test file                              | What is covered                                                                              |
|----------------------------------------|----------------------------------------------------------------------------------------------|
| `Unit/ProductRowMapperTest`            | CSV normalisation: currency stripping, stock parsing, discontinued flag, whitespace trimming |
| `Unit/ProductImportValidatorTest`      | Field validation: required fields, max-length checks, boundary values                       |
| `Unit/ProductImportRulesTest`          | Business rules: low-cost/low-stock skip, over-£1000 skip, boundary values                   |
| `Unit/ImportResultTest`               | Result tracking: processed/successful/skipped counters, failure collection                    |
| `Unit/ProductCsvReaderTest`            | CSV reading: row numbering starting at 2, header-keyed data, generator behaviour             |
| `Feature/ProductImportServiceTest`     | Full pipeline: DB insertion, dry-run, discontinued dates, duplicate detection, all failures  |
| `Feature/ImportProductsCommandTest`    | Artisan command: output messages, exit codes, `--test` flag, failure display                 |

---

## Data Quality Considerations

The importer handles several common real-world data issues:

| Issue                                     | How it is handled                                                         |
|-------------------------------------------|---------------------------------------------------------------------------|
| Currency symbols (`£`, `$`) in cost       | Stripped automatically before numeric parsing                             |
| Comma-formatted numbers (`1,000.00`)      | Commas stripped before parsing                                            |
| Extra whitespace in any field             | All string fields are trimmed                                             |
| Mixed-case discontinued flag (`YES`, `Yes`) | Normalised to lowercase before comparison                               |
| Non-numeric stock value                   | Caught as a mapping error; row recorded as failed with reason             |
| Negative cost or non-numeric cost         | Rejected during mapping; row recorded as failed                           |
| Unexpected `Discontinued` value (e.g. `no`) | Rejected during mapping; row recorded as failed                        |
| Duplicate product codes within the CSV   | Second occurrence detected and skipped                                    |
| Product code already in database         | Detected via DB lookup and skipped                                        |

**Potential improvements with more time:**

- **Encoding detection**: Files with non-UTF-8 encoding (Windows-1252, ISO-8859-1) could be detected via `mb_detect_encoding()` and converted with `iconv()` before parsing.
- **Malformed CSV**: `SimpleExcelReader` (via `League\Csv`) handles quoted fields containing embedded commas and newlines; truncated files would produce empty column values caught by the validator.
- **Large files**: The `ProductCsvReader` uses a PHP `Generator`, so memory usage stays bounded regardless of file size.
- **Line endings**: `League\Csv` handles `\r\n`, `\n`, and `\r` transparently.

---


