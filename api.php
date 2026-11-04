<?php

/**
 * JSON API used by the page over Ajax.
 *
 *   GET  api.php?action=list           all products and the sum of totals
 *   POST api.php?action=create         add a product (JSON body)
 *   POST api.php?action=update&id=N    change product N (JSON body)
 *
 * Every response includes the full, freshly sorted product list so the page
 * can redraw the table from one source of truth.
 */

declare(strict_types=1);

// A PHP warning printed into the response would break the JSON.
ini_set('display_errors', '0');

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/**
 * @param array<string, mixed> $body
 */
function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        respond(405, ['error' => 'Use ' . $method . ' for this action.']);
    }
}

/**
 * The request body as an array. Only JSON is accepted: browsers cannot send
 * a JSON body from another site without a CORS preflight, which this API
 * never approves, so other sites cannot submit data here (basic CSRF guard).
 *
 * @return array<string, mixed>
 */
function read_json_body(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== 0) {
        respond(415, ['error' => 'Send the data as JSON (Content-Type: application/json).']);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respond(400, ['error' => 'The request body is not valid JSON.']);
    }

    return $data;
}

/**
 * Validates the body, or stops with a 422 listing what to fix.
 *
 * @return array<string, mixed>
 */
function validated_fields(): array
{
    list($values, $errors) = ProductValidator::validate(read_json_body());
    if ($errors) {
        respond(422, ['error' => 'Please fix the highlighted fields.', 'errors' => $errors]);
    }

    return $values;
}

$store = new ProductStore(DATA_FILE);
$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'list';

try {
    switch ($action) {
        case 'list':
            require_method('GET');
            respond(200, inventory_payload($store));
            break;

        case 'create':
            require_method('POST');
            $product = $store->create(validated_fields());
            respond(201, ['message' => 'Product added.', 'id' => $product['id']] + inventory_payload($store));
            break;

        case 'update':
            require_method('POST');
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                respond(400, ['error' => 'Missing or invalid product id.']);
            }
            $product = $store->update($id, validated_fields());
            if ($product === null) {
                respond(404, ['error' => 'That product no longer exists. Reload the page.']);
            }
            respond(200, ['message' => 'Changes saved.', 'id' => $id] + inventory_payload($store));
            break;

        default:
            respond(404, ['error' => 'Unknown action.']);
    }
} catch (StorageException $e) {
    error_log('[product-inventory] ' . $e->getMessage());
    respond(500, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[product-inventory] ' . $e);
    respond(500, ['error' => 'Something went wrong on the server. Please try again.']);
}
