<?php
// Database connection
$servername = "localhost:3308";
$username = "root";
$password = "root";
$dbname = "elearning_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('<div class="alert alert-danger">Connection failed: ' . $conn->connect_error . '</div>');
}

// Get all tables in the database
$tablesQuery = "SHOW TABLES";
$tablesResult = $conn->query($tablesQuery);

if ($tablesResult->num_rows > 0) {
    // Create a table of contents
    echo '<div class="card mb-4">';
    echo '<div class="card-header">Table of Contents</div>';
    echo '<div class="card-body">';
    echo '<ul class="list-group" id="table-list">';
    
    $tableNames = [];
    while ($tableRow = $tablesResult->fetch_row()) {
        $tableName = $tableRow[0];
        $tableNames[] = $tableName;
        echo '<li class="list-group-item"><a href="#' . $tableName . '">' . $tableName . '</a></li>';
    }
    
    echo '</ul>';
    echo '</div>';
    echo '</div>';

    // Display each table
    foreach ($tableNames as $tableName) {
        echo '<div class="table-section" id="section-' . $tableName . '">';
        echo '<h2 class="table-name" id="' . $tableName . '">' . $tableName . '</h2>';
        
        // Get column information
        $columnsQuery = "SHOW COLUMNS FROM `$tableName`";
        $columnsResult = $conn->query($columnsQuery);
        
        if ($columnsResult->num_rows > 0) {
            $columns = [];
            while ($columnRow = $columnsResult->fetch_assoc()) {
                $columns[] = $columnRow['Field'];
            }
            
            // Get data from table
            $dataQuery = "SELECT * FROM `$tableName` LIMIT 1000"; // Limit to prevent performance issues
            $dataResult = $conn->query($dataQuery);
            
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-bordered table-hover">';
            
            // Table header
            echo '<thead class="table-dark"><tr>';
            foreach ($columns as $column) {
                echo '<th>' . htmlspecialchars($column) . '</th>';
            }
            echo '</tr></thead>';
            
            // Table body
            echo '<tbody>';
            if ($dataResult->num_rows > 0) {
                while ($dataRow = $dataResult->fetch_assoc()) {
                    echo '<tr>';
                    foreach ($columns as $column) {
                        echo '<td>';
                        // Handle JSON data for better display
                        if (isJson($dataRow[$column])) {
                            echo '<pre class="mb-0" style="max-height: 200px; overflow-y: auto;">';
                            echo json_encode(json_decode($dataRow[$column]), JSON_PRETTY_PRINT);
                            echo '</pre>';
                        } else {
                            // Truncate long text for better display
                            if (is_string($dataRow[$column]) && strlen($dataRow[$column]) > 200) {
                                echo '<div style="max-height: 200px; overflow-y: auto;">';
                                echo htmlspecialchars(substr($dataRow[$column], 0, 200)) . '...';
                                echo '</div>';
                            } else {
                                echo htmlspecialchars($dataRow[$column] ?? 'NULL');
                            }
                        }
                        echo '</td>';
                    }
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="' . count($columns) . '" class="text-center">No data</td></tr>';
            }
            echo '</tbody>';
            
            // Add table footer with row count
            echo '<tfoot class="table-light"><tr>';
            echo '<td colspan="' . count($columns) . '">';
            
            // Get row count
            $countQuery = "SELECT COUNT(*) AS count FROM `$tableName`";
            $countResult = $conn->query($countQuery);
            $countRow = $countResult->fetch_assoc();
            echo 'Total rows: ' . $countRow['count'];
            
            echo '</td></tr></tfoot>';
            
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">No columns found in table ' . $tableName . '</div>';
        }
        echo '</div>'; // Close table-section
    }
} else {
    echo '<div class="alert alert-info">No tables found in database</div>';
}

// Function to check if a string is valid JSON
function isJson($string) {
    if (!is_string($string)) return false;
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

// Close connection
$conn->close();
?> 