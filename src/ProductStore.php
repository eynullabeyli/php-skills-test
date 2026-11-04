<?php

declare(strict_types=1);

/**
 * Thrown when the JSON data file cannot be read or written. Its message is
 * safe to show to the person using the page.
 */
final class StorageException extends RuntimeException
{
}

/**
 * Keeps products in a JSON file.
 *
 * Reads take a shared lock and writes an exclusive lock on the file, so two
 * people saving at the same moment cannot overwrite each other's changes.
 */
final class ProductStore
{
    /** @var string */
    private $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /**
     * All products, oldest submission first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $handle = $this->open('rb');
        try {
            flock($handle, LOCK_SH);
            $products = $this->read($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        usort($products, function (array $a, array $b): int {
            // Timestamps are UTC ISO 8601 strings, so they sort as text.
            // The id breaks ties between products saved in the same second.
            return [$a['submitted_at'], $a['id']] <=> [$b['submitted_at'], $b['id']];
        });

        return $products;
    }

    /**
     * Saves a new product and returns it with its id and timestamp.
     *
     * @param array{product_name: string, quantity_in_stock: int, price_per_item: float} $fields
     * @return array<string, mixed>
     */
    public function create(array $fields): array
    {
        return $this->change(function (array $products) use ($fields): array {
            $ids = array_column($products, 'id');
            $product = [
                'id' => $ids ? max($ids) + 1 : 1,
                'product_name' => $fields['product_name'],
                'quantity_in_stock' => $fields['quantity_in_stock'],
                'price_per_item' => $fields['price_per_item'],
                'submitted_at' => self::now(),
                'updated_at' => null,
            ];
            $products[] = $product;

            return [$products, $product];
        });
    }

    /**
     * Changes a product's fields. The original submission time is kept so
     * the product keeps its place in the list.
     *
     * @param array{product_name: string, quantity_in_stock: int, price_per_item: float} $fields
     * @return array<string, mixed>|null the updated product, or null if no product has this id
     */
    public function update(int $id, array $fields): ?array
    {
        return $this->change(function (array $products) use ($id, $fields): array {
            foreach ($products as $index => $product) {
                if ($product['id'] === $id) {
                    $products[$index] = array_merge($product, $fields, ['updated_at' => self::now()]);

                    return [$products, $products[$index]];
                }
            }

            return [null, null]; // Nothing to write.
        });
    }

    /**
     * Whether the web server can save to the data file.
     */
    public function isWritable(): bool
    {
        return is_file($this->file) ? is_writable($this->file) : is_writable(dirname($this->file));
    }

    /**
     * Runs $edit on the stored products while holding an exclusive lock.
     * $edit returns [new product list or null to skip writing, result].
     *
     * @return mixed the result returned by $edit
     */
    private function change(callable $edit)
    {
        $handle = $this->open('c+b');
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException('The data file is busy. Please try again.');
            }

            list($products, $result) = $edit($this->read($handle));

            if ($products !== null) {
                $json = json_encode(
                    array_values($products),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
                );
                if ($json === false) {
                    throw new RuntimeException('Could not encode products as JSON: ' . json_last_error_msg());
                }

                ftruncate($handle, 0);
                rewind($handle);
                if (fwrite($handle, $json . "\n") === false) {
                    throw new StorageException('Could not save to ' . $this->displayName() . '.');
                }
                fflush($handle);
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array<int, array<string, mixed>>
     */
    private function read($handle): array
    {
        rewind($handle);
        $raw = stream_get_contents($handle);
        if ($raw === false) {
            throw new StorageException('Could not read ' . $this->displayName() . '.');
        }
        if (trim($raw) === '') {
            return [];
        }

        $products = json_decode($raw, true);
        if (!is_array($products)) {
            // Refuse to continue rather than overwrite a damaged file.
            throw new StorageException($this->displayName() . ' does not contain valid JSON. Fix it, or replace its contents with [] to start over.');
        }

        return array_values($products);
    }

    /**
     * @return resource
     */
    private function open(string $mode)
    {
        $handle = @fopen($this->file, $mode);
        if ($handle === false) {
            $action = $mode === 'rb' ? 'read' : 'save to';
            throw new StorageException(
                'The server cannot ' . $action . ' ' . $this->displayName() . '. Make sure the web server user can write to the data folder.'
            );
        }

        return $handle;
    }

    private function displayName(): string
    {
        return basename(dirname($this->file)) . '/' . basename($this->file);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
