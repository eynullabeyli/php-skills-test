<?php

declare(strict_types=1);

/**
 * Checks and cleans the three product fields. The server is the single
 * source of truth for these rules; the page shows its messages next to the
 * fields they belong to.
 */
final class ProductValidator
{
    const MAX_NAME_LENGTH = 120;
    const MAX_QUANTITY = 1000000000;
    const MAX_PRICE = 1000000;

    /**
     * @param array<string, mixed> $input raw fields from the request
     * @return array{0: array<string, mixed>, 1: array<string, string>} [clean values, error message per field]
     */
    public static function validate(array $input): array
    {
        $values = [];
        $errors = [];

        $name = trim(self::text($input['product_name'] ?? null));
        if ($name === '') {
            $errors['product_name'] = 'Enter a product name.';
        } elseif (self::length($name) > self::MAX_NAME_LENGTH) {
            $errors['product_name'] = 'Use at most ' . self::MAX_NAME_LENGTH . ' characters.';
        } else {
            $values['product_name'] = $name;
        }

        $quantity = trim(self::text($input['quantity_in_stock'] ?? null));
        if ($quantity === '') {
            $errors['quantity_in_stock'] = 'Enter the quantity in stock.';
        } elseif (!preg_match('/^\d+$/', $quantity)) {
            $errors['quantity_in_stock'] = 'Use a whole number, 0 or more.';
        } elseif (strlen(ltrim($quantity, '0')) > 10 || (int) $quantity > self::MAX_QUANTITY) {
            $errors['quantity_in_stock'] = 'Use a number no larger than ' . number_format(self::MAX_QUANTITY) . '.';
        } else {
            $values['quantity_in_stock'] = (int) $quantity;
        }

        $price = trim(self::text($input['price_per_item'] ?? null));
        if ($price === '') {
            $errors['price_per_item'] = 'Enter the price per item.';
        } elseif (!preg_match('/^(?:\d+(?:\.\d{0,2})?|\.\d{1,2})$/', $price)) {
            $errors['price_per_item'] = 'Use a number with up to 2 decimals, for example 4.99.';
        } elseif ((float) $price > self::MAX_PRICE) {
            $errors['price_per_item'] = 'Use a price no larger than ' . number_format(self::MAX_PRICE) . '.';
        } else {
            $values['price_per_item'] = round((float) $price, 2);
        }

        return [$values, $errors];
    }

    /**
     * @param mixed $value
     */
    private static function text($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return ''; // Missing, null, arrays and booleans count as empty.
    }

    private static function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return (int) preg_match_all('/./su', $text);
    }
}
