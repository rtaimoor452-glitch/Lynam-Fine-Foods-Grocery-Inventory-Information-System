<?php
/**
 * Database Configuration
 * 
 * This file creates a PDO connection to the SQLite database.
 * The database file is stored in the db/ directory.
 * If the database or the products table does not exist,
 * they will be created automatically.
 * 
 * PDO (PHP Data Objects) is used because:
 * - It provides a consistent interface for database access
 * - It supports prepared statements to prevent SQL injection
 * - It works with SQLite without any extra configuration
 */

/**
 * Get a PDO database connection to the SQLite database
 * 
 * This function:
 * 1. Builds the path to the SQLite database file
 * 2. Creates a new PDO connection
 * 3. Sets error mode to throw exceptions
 * 4. Sets the default fetch mode to associative arrays
 * 5. Creates the products table if it does not exist
 * 
 * @return PDO The database connection object
 */
function getDatabase() {
    // Build the path to the SQLite database file
    // __DIR__ is the directory where this file is located (config/)
    // We go up one level (..) to reach the project root, then into db/
    $dbPath = __DIR__ . '/../db/products.db';

    try {
        // Create a new PDO connection to the SQLite database
        // If the .db file does not exist, SQLite will create it
        $pdo = new PDO('sqlite:' . $dbPath);

        // Tell PDO to throw exceptions when a database error occurs
        // This makes it easier to catch and handle errors
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Tell PDO to return query results as associative arrays
        // This means we can access columns by name, e.g. $row['product_name']
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create the products table if it does not already exist
        // This runs every time a connection is made, but the IF NOT EXISTS
        // clause means it only creates the table on the very first run
        $pdo->exec("
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

        // Return the PDO connection so other files can use it
        return $pdo;

    } catch (PDOException $e) {
        // If anything goes wrong, send a 500 error response
        http_response_code(500);
        echo json_encode([
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}
