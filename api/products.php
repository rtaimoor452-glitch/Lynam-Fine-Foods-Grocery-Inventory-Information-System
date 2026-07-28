<?php
/**
 * Products API
 * 
 * This is the main API file that handles all CRUD operations for products.
 * It receives HTTP requests from the JavaScript frontend (via the Fetch API),
 * processes them, interacts with the SQLite database using PDO prepared
 * statements, and returns JSON responses with appropriate HTTP status codes.
 * 
 * Endpoints:
 *   GET    api/products.php          - Get all products
 *   GET    api/products.php?id=1     - Get one product by ID
 *   POST   api/products.php          - Create a new product
 *   PUT    api/products.php?id=1     - Update a product
 *   DELETE api/products.php?id=1     - Delete a product
 * 
 * All responses are in JSON format.
 * All database queries use PDO prepared statements to prevent SQL injection.
 */

// ============================================================
// HEADERS
// ============================================================

// Tell the browser that the response is JSON
header('Content-Type: application/json');

// Allow cross-origin requests (needed when frontend and backend
// are served from the same PHP development server)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request (sent by browsers before PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// DATABASE CONNECTION
// ============================================================

// Include the database configuration file
// This gives us access to the getDatabase() function
require_once __DIR__ . '/../config/database.php';

// Get a PDO connection to the SQLite database
$pdo = getDatabase();

// ============================================================
// REQUEST ROUTING
// ============================================================

// Get the HTTP method (GET, POST, PUT or DELETE)
$method = $_SERVER['REQUEST_METHOD'];

// Get the product ID from the URL query string if it was provided
// For example: api/products.php?id=1 will set $id to "1"
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Route the request to the correct function based on the HTTP method
switch ($method) {

    case 'GET':
        // If an ID was provided, get one product; otherwise get all
        if ($id !== null) {
            getProduct($pdo, $id);
        } else {
            getAllProducts($pdo);
        }
        break;

    case 'POST':
        // Create a new product
        createProduct($pdo);
        break;

    case 'PUT':
        // Update an existing product (ID is required)
        if ($id !== null) {
            updateProduct($pdo, $id);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID is required for update']);
        }
        break;

    case 'DELETE':
        // Delete a product (ID is required)
        if ($id !== null) {
            deleteProduct($pdo, $id);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID is required for deletion']);
        }
        break;

    default:
        // Any other HTTP method is not allowed
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// ============================================================
// CRUD FUNCTIONS
// ============================================================

/**
 * GET all products
 * 
 * Retrieves every product from the database and returns them
 * as a JSON array. Products are ordered by ID descending so
 * the newest products appear first.
 * 
 * @param PDO $pdo The database connection
 */
function getAllProducts($pdo) {
    try {
        // Execute a simple SELECT query to get all products
        $stmt = $pdo->query('SELECT * FROM products ORDER BY id DESC');
        $products = $stmt->fetchAll();

        // Return 200 OK with the products array
        http_response_code(200);
        echo json_encode($products);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to retrieve products: ' . $e->getMessage()
        ]);
    }
}

/**
 * GET one product by ID
 * 
 * Retrieves a single product matching the given ID.
 * Returns 404 if the product is not found.
 * 
 * @param PDO $pdo The database connection
 * @param mixed $id The product ID from the query string
 */
function getProduct($pdo, $id) {
    // Validate that the ID is a positive integer
    if (!filter_var($id, FILTER_VALIDATE_INT) || intval($id) < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid product ID. ID must be a positive integer.']);
        return;
    }

    try {
        // Use a prepared statement to prevent SQL injection
        // The :id placeholder is safely replaced with the actual value
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $product = $stmt->fetch();

        if ($product) {
            // Product found - return 200 OK
            http_response_code(200);
            echo json_encode($product);
        } else {
            // Product not found - return 404
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to retrieve product: ' . $e->getMessage()
        ]);
    }
}

/**
 * POST - Create a new product
 * 
 * Reads JSON data from the request body (sent by JavaScript Fetch API),
 * validates the input, trims whitespace, and inserts a new row into
 * the products table.
 * 
 * Returns the newly created product with a 201 Created status code.
 * 
 * @param PDO $pdo The database connection
 */
function createProduct($pdo) {
    // Read the raw JSON data from the request body
    // file_get_contents('php://input') reads the raw POST data
    // json_decode converts the JSON string into a PHP associative array
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // Check if the JSON was valid
    if ($input === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }

    // Validate the input data using our validation function
    $errors = validateProduct($input);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Validation failed',
            'details' => $errors
        ]);
        return;
    }

    // Sanitise input - trim whitespace from strings
    $productName = trim($input['product_name']);
    $category    = trim($input['category']);
    $price       = floatval($input['price']);
    $quantity    = intval($input['quantity']);
    $supplier    = isset($input['supplier']) ? trim($input['supplier']) : '';
    $expiryDate  = isset($input['expiry_date']) ? trim($input['expiry_date']) : '';

    try {
        // Insert the new product using a prepared statement
        // Prepared statements prevent SQL injection by separating
        // the SQL command from the data values
        $stmt = $pdo->prepare('
            INSERT INTO products
                (product_name, category, price, quantity, supplier, expiry_date,
                 created_at, updated_at)
            VALUES
                (:product_name, :category, :price, :quantity, :supplier, :expiry_date,
                 CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        $stmt->execute([
            ':product_name' => $productName,
            ':category'     => $category,
            ':price'        => $price,
            ':quantity'     => $quantity,
            ':supplier'     => $supplier,
            ':expiry_date'  => $expiryDate
        ]);

        // Get the auto-generated ID of the new product
        $newId = $pdo->lastInsertId();

        // Fetch the complete product record to return to the frontend
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => $newId]);
        $product = $stmt->fetch();

        // Return 201 Created with the new product data
        http_response_code(201);
        echo json_encode([
            'message' => 'Product created successfully',
            'product' => $product
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to create product: ' . $e->getMessage()
        ]);
    }
}

