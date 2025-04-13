<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT");
header("Access-Control-Allow-Headers: Content-Type");

// Get cleaned URI segments (fix for subfolders like localhost/product-api/)
$basePath = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
$requestUri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$uri = array_values(array_diff($requestUri, $basePath));
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Load product list from file
function loadProducts() {
    $file = 'data.json';
    if (!file_exists($file)) file_put_contents($file, json_encode([]));
    return json_decode(file_get_contents($file), true);
}

// Save product list to file
function saveProducts($products) {
    file_put_contents('data.json', json_encode($products, JSON_PRETTY_PRINT));
}

// Input validation
function validateProduct($data, $partial = false) {
    $errors = [];

    if (!$partial || isset($data['name'])) {
        if (empty($data['name'])) $errors[] = 'Name is required';
        elseif (!is_string($data['name']) || strlen($data['name']) > 255) $errors[] = 'Invalid name';
    }

    if (isset($data['description']) && !is_string($data['description'])) {
        $errors[] = 'Description must be a string';
    }

    if (!$partial || isset($data['price'])) {
        if (!is_numeric($data['price']) || $data['price'] <= 0) $errors[] = 'Invalid price';
    }

    if (!$partial || isset($data['quantity'])) {
        if (!is_int($data['quantity']) || $data['quantity'] < 0) $errors[] = 'Invalid quantity';
    }

    return $errors;
}

// Routing logic
if (isset($uri[0]) && $uri[0] === 'products') {
    // Create product
    if ($method === 'POST') {
        $errors = validateProduct($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['errors' => $errors]);
            exit;
        }

        $products = loadProducts();
        $id = count($products) > 0 ? end($products)['id'] + 1 : 1;

        $product = [
            'id' => $id,
            'name' => $input['name'],
            'description' => $input['description'] ?? '',
            'price' => $input['price'],
            'quantity' => $input['quantity']
        ];

        $products[] = $product;
        saveProducts($products);

        http_response_code(201);
        echo json_encode($product);
        exit;

    } elseif ($method === 'GET' && isset($uri[1])) {
        // Get product by ID
        $id = (int)$uri[1];
        $products = loadProducts();
        foreach ($products as $product) {
            if ($product['id'] === $id) {
                echo json_encode($product);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        exit;

    } elseif ($method === 'PUT' && isset($uri[1])) {
        // Update product by ID
        $id = (int)$uri[1];
        $products = loadProducts();
        $updated = false;

        foreach ($products as &$product) {
            if ($product['id'] === $id) {
                $errors = validateProduct($input, true);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['errors' => $errors]);
                    exit;
                }

                $product = array_merge($product, $input);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            saveProducts($products);
            echo json_encode($product);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Product not found']);
        }
        exit;
    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        exit;
    }
} else {
    http_response_code(404);
    echo json_encode(['message' => 'Route not found']);
    exit;
}
?>
