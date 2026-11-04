<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductStore.php';
require_once __DIR__ . '/ProductValidator.php';

define('DATA_FILE', dirname(__DIR__) . '/data/products.json');

/**
 * Products ordered by submission time, each with its total value
 * (quantity x price), plus the sum of all totals.
 *
 * Money is added up in whole cents so totals never pick up floating-point
 * noise such as 0.1 + 0.2 = 0.30000000000000004.
 *
 * @return array{products: array<int, array<string, mixed>>, sum_total: int|float}
 */
function inventory_payload(ProductStore $store): array
{
    $products = [];
    $sumCents = 0;

    foreach ($store->all() as $product) {
        $totalCents = $product['quantity_in_stock'] * (int) round($product['price_per_item'] * 100);
        $sumCents += $totalCents;
        $product['total_value'] = $totalCents / 100;
        $products[] = $product;
    }

    return ['products' => $products, 'sum_total' => $sumCents / 100];
}