/**
 * PUT - Update an existing product
 * 
 * Reads JSON data from the request body, validates it, checks that
 * the product exists, and updates the database record.
 * 
 * Returns the updated product with a 200 OK status code.
 * 
 * @param PDO $pdo The database connection
 * @param mixed $id The product ID from the query string
 */
function updateProduct($pdo, $id) {
    // Validate that the ID is a positive integer
    if (!filter_var($id, FILTER_VALIDATE_INT) || intval($id) < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid product ID. ID must be a positive integer.']);
        return;
    }

    // Check if the product exists before trying to update it
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => intval($id)]);
    $existingProduct = $stmt->fetch();

    if (!$existingProduct) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }

    // Read and parse the JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if ($input === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }

    // Validate the input
    $errors = validateProduct($input);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Validation failed',
            'details' => $errors
        ]);
        return;
    }

    // Sanitise input
    $productName = trim($input['product_name']);
    $category    = trim($input['category']);
    $price       = floatval($input['price']);
    $quantity    = intval($input['quantity']);
    $supplier    = isset($input['supplier']) ? trim($input['supplier']) : '';
    $expiryDate  = isset($input['expiry_date']) ? trim($input['expiry_date']) : '';

    try {
        // Update the product using a prepared statement
        // We update the updated_at timestamp to record when the change was made
        $stmt = $pdo->prepare('
            UPDATE products
            SET product_name = :product_name,
                category     = :category,
                price        = :price,
                quantity     = :quantity,
                supplier     = :supplier,
                expiry_date  = :expiry_date,
                updated_at   = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        $stmt->execute([
            ':product_name' => $productName,
            ':category'     => $category,
            ':price'        => $price,
            ':quantity'     => $quantity,
            ':supplier'     => $supplier,
            ':expiry_date'  => $expiryDate,
            ':id'           => intval($id)
        ]);

        // Fetch the updated product to return to the frontend
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);
        $product = $stmt->fetch();

        // Return 200 OK with the updated product
        http_response_code(200);
        echo json_encode([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update product: ' . $e->getMessage()
        ]);
    }
}

/**
 * DELETE - Delete a product by ID
 * 
 * Checks that the product exists, then deletes it from the database.
 * Returns a success message with 200 OK.
 * 
 * @param PDO $pdo The database connection
 * @param mixed $id The product ID from the query string
 */
function deleteProduct($pdo, $id) {
    // Validate that the ID is a positive integer
    if (!filter_var($id, FILTER_VALIDATE_INT) || intval($id) < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid product ID. ID must be a positive integer.']);
        return;
    }

    // Check if the product exists before trying to delete it
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => intval($id)]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        return;
    }

    try {
        // Delete the product using a prepared statement
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => intval($id)]);

        // Return 200 OK with a success message
        http_response_code(200);
        echo json_encode(['message' => 'Product deleted successfully']);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to delete product: ' . $e->getMessage()
        ]);
    }
}

// ============================================================
// VALIDATION FUNCTION
// ============================================================

/**
 * Validate product input data
 * 
 * Checks all required fields and data types.
 * Returns an array of error messages. An empty array means
 * the data is valid.
 * 
 * This validation runs on the backend (PHP) regardless of
 * whether the frontend (JavaScript) has already validated.
 * Never trust frontend validation alone.
 * 
 * @param array $data The product data to validate
 * @return array Array of error messages (empty if valid)
 */
function validateProduct($data) {
    $errors = [];

    // Product name is required and must not be empty after trimming
    if (!isset($data['product_name']) || trim($data['product_name']) === '') {
        $errors[] = 'Product name is required';
    }

    // Category is required and must not be empty after trimming
    if (!isset($data['category']) || trim($data['category']) === '') {
        $errors[] = 'Category is required';
    }

    // Price must be present, numeric, and not negative
    if (!isset($data['price']) || $data['price'] === '' || !is_numeric($data['price'])) {
        $errors[] = 'Price must be a valid number';
    } elseif (floatval($data['price']) < 0) {
        $errors[] = 'Price cannot be negative';
    }

    // Quantity must be present, numeric, a whole number, and not negative
    if (!isset($data['quantity']) || $data['quantity'] === '' || !is_numeric($data['quantity'])) {
        $errors[] = 'Quantity must be a valid number';
    } elseif (intval($data['quantity']) != floatval($data['quantity'])) {
        $errors[] = 'Quantity must be a whole number';
    } elseif (intval($data['quantity']) < 0) {
        $errors[] = 'Quantity cannot be negative';
    }

    // Expiry date is optional, but must be valid YYYY-MM-DD if provided
    if (isset($data['expiry_date']) && trim($data['expiry_date']) !== '') {
        $dateString = trim($data['expiry_date']);
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        if (!$date || $date->format('Y-m-d') !== $dateString) {
            $errors[] = 'Expiry date must be a valid date in YYYY-MM-DD format';
        }
    }

    return $errors;
}
