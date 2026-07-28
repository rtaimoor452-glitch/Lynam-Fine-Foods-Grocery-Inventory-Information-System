<?php
/**
 * Unit Tests - Product Validation and CRUD Operations
 * 
 * This file tests:
 * - Product validation rules (required fields, data types, boundaries)
 * - CRUD operations (Create, Read, Update, Delete) against a test database
 * - Error handling for invalid inputs
 * 
 * How to run:
 *   Open a terminal in the project root directory and run:
 *   php tests/test_validation.php
 * 
 * These tests use a separate test database (test_products.db) to avoid
 * affecting the production data in products.db.
 */

// ============================================================
// TEST FRAMEWORK (Simple custom test runner)
// ============================================================

// Counters to track test results
$testsPassed = 0;
$testsFailed = 0;
$totalTests  = 0;

/**
 * Run a single test and report the result
 * 
 * @param string $testName   A description of what is being tested
 * @param bool   $condition  true if the test passes, false if it fails
 */
function runTest($testName, $condition) {
    global $testsPassed, $testsFailed, $totalTests;
    $totalTests++;

    if ($condition) {
        echo "  PASS: $testName\n";
        $testsPassed++;
    } else {
        echo "  FAIL: $testName\n";
        $testsFailed++;
    }
}

// ============================================================
// VALIDATION FUNCTION (duplicated from API for unit testing)
// ============================================================

// Maximum allowed values for validation (must match api/products.php)
define('MAX_NAME_LENGTH', 100);
define('MAX_SUPPLIER_LENGTH', 100);
define('MAX_PRICE', 99999);
define('MAX_QUANTITY', 99999);

/**
 * Validate product input data
 * 
 * This is the same validation logic used in api/products.php.
 * It is duplicated here so we can test it independently without
 * making HTTP requests.
 * 
 * @param array $data The product data to validate
 * @return array Array of error messages (empty if valid)
 */
function validateProduct($data) {
    $errors = [];

    // Product name is required
    if (!isset($data['product_name']) || trim($data['product_name']) === '') {
        $errors[] = 'Product name is required';
    } elseif (strlen(trim($data['product_name'])) > MAX_NAME_LENGTH) {
        $errors[] = 'Product name must be ' . MAX_NAME_LENGTH . ' characters or less';
    }

    // Category is required
    if (!isset($data['category']) || trim($data['category']) === '') {
        $errors[] = 'Category is required';
    }

    // Price must be numeric and not negative
    if (!isset($data['price']) || $data['price'] === '' || !is_numeric($data['price'])) {
        $errors[] = 'Price must be a valid number';
    } elseif (floatval($data['price']) < 0) {
        $errors[] = 'Price cannot be negative';
    } elseif (floatval($data['price']) > MAX_PRICE) {
        $errors[] = 'Price cannot exceed ' . MAX_PRICE;
    }

    // Quantity must be a whole number and not negative
    if (!isset($data['quantity']) || $data['quantity'] === '' || !is_numeric($data['quantity'])) {
        $errors[] = 'Quantity must be a valid number';
    } elseif (intval($data['quantity']) != floatval($data['quantity'])) {
        $errors[] = 'Quantity must be a whole number';
    } elseif (intval($data['quantity']) < 0) {
        $errors[] = 'Quantity cannot be negative';
    } elseif (intval($data['quantity']) > MAX_QUANTITY) {
        $errors[] = 'Quantity cannot exceed ' . MAX_QUANTITY;
    }

    // Supplier is optional but has a maximum length
    if (isset($data['supplier']) && trim($data['supplier']) !== '') {
        if (strlen(trim($data['supplier'])) > MAX_SUPPLIER_LENGTH) {
            $errors[] = 'Supplier name must be ' . MAX_SUPPLIER_LENGTH . ' characters or less';
        }
    }

    // Expiry date must be valid if provided
    if (isset($data['expiry_date']) && trim($data['expiry_date']) !== '') {
        $dateString = trim($data['expiry_date']);
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        if (!$date || $date->format('Y-m-d') !== $dateString) {
            $errors[] = 'Expiry date must be a valid date in YYYY-MM-DD format';
        }
    }

    return $errors;
}

// ============================================================
// VALIDATION TESTS
// ============================================================

echo "\n========================================\n";
echo "UNIT TESTS - Product Validation\n";
echo "========================================\n\n";

echo "--- Input Validation Tests ---\n\n";

