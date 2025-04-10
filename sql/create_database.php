<?php
$host = 'localhost';
$port = 3308;
$username = 'root';
$password = 'root';

try {
    // Create connection
    $conn = new mysqli($host . ':' . $port, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    echo "<h2>Database Setup Process</h2>";
    echo "<div style='font-family: Arial, sans-serif; margin: 20px;'>";
    
    // Read SQL file
    $sql = file_get_contents('database.sql');
    
    if ($sql === false) {
        throw new Exception("Error reading SQL file");
    }

    // Split SQL commands
    $commands = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each command
    foreach ($commands as $command) {
        if (!empty($command)) {
            if ($conn->query($command)) {
                echo "<p style='color: green;'>✓ Success: " . htmlspecialchars(substr($command, 0, 100)) . "...</p>";
            } else {
                throw new Exception("Error executing query: " . $conn->error . "\nQuery: " . $command);
            }
        }
    }

    echo "<p style='color: green; font-weight: bold;'>✓ Database setup completed successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
    echo "</div>";
}
?>