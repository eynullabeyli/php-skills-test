<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require __DIR__ . '/src/bootstrap.php';

$store = new ProductStore(DATA_FILE);
$notice = null;

try {
    $initial = inventory_payload($store);
    if (!$store->isWritable()) {
        $notice = 'The web server cannot save to data/products.json, so new products and edits will fail. '
            . 'Give the web server user write access to the data folder (for example: chmod 777 data && chmod 666 data/products.json).';
    }
} catch (StorageException $e) {
    $initial = ['products' => [], 'sum_total' => 0];
    $notice = $e->getMessage();
}

/** Escapes text for HTML output. */
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product inventory</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="bg-body-tertiary">
<main class="container py-4 py-md-5">
    <header class="mb-4">
        <h1 class="h3 mb-1">Product inventory</h1>
        <p class="text-body-secondary mb-0">Add a product and the list below updates straight away.</p>
    </header>

    <?php if ($notice !== null): ?>
        <div class="alert alert-warning" role="alert"><?= e($notice) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm mb-4" aria-labelledby="add-heading">
        <div class="card-body p-4">
            <h2 id="add-heading" class="h5 mb-3">Add a product</h2>
            <form id="product-form" class="row g-3" novalidate autocomplete="off">
                <div class="col-lg-5">
                    <label for="product_name" class="form-label">Product name</label>
                    <input type="text" class="form-control" id="product_name" name="product_name"
                           maxlength="<?= ProductValidator::MAX_NAME_LENGTH ?>" required
                           aria-describedby="product_name-error">
                    <div class="invalid-feedback" id="product_name-error"></div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label for="quantity_in_stock" class="form-label">Quantity in stock</label>
                    <input type="text" class="form-control text-end" id="quantity_in_stock" name="quantity_in_stock"
                           inputmode="numeric" placeholder="e.g. 25" required
                           aria-describedby="quantity_in_stock-error">
                    <div class="invalid-feedback" id="quantity_in_stock-error"></div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label for="price_per_item" class="form-label">Price per item</label>
                    <input type="text" class="form-control text-end" id="price_per_item" name="price_per_item"
                           inputmode="decimal" placeholder="e.g. 4.99" required
                           aria-describedby="price_per_item-error">
                    <div class="invalid-feedback" id="price_per_item-error"></div>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <div class="form-label d-none d-sm-block" aria-hidden="true">&nbsp;</div>
                    <button type="submit" class="btn btn-primary w-100">
                        <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>
                        Add product
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm" aria-labelledby="list-heading">
        <div class="card-body px-4 pt-4 pb-2 d-flex flex-wrap justify-content-between align-items-baseline gap-2">
            <h2 id="list-heading" class="h5 mb-0">Submitted products</h2>
            <span class="text-body-secondary small" id="product-count"></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="product-table">
                <thead>
                <tr>
                    <th scope="col">Product name</th>
                    <th scope="col" class="text-end">Quantity in stock</th>
                    <th scope="col" class="text-end">Price per item</th>
                    <th scope="col">Datetime submitted</th>
                    <th scope="col" class="text-end">Total value number</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody id="product-rows"></tbody>
                <tfoot>
                <tr class="sum-row">
                    <th scope="row" colspan="4">Sum of all total values</th>
                    <td class="text-end num" id="sum-total"></td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>
</main>

<div id="flash" class="flash alert d-none" role="status" aria-live="polite"></div>

<script id="initial-data" type="application/json"><?= json_encode(
    $initial,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?></script>
<script src="assets/app.js"></script>
</body>
</html>
