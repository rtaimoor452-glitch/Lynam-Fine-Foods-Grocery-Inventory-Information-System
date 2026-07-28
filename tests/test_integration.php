<?php
/**
 * Integration Test - Frontend to Backend CRUD Flow
 * 
 * This test verifies the full integration between the frontend and backend:
 * 
 * 1. A user enters product information (simulated by this test)
 * 2. JavaScript sends a Fetch API request (simulated by cURL HTTP requests)
 * 3. PHP processes the request via the API endpoint
 * 4. SQLite stores the product data
 * 5. The API returns a JSON response
 * 6. JavaScript displays the product in the table (verified by checking JSON)
 * 
 * PREREQUISITE: The PHP built-in server must be running.
 * Start it from the project root directory with:
 *   php -S localhost:8000
 * 
 * Then run this test:
 *   php tests/test_integration.php
 * 
 * This test uses cURL to simulate the HTTP requests that the JavaScript
 * Fetch API would make from the browser. The requests are identical -
 * same URL, same headers, same JSON body.
 */

// ============================================================
// CONFIGURATION
// ============================================================

// The base URL of the API (must match the running PHP server)
$baseUrl = 'http://localhost:8000/api/products.php';

// Test counters
$testsPassed = 0;
$testsFailed = 0;
$totalTests  = 0;

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Run a single test and report the result
 * 
 * @param string $testName   Description of the test
 * @param bool   $condition  true = pass, false = fail
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

/**
 * Make an HTTP request to the API
 * 
 * This function uses cURL to simulate the HTTP requests that
 * JavaScript's Fetch API would send from the browser.
 * 
 * @param string $url    The full URL to request
 * @param string $method The HTTP method (GET, POST, PUT, DELETE)
 * @param array  $data   Optional data to send as JSON (for POST/PUT)
 * @return array Contains 'httpCode', 'body' (parsed JSON), and 'raw' (string)
 */
function apiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // If data is provided, send it as JSON in the request body
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    // If cURL itself failed (e.g. server not running)
    if ($error) {
        return ['error' => $error, 'httpCode' => 0, 'body' => null, 'raw' => ''];
    }

    return [
        'httpCode' => $httpCode,
        'body'     => json_decode($response, true),
        'raw'      => $response
    ];
}

// ============================================================
// PRE-FLIGHT CHECK
// ============================================================

echo "\n========================================\n";
echo "INTEGRATION TEST - Full CRUD Flow\n";
echo "========================================\n\n";

// Check if the PHP development server is running
$checkResponse = apiRequest($baseUrl);
if ($checkResponse['httpCode'] === 0) {
    echo "ERROR: Cannot connect to $baseUrl\n";
    echo "\n";
    echo "The PHP development server must be running for integration tests.\n";
    echo "Open a separate terminal and run:\n";
    echo "\n";
    echo "  php -S localhost:8000\n";
    echo "\n";
    echo "Then run this test again:\n";
    echo "\n";
    echo "  php tests/test_integration.php\n";
    echo "\n";
    exit(1);
}

echo "Connected to server at $baseUrl\n";
echo "Starting integration tests...\n\n";

// ============================================================
// STEP 1: CREATE a product (simulates user filling in the form)
// ============================================================

echo "--- Step 1: Create Product (POST request) ---\n";
echo "  Simulating: User enters product data and clicks 'Add Product'\n";
echo "  JavaScript sends: POST api/products.php with JSON body\n\n";

// This is the data that JavaScript would collect from the form
$newProduct = [
    'product_name' => 'Integration Test Bread',
    'category'     => 'Bakery',
    'price'        => 2.99,
    'quantity'     => 15,
    'supplier'     => 'Test Supplier',
    'expiry_date'  => '2025-12-31'
];

$createResponse = apiRequest($baseUrl, 'POST', $newProduct);

runTest('POST returns HTTP 201 (Created)', $createResponse['httpCode'] === 201);
runTest('Response contains success message', isset($createResponse['body']['message']));
runTest('Response contains the created product', isset($createResponse['body']['product']));

// Extract the created product for use in later tests
$createdProduct = isset($createResponse['body']['product']) ? $createResponse['body']['product'] : null;
$createdId = $createdProduct ? $createdProduct['id'] : null;

runTest('Created product has a valid ID', $createdId !== null && $createdId > 0);
runTest('Product name matches input', $createdProduct && $createdProduct['product_name'] === 'Integration Test Bread');
runTest('Product price matches input', $createdProduct && floatval($createdProduct['price']) == 2.99);
runTest('Product quantity matches input', $createdProduct && intval($createdProduct['quantity']) === 15);

echo "\n";

// ============================================================
// STEP 2: READ the product by ID (simulates viewing a product)
// ============================================================

echo "--- Step 2: Read Product by ID (GET request) ---\n";
echo "  JavaScript sends: GET api/products.php?id=$createdId\n\n";