// Test 1: Valid product should pass all validation
$validProduct = [
    'product_name' => 'Irish Soda Bread',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20,
    'supplier'     => 'Local Bakery',
    'expiry_date'  => '2025-12-31'
];
$errors = validateProduct($validProduct);
runTest('Valid product passes validation', count($errors) === 0);

// Test 2: Empty product name should fail
$emptyName = [
    'product_name' => '',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20
];
$errors = validateProduct($emptyName);
runTest('Empty product name fails validation', count($errors) > 0);

// Test 3: Missing product name key should fail
$missingName = [
    'category' => 'Bakery',
    'price'    => 3.50,
    'quantity' => 20
];
$errors = validateProduct($missingName);
runTest('Missing product name fails validation', count($errors) > 0);

// Test 4: Empty category should fail
$emptyCategory = [
    'product_name' => 'Test Product',
    'category'     => '',
    'price'        => 3.50,
    'quantity'     => 20
];
$errors = validateProduct($emptyCategory);
runTest('Empty category fails validation', count($errors) > 0);

// Test 5: Negative price should fail
$negativePrice = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => -5.00,
    'quantity'     => 20
];
$errors = validateProduct($negativePrice);
runTest('Negative price fails validation', count($errors) > 0);

// Test 6: Negative quantity should fail
$negativeQuantity = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => -10
];
$errors = validateProduct($negativeQuantity);
runTest('Negative quantity fails validation', count($errors) > 0);

// Test 7: Decimal quantity should fail (must be whole number)
$decimalQuantity = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 5.5
];
$errors = validateProduct($decimalQuantity);
runTest('Decimal quantity fails validation', count($errors) > 0);

// Test 8: Zero price should pass (free items are valid)
$zeroPrice = [
    'product_name' => 'Free Sample',
    'category'     => 'Other',
    'price'        => 0,
    'quantity'     => 10
];
$errors = validateProduct($zeroPrice);
runTest('Zero price passes validation', count($errors) === 0);

// Test 9: Zero quantity should pass (out of stock is valid)
$zeroQuantity = [
    'product_name' => 'Out of Stock Item',
    'category'     => 'Grocery',
    'price'        => 2.99,
    'quantity'     => 0
];
$errors = validateProduct($zeroQuantity);
runTest('Zero quantity passes validation', count($errors) === 0);

// Test 10: Invalid expiry date format should fail
$invalidDate = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20,
    'expiry_date'  => 'not-a-date'
];
$errors = validateProduct($invalidDate);
runTest('Invalid expiry date fails validation', count($errors) > 0);

// Test 11: Product name with only spaces should fail
$spacesOnly = [
    'product_name' => '   ',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20
];
$errors = validateProduct($spacesOnly);
runTest('Product name with only spaces fails validation', count($errors) > 0);

// Test 12: Valid product without optional fields should pass
$noOptionalFields = [
    'product_name' => 'Basic Product',
    'category'     => 'Grocery',
    'price'        => 1.99,
    'quantity'     => 50
];
$errors = validateProduct($noOptionalFields);
runTest('Product without optional fields passes validation', count($errors) === 0);

// Test 13: Product name exceeding maximum length should fail
$longName = [
    'product_name' => str_repeat('A', 101), // 101 characters exceeds max of 100
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20
];
$errors = validateProduct($longName);
runTest('Product name over 100 characters fails validation', count($errors) > 0);

// Test 14: Product name at exactly maximum length should pass
$exactLengthName = [
    'product_name' => str_repeat('A', 100), // Exactly 100 characters
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20
];
$errors = validateProduct($exactLengthName);
runTest('Product name at exactly 100 characters passes validation', count($errors) === 0);

// Test 15: Price exceeding maximum should fail
$highPrice = [
    'product_name' => 'Expensive Item',
    'category'     => 'Deli',
    'price'        => 100000, // Exceeds max of 99999
    'quantity'     => 5
];
$errors = validateProduct($highPrice);
runTest('Price over 99999 fails validation', count($errors) > 0);

// Test 16: Quantity exceeding maximum should fail
$highQuantity = [
    'product_name' => 'Bulk Item',
    'category'     => 'Grocery',
    'price'        => 10.00,
    'quantity'     => 100000 // Exceeds max of 99999
];
$errors = validateProduct($highQuantity);
runTest('Quantity over 99999 fails validation', count($errors) > 0);

// Test 17: Supplier name exceeding maximum length should fail
$longSupplier = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 20,
    'supplier'     => str_repeat('S', 101) // 101 characters exceeds max of 100
];
$errors = validateProduct($longSupplier);
runTest('Supplier name over 100 characters fails validation', count($errors) > 0);

