<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Tables Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            margin-bottom: 2rem;
        }
        .table-name {
            background-color: #f8f9fa;
            padding: 10px;
            margin-top: 20px;
            border-radius: 5px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        #back-to-top {
            position: fixed;
            bottom: 25px;
            right: 25px;
            display: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            text-align: center;
            font-size: 20px;
            z-index: 1500;
            opacity: 0.7;
            transition: opacity 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            line-height: 45px;
        }
        #back-to-top:hover {
            opacity: 1;
            cursor: pointer;
        }
        #refresh-btn {
            position: fixed;
            bottom: 25px;
            left: 25px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #28a745;
            color: white;
            text-align: center;
            font-size: 20px;
            z-index: 1500;
            opacity: 0.7;
            transition: opacity 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            line-height: 45px;
            border: none;
        }
        #refresh-btn:hover {
            opacity: 1;
            cursor: pointer;
        }
        .refresh-spinner {
            display: none;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        .spinner-border {
            width: 3rem; 
            height: 3rem;
        }
        #refresh-interval-container {
            position: fixed;
            bottom: 85px;
            left: 25px;
            z-index: 1500;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            display: none;
        }
        #last-refresh {
            position: fixed;
            bottom: 85px;
            right: 25px;
            z-index: 1500;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="loading-overlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="container-fluid py-4" id="main-content">
        <h1 class="mb-4">Database Tables Viewer</h1>
        <div class="alert alert-warning">
            <strong>Warning:</strong> This page displays all database tables and their contents. 
            Please delete this file after your project is complete for security reasons.
        </div>
        
        <div class="d-flex justify-content-between mb-3">
            <div class="input-group">
                <input type="text" class="form-control" id="search-tables" placeholder="Search tables...">
                <button class="btn btn-outline-secondary" type="button" id="toggle-autorefresh">
                    <i class="fas fa-sync-alt"></i> Auto Refresh: OFF
                </button>
            </div>
            
            <button class="btn btn-danger" id="recreate-db-btn" data-bs-toggle="modal" data-bs-target="#recreateDbModal">
                <i class="fas fa-database"></i> Recreate Database
            </button>
        </div>
        
        <div id="tables-container">
            <?php
            // Initial data load
            initialDataLoad();
            
            function initialDataLoad() {
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

                // Close connection
                $conn->close();
            }
            
            // Function to check if a string is valid JSON
            function isJson($string) {
                if (!is_string($string)) return false;
                json_decode($string);
                return (json_last_error() == JSON_ERROR_NONE);
            }
            ?>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="d-flex justify-content-between">
            <a href="#" class="btn btn-primary">Back to Top</a>
            <button class="btn btn-danger" onclick="if(confirm('Are you sure you want to close this page?')) window.close();">Close</button>
        </div>
    </div>

    <!-- Floating Back to Top Button -->
    <button id="back-to-top" title="Back to Top">↑</button>
    
    <!-- Floating Refresh Button -->
    <button id="refresh-btn" title="Refresh Data">
        <i class="fas fa-sync-alt"></i>
        <span class="refresh-spinner">⟳</span>
    </button>
    
    <!-- Refresh Interval Settings -->
    <div id="refresh-interval-container">
        <div class="input-group input-group-sm">
            <span class="input-group-text">Refresh every</span>
            <select class="form-select" id="refresh-interval">
                <option value="5">5 seconds</option>
                <option value="10" selected>10 seconds</option>
                <option value="30">30 seconds</option>
                <option value="60">1 minute</option>
                <option value="300">5 minutes</option>
            </select>
        </div>
    </div>
    
    <!-- Last Refresh Time -->
    <div id="last-refresh">
        Last refreshed: <span id="last-refresh-time">Just now</span>
    </div>

    <!-- Recreate Database Modal -->
    <div class="modal fade" id="recreateDbModal" tabindex="-1" aria-labelledby="recreateDbModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="recreateDbModalLabel"><i class="fas fa-exclamation-triangle"></i> Recreate Database</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will completely recreate the database. All existing data will be lost!
                    </div>
                    <p>Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-recreate">Yes, Recreate Database</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Modal -->
    <div class="modal fade" id="processingModal" tabindex="-1" aria-labelledby="processingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="processingModalLabel">Processing</h5>
                </div>
                <div class="modal-body text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Recreating database, please wait...</p>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/lib/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Back to top button functionality
        var backToTopButton = document.getElementById("back-to-top");
        
        // When the user scrolls down 300px from the top of the document, show the button
        window.onscroll = function() {
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                backToTopButton.style.display = "block";
            } else {
                backToTopButton.style.display = "none";
            }
        };
        
        // When the user clicks on the button, scroll to the top of the document
        backToTopButton.addEventListener("click", function() {
            document.body.scrollTop = 0; // For Safari
            document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
        });
        
        $(document).ready(function() {
            // Table search functionality
            $("#search-tables").on("keyup", function() {
                const value = $(this).val().toLowerCase();
                $("#table-list li").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
                
                $(".table-section").filter(function() {
                    const tableName = $(this).find(".table-name").text().toLowerCase();
                    const shouldShow = tableName.indexOf(value) > -1;
                    $(this).toggle(shouldShow);
                });
            });
            
            // Refresh functionality
            let refreshInterval;
            let isAutoRefreshOn = false;
            
            // Initialize last refresh time
            updateLastRefreshTime();
            
            $("#refresh-btn").click(function() {
                refreshData();
            });
            
            // Toggle auto-refresh
            $("#toggle-autorefresh").click(function() {
                isAutoRefreshOn = !isAutoRefreshOn;
                const buttonText = isAutoRefreshOn ? "Auto Refresh: ON" : "Auto Refresh: OFF";
                $(this).html(`<i class="fas fa-sync-alt"></i> ${buttonText}`);
                
                if (isAutoRefreshOn) {
                    startAutoRefresh();
                    $("#refresh-interval-container").fadeIn();
                } else {
                    clearInterval(refreshInterval);
                    $("#refresh-interval-container").fadeOut();
                }
            });
            
            // Change refresh interval
            $("#refresh-interval").change(function() {
                if (isAutoRefreshOn) {
                    clearInterval(refreshInterval);
                    startAutoRefresh();
                }
            });
            
            function startAutoRefresh() {
                const seconds = parseInt($("#refresh-interval").val());
                refreshInterval = setInterval(refreshData, seconds * 1000);
            }
            
            function refreshData() {
                // Show loading spinner
                $(".loading-overlay").fadeIn(200);
                $(".refresh-spinner").show();
                $("#refresh-btn i").hide();
                
                // Fetch fresh data via AJAX
                $.ajax({
                    url: "get_tables_data.php",
                    type: "GET",
                    success: function(response) {
                        $("#tables-container").html(response);
                        updateLastRefreshTime();
                    },
                    error: function(xhr, status, error) {
                        console.error("Error refreshing data:", error);
                        alert("Failed to refresh data: " + error);
                    },
                    complete: function() {
                        // Hide loading spinner
                        $(".loading-overlay").fadeOut(200);
                        $(".refresh-spinner").hide();
                        $("#refresh-btn i").show();
                    }
                });
            }
            
            function updateLastRefreshTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                $("#last-refresh-time").text(timeString);
            }
            
            // Database recreation functionality
            $("#confirm-recreate").click(function() {
                // Hide the confirmation modal
                $("#recreateDbModal").modal('hide');
                
                // Show the processing modal
                $("#processingModal").modal('show');
                
                // Set initial progress
                let progress = 10;
                $(".progress-bar").css("width", progress + "%");
                
                // Simulate progress until we get the real result
                const progressInterval = setInterval(function() {
                    progress += 5;
                    if (progress <= 90) {
                        $(".progress-bar").css("width", progress + "%");
                    }
                }, 500);
                
                // Execute the create_database.php script
                $.ajax({
                    url: "create_database.php",
                    type: "GET",
                    success: function(response) {
                        // Stop the progress interval
                        clearInterval(progressInterval);
                        
                        // Set progress to 100%
                        $(".progress-bar").css("width", "100%");
                        
                        // Wait a moment and then reload the page
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function(xhr, status, error) {
                        // Stop the progress interval
                        clearInterval(progressInterval);
                        
                        // Hide the processing modal
                        $("#processingModal").modal('hide');
                        
                        // Show error alert
                        alert("Failed to recreate database: " + error);
                    }
                });
            });
        });
    </script>
</body>
</html> 