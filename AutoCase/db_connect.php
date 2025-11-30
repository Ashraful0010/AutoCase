<?php
/**
 * Database Connection Utility
 * Uses the user-specified function to connect to MySQL database.
 * Ensure your database 'autocase' and a user table 'users' exist.
 */
function getConnection()
{
    // Credentials provided by the user: localhost, root, (no password), autocase
    $conn = new mysqli('localhost', 'root', '', 'autocase');

    // Check connection
    if ($conn->connect_error) {
        // Output connection error and stop execution
        die("Database connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Optional: Run this once to ensure the required 'users' table exists.
// In a production environment, this should be handled by migration scripts.
$conn = getConnection();

$sql_create_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    test_case_count INT(11) DEFAULT 0
);
";

if (!$conn->query($sql_create_table)) {
    // Log an error if table creation fails but do not halt the application
    error_log("Error creating users table: " . $conn->error);
}

// Close the connection used only for table creation check
$conn->close();

?>