// Test 18: Non-numeric price should fail
$nonNumericPrice = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => 'abc',
    'quantity'     => 20
];
$errors = validateProduct($nonNumericPrice);
runTest('Non-numeric price fails validation', count($errors) > 0);

// Test 19: Non-numeric quantity should fail
$nonNumericQuantity = [
    'product_name' => 'Test Product',
    'category'     => 'Bakery',
    'price'        => 3.50,
    'quantity'     => 'xyz'
];
$errors = validateProduct($nonNumericQuantity);
runTest('Non-numeric quantity fails validation', count($errors) > 0);

// ============================================================
// CRUD TESTS (using a separate test database)
// ============================================================

echo "\n--- CRUD Database Tests ---\n\n";

// Path to a separate test database
// This keeps test data separate from production data
$testDbPath = __DIR__ . '/../db/test_products.db';

// Remove old test database if it exists from a previous run
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

try {
    // Create a fresh test database connection
    $testPdo = new PDO('sqlite:' . $testDbPath);
    $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $testPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create the products table in the test database
    $testPdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_name TEXT NOT NULL,
            category TEXT NOT NULL,
            price REAL NOT NULL CHECK(price >= 0),
            quantity INTEGER NOT NULL CHECK(quantity >= 0),
            supplier TEXT,
            expiry_date TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- Test: CREATE a product ---
    $stmt = $testPdo->prepare('
        INSERT INTO products (product_name, category, price, quantity, supplier, expiry_date)
        VALUES (:product_name, :category, :price, :quantity, :supplier, :expiry_date)
    ');
    $stmt->execute([
        ':product_name' => 'Irish Soda Bread',
        ':category'     => 'Bakery',
        ':price'        => 3.50,
        ':quantity'     => 20,
        ':supplier'     => 'Local Bakery',
        ':expiry_date'  => '2025-12-31'
    ]);
    $newId = $testPdo->lastInsertId();
    runTest('Create product returns valid ID', $newId > 0);

    // --- Test: READ the created product ---
    $stmt = $testPdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $newId]);
    $product = $stmt->fetch();
    runTest('Read product returns correct name', $product['product_name'] === 'Irish Soda Bread');
    runTest('Read product returns correct category', $product['category'] === 'Bakery');
    runTest('Read product returns correct price', floatval($product['price']) === 3.50);
    runTest('Read product returns correct quantity', intval($product['quantity']) === 20);

    // --- Test: UPDATE the product ---
    $stmt = $testPdo->prepare('
        UPDATE products
        SET product_name = :product_name,
            price = :price,
            quantity = :quantity,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ');
    $stmt->execute([
        ':product_name' => 'Brown Soda Bread',
        ':price'        => 4.00,
        ':quantity'     => 15,
        ':id'           => $newId
    ]);

    // Read back the updated product to verify changes
    $stmt = $testPdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $newId]);
    $updated = $stmt->fetch();
    runTest('Update product changes name correctly', $updated['product_name'] === 'Brown Soda Bread');
    runTest('Update product changes price correctly', floatval($updated['price']) === 4.00);
    runTest('Update product changes quantity correctly', intval($updated['quantity']) === 15);

    // --- Test: DELETE the product ---
    $stmt = $testPdo->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $newId]);

    // Verify the product no longer exists
    $stmt = $testPdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $newId]);
    $deleted = $stmt->fetch();
    runTest('Delete product removes it from database', $deleted === false);

    // --- Test: READ with invalid ID (product not found) ---
    $stmt = $testPdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => 99999]);
    $notFound = $stmt->fetch();
    runTest('Invalid ID returns no product (product not found)', $notFound === false);

    // --- Test: READ with negative ID ---
    $stmt = $testPdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => -1]);
    $negativeId = $stmt->fetch();
    runTest('Negative ID returns no product', $negativeId === false);

    // Clean up: remove the test database file
    unlink($testDbPath);

} catch (PDOException $e) {
    echo "  DATABASE ERROR: " . $e->getMessage() . "\n";
    $testsFailed++;
    $totalTests++;
}

// ============================================================
// RESULTS SUMMARY
// ============================================================

echo "\n========================================\n";
echo "TEST RESULTS SUMMARY\n";
echo "========================================\n";
echo "Total tests:  $totalTests\n";
echo "Passed:       $testsPassed\n";
echo "Failed:       $testsFailed\n";
echo "\n";

if ($testsFailed === 0) {
    echo "STATUS: ALL TESTS PASSED\n";
} else {
    echo "STATUS: SOME TESTS FAILED\n";
}

echo "========================================\n\n";