if ($createdId) {
    $readResponse = apiRequest($baseUrl . '?id=' . $createdId);

    runTest('GET by ID returns HTTP 200 (OK)', $readResponse['httpCode'] === 200);
    runTest('Returned product name is correct', $readResponse['body']['product_name'] === 'Integration Test Bread');
    runTest('Returned category is correct', $readResponse['body']['category'] === 'Bakery');
    runTest('Returned quantity is correct', intval($readResponse['body']['quantity']) === 15);
    runTest('Returned supplier is correct', $readResponse['body']['supplier'] === 'Test Supplier');
} else {
    echo "  SKIP: Cannot read - product creation failed\n";
}

echo "\n";

// ============================================================
// STEP 3: READ all products (simulates loading the product table)
// ============================================================

echo "--- Step 3: Read All Products (GET request) ---\n";
echo "  JavaScript sends: GET api/products.php\n";
echo "  JavaScript displays the products array in the HTML table\n\n";

$allResponse = apiRequest($baseUrl);

runTest('GET all returns HTTP 200 (OK)', $allResponse['httpCode'] === 200);
runTest('Response is a JSON array', is_array($allResponse['body']));
runTest('Array contains at least one product', is_array($allResponse['body']) && count($allResponse['body']) >= 1);

echo "\n";

// ============================================================
// STEP 4: UPDATE the product (simulates editing via the form)
// ============================================================

echo "--- Step 4: Update Product (PUT request) ---\n";
echo "  Simulating: User clicks Edit, changes values, clicks 'Update Product'\n";
echo "  JavaScript sends: PUT api/products.php?id=$createdId with JSON body\n\n";

if ($createdId) {
    $updatedData = [
        'product_name' => 'Updated Test Bread',
        'category'     => 'Bakery',
        'price'        => 3.49,
        'quantity'     => 25,
        'supplier'     => 'Updated Supplier',
        'expiry_date'  => '2026-06-30'
    ];

    $updateResponse = apiRequest($baseUrl . '?id=' . $createdId, 'PUT', $updatedData);

    runTest('PUT returns HTTP 200 (OK)', $updateResponse['httpCode'] === 200);
    runTest('Response contains success message', isset($updateResponse['body']['message']));
    runTest('Response contains updated product', isset($updateResponse['body']['product']));

    // Verify the update by reading the product again
    $verifyResponse = apiRequest($baseUrl . '?id=' . $createdId);
    runTest('Updated name is correct', $verifyResponse['body']['product_name'] === 'Updated Test Bread');
    runTest('Updated price is correct', floatval($verifyResponse['body']['price']) == 3.49);
    runTest('Updated quantity is correct', intval($verifyResponse['body']['quantity']) === 25);
} else {
    echo "  SKIP: Cannot update - product creation failed\n";
}

echo "\n";

// ============================================================
// STEP 5: DELETE the product (simulates confirming deletion)
// ============================================================

echo "--- Step 5: Delete Product (DELETE request) ---\n";
echo "  Simulating: User clicks Delete, confirms in dialog\n";
echo "  JavaScript sends: DELETE api/products.php?id=$createdId\n\n";

if ($createdId) {
    $deleteResponse = apiRequest($baseUrl . '?id=' . $createdId, 'DELETE');

    runTest('DELETE returns HTTP 200 (OK)', $deleteResponse['httpCode'] === 200);
    runTest('Response contains success message', isset($deleteResponse['body']['message']));

    // Verify the product is gone
    $verifyDeleted = apiRequest($baseUrl . '?id=' . $createdId);
    runTest('Deleted product returns HTTP 404 (Not Found)', $verifyDeleted['httpCode'] === 404);
} else {
    echo "  SKIP: Cannot delete - product creation failed\n";
}

echo "\n";

// ============================================================
// STEP 6: ERROR HANDLING (simulates invalid inputs)
// ============================================================

echo "--- Step 6: Error Handling Tests ---\n";
echo "  Testing API responses to invalid inputs\n\n";

// Test: Create with empty required fields
$invalidProduct = [
    'product_name' => '',
    'category'     => '',
    'price'        => -5,
    'quantity'     => -1
];
$errorResponse = apiRequest($baseUrl, 'POST', $invalidProduct);
runTest('Invalid POST returns HTTP 400 (Bad Request)', $errorResponse['httpCode'] === 400);
runTest('Error response contains error details', isset($errorResponse['body']['error']));

// Test: Read a non-existent product
$notFoundResponse = apiRequest($baseUrl . '?id=99999');
runTest('Non-existent product returns HTTP 404', $notFoundResponse['httpCode'] === 404);

// Test: Delete a non-existent product
$deleteNotFound = apiRequest($baseUrl . '?id=99999', 'DELETE');
runTest('Delete non-existent product returns HTTP 404', $deleteNotFound['httpCode'] === 404);

// Test: Update a non-existent product
$updateNotFound = apiRequest($baseUrl . '?id=99999', 'PUT', [
    'product_name' => 'Ghost Product',
    'category'     => 'Grocery',
    'price'        => 1.00,
    'quantity'     => 1
]);
runTest('Update non-existent product returns HTTP 404', $updateNotFound['httpCode'] === 404);

echo "\n";

// ============================================================
// RESULTS SUMMARY
// ============================================================

echo "========================================\n";
echo "INTEGRATION TEST RESULTS\n";
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
