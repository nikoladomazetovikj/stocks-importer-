<?php

namespace App\Services\ProductImport;

final class ImportResult
{
    private int $processed = 0;

    private int $successful = 0;

    /**
     * @var ImportFailure[]
     */
    private array $failures = [];

    public function incrementProcessed(): void
    {
        $this->processed++;
    }

    public function incrementSuccessful(): void
    {
        $this->successful++;
    }

    public function addFailure(ImportFailure $failure): void
    {
        $this->failures[] = $failure;
    }

    public function processed(): int
    {
        return $this->processed;
    }

    public function successful(): int
    {
        return $this->successful;
    }

    public function skipped(): int
    {
        return count($this->failures);
    }

    /**
     * @return ImportFailure[]
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
