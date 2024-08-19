<?php
// Database connection
include 'db.php';

// Function to shorten URLs
function shortenUrl($url, $maxLength = 0) {
    $parsedUrl = parse_url($url);
    $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
    $path = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : ''; // Trim slashes from the path
    $shortenedUrl = $host;

    if (!empty($path)) {
        $remainingLength = $maxLength - strlen($host) - 3; // 3 for the ellipsis
        if ($remainingLength > 0) {
            if (strlen($path) > $remainingLength) {
                $shortenedPath = substr($path, 0, $remainingLength) . '...';
            } else {
                $shortenedPath = $path;
            }
            $shortenedUrl .= '/' . $shortenedPath;
        }
    }

    return $shortenedUrl;
}

// Handle AJAX request
if (isset($_GET['ajax'])) {
    // Pagination settings
    $limit = isset($_GET['entries']) ? intval($_GET['entries']) : 10; // Number of records per page
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    
    // Sanitize search input
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

    // Adjust SQL query based on whether "All" is selected
    $whereClause = " WHERE (urls.url_1 LIKE '%$search%' OR urls.url_2 LIKE '%$search%')";

    // Status filter integration
    if ($statusFilter === 'Passed') {
        $whereClause .= " AND testcases.status = 0";  // 0 is assumed to be Passed
    } elseif ($statusFilter === 'Failed') {
        $whereClause .= " AND testcases.status = 1";  // 1 is assumed to be Failed
    }

    if ($limit === -1) {
        $testSql = "
            SELECT urls.url_1 AS production_link, 
                   urls.url_2 AS uat_link, 
                   testcases.status, 
                   testcases.time_taken, 
                   testcases.error_message,
                   comparisonresults.image_path_1,
                   comparisonresults.image_path_2
            FROM urls
            JOIN testcases ON urls.case_id = testcases.case_id
            LEFT JOIN comparisonresults ON urls.url_id = comparisonresults.url_id
            $whereClause
        ";
    } else {
        $testSql = "
            SELECT urls.url_1 AS production_link, 
                   urls.url_2 AS uat_link, 
                   testcases.status, 
                   testcases.time_taken, 
                   testcases.error_message,
                   comparisonresults.image_path_1,
                   comparisonresults.image_path_2
            FROM urls
            JOIN testcases ON urls.case_id = testcases.case_id
            LEFT JOIN comparisonresults ON urls.url_id = comparisonresults.url_id
            $whereClause
            LIMIT $offset, $limit";
    }

    $testResult = $conn->query($testSql);

    // Fetch total count for pagination controls
    $totalSql = "SELECT COUNT(*) as total 
    FROM urls
    JOIN testcases ON urls.case_id = testcases.case_id
    $whereClause";

    $totalResult = $conn->query($totalSql);
    $totalCountenter = $totalResult->fetch_assoc()['total'];
    $totalPages = ($limit === -1) ? 1 : ceil($totalCountenter / $limit);

    // Generate the HTML for table rows
    $html = '';
    if ($testResult->num_rows > 0) {
        while ($row = $testResult->fetch_assoc()) {
            $shortenedProductionLink = shortenUrl($row['production_link']);
            $shortenedUatLink = shortenUrl($row['uat_link']);
            
            $html .= '<tr>
                        <td>
                            <a href="' . htmlspecialchars($row['production_link']) . '" target="_blank" class="image-link" data-image="' . htmlspecialchars($row['image_path_1']) . '">
                                ' . htmlspecialchars($shortenedProductionLink) . '
                            </a>
                        </td>
                        <td>
                            <a href="' . htmlspecialchars($row['uat_link']) . '" target="_blank" class="image-link" data-image="' . htmlspecialchars($row['image_path_2']) . '">
                                ' . htmlspecialchars($shortenedUatLink) . '
                            </a>
                        </td>
                        <td>' . ($row['status'] == 0 ? 'Passed' : 'Failed') . '</td>
                        <td>' . htmlspecialchars($row['time_taken']) . '</td>
                        <td>' . htmlspecialchars($row['error_message']) . '</td>
                        <td>
                            <button class="download-button-row" 
                                style="background-color: #007bff; height: 30px; width: 100%; display: flex; align-items: center; justify-content: center; border: none; color: white; border-radius: 4px; padding: 0 12px;"
                                data-production-image="' . htmlspecialchars($row['image_path_1']) . '" 
                                data-uat-image="' . htmlspecialchars($row['image_path_2']) . '">
                                <i class="fas fa-download" style="margin-right: 8px;"></i>Download
                            </button>
                        </td>
                    </tr>';
        }
    } else {
        $html .= '<tr>
                    <td colspan="6" class="no-records">No records found.</td>
                </tr>';
    }

    // Output for AJAX request
    echo json_encode([
        'html' => $html,
        'pagination' => [
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'totalCountenter' => $totalCountenter,
            'startRecord' => $offset + 1,
            'endRecord' => min($offset + $limit, $totalCountenter),
        ],
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* Additional styles for the screenshots content */

       
        
        .pagination {
            list-style-type: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        .pagination li {
            margin: 0 5px;
        }
        .pagination a {
            margin: 0 5px;
    padding: 5px 10px;
    text-decoration: none;
    color: #000;
    border-radius: 4px;
    transition: background-color 0.3s, color 0.3s;
        }
        .pagination a:hover {
            background-color: #000;
    color: #fff;
        }
        .pagination .active a {
            background-color: #a4a9ad; /* Blue background for active page */
    color: rgb(4, 2, 2); /* White text color for active page */
    border-color: #000000; /* Blue border for active page */
    font-weight: bold;
        }
        .pagination .disabled a {
            color: #ddd;
            border-color: #ddd;
            cursor: not-allowed;
        }

    </style>
</head>
<body>
<div id="testMarticsContent" style="display: block;">
    <div class="suites_background">
        <div class="show-search">
            <div class="left-section">
                <label for="entries">Show</label>
                <select id="entries" onchange="changeEntries()">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="100">100</option>
                    <option value="All">All</option>
                </select>
                <label for="entries">entries</label>
            </div>
            <div class="center-section">
                <img src="/AUTO_Dc/img/logo-dark.png" alt="Logo" class="center-logo"> <!-- Center logo -->
            </div>
            <div class="right-section">
                <p>Search: </p> 
                <input type="text" id="search" placeholder="" oninput="searchTable()">
            </div>
        </div>
        <div class="table_class">
            <table class="table row-border tablecard" id="testTable">
                <thead>
                    <tr class="table-header">
                        <th>Production Link</th>
                        <th>UAT Link</th>
                        <th>
    <label for="status-filter">Status</label>
    <select id="status-filter">
        <option value="">All</option>
        <option value="Passed">&#9650; Pass</option>  <!-- Up Arrow for Pass -->
        <option value="Failed">&#9660; Fail</option>  <!-- Down Arrow for Fail -->
    </select>
</th>

                        <th>Time(s)</th>
                        <th>Error Message</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be populated here by JavaScript -->
                </tbody>
            </table>

            <!-- Pagination and Entries Info -->
            
        </div>
        <div class="cs">
                <div class="entries-info">
                    Showing <span id="startRecord">1</span> to <span id="endRecord">10</span> of <span id="totalCountenter">0</span> entries
                    <div class="pagination-right" >
                <!-- Pagination controls will be loaded here -->
                <ul class="pagination">
                   
                </ul>
            </div>
                </div>
                
                
            </div>
    </div>
</div>
<script>  
$(document).ready(function() {
    // Functionality for opening the modal
    // (Implement modal open functionality here if needed)

    // Functionality for downloading both images
    $(document).on('click', '.download-button-row', function() {
        var productionImage = $(this).data('production-image');
        var uatImage = $(this).data('uat-image');

        // Generate filenames based on the URLs
        var productionFilename = productionImage.substring(productionImage.lastIndexOf('/') + 1);
        var uatFilename = uatImage.substring(uatImage.lastIndexOf('/') + 1);

        // Create and click link for production image
        var productionLink = document.createElement('a');
        productionLink.href = productionImage;
        productionLink.download = productionFilename; // Use the URL portion as the filename
        productionLink.style.display = 'none'; // Hide the link
        document.body.appendChild(productionLink);
        productionLink.click();
        document.body.removeChild(productionLink);

        // Create and click link for UAT image
        var uatLink = document.createElement('a');
        uatLink.href = uatImage;
        uatLink.download = uatFilename; // Use the URL portion as the filename
        uatLink.style.display = 'none'; // Hide the link
        document.body.appendChild(uatLink);
        uatLink.click();
        document.body.removeChild(uatLink);
    });

    // Functionality for pagination using AJAX
  
    function loadPage(page, entries, search, status) {
    $.ajax({
        url: 'p.php',
        type: 'GET',
        dataType: 'json',
        data: {
            page: page,
            entries: entries,
            search: search,
            status: status, // Add status filter here
            ajax: true
        },
        success: function(response) {
            $('#testTable tbody').html(response.html);

                // Update pagination
                var totalPages = response.pagination.totalPages;
                var currentPage = response.pagination.currentPage;
                var totalCountenter = response.pagination.totalCountenter;
                var startRecord = response.pagination.startRecord;
                var endRecord = response.pagination.endRecord;

                // Update entries info
                $('#startRecord').text(startRecord);
                $('#endRecord').text(endRecord);
                $('#totalCountenter').text(totalCountenter);

                var paginationHtml = '';

                // Previous button
                paginationHtml += '<li class="' + (currentPage <= 1 ? 'disabled' : '') + '"><a href="javascript:void(0);" data-page="' + (currentPage - 1) + '">Previous</a></li>';

                // Define the range of pages to display
                var startPage = Math.max(1, currentPage - 2);
                var endPage = Math.min(totalPages, currentPage + 2);

                if (startPage > 1) {
                    paginationHtml += '<li><a href="javascript:void(0);" data-page="1">1</a></li>';
                    if (startPage > 2) paginationHtml += '<li>...</li>';
                }

                for (var i = startPage; i <= endPage; i++) {
                    var activeClass = i === currentPage ? 'active' : '';
                    paginationHtml += '<li class="' + activeClass + '"><a href="javascript:void(0);" data-page="' + i + '">' + i + '</a></li>';
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) paginationHtml += '<li>...</li>';
                    paginationHtml += '<li><a href="javascript:void(0);" data-page="' + totalPages + '">' + totalPages + '</a></li>';
                }

                // Next button
                paginationHtml += '<li class="' + (currentPage >= totalPages ? 'disabled' : '') + '"><a href="javascript:void(0);" data-page="' + (currentPage + 1) + '">Next</a></li>';

                $('.pagination').html(paginationHtml);
            }
        });
    }

    // Function to handle search input
    window.searchTable = function() {
        var search = $('#search').val();
        var entries = $('#entries').val();
        var status = $('#status-filter').val();
        loadPage(1, entries, search ,status); // Load first page with search results
    }

    // Function to handle entries per page change
    window.changeEntries = function() {
        var entries = $('#entries').val();
        var search = $('#search').val();
        var status = $('#status-filter').val();
        if (entries === 'All') {
        entries = -1;
    }
        loadPage(1, entries, search,status); // Load first page with updated entries per page
    }

    // Handle pagination click
    $(document).on('click', '.pagination a', function() {
        var page = $(this).data('page');
        var entries = $('#entries').val();
        var search = $('#search').val();
        var status = $('#status-filter').val();
        loadPage(page, entries, search ,status);
    });
    $('#status-filter').on('change', function() {
    var search = $('#search').val();
    var entries = $('#entries').val();
    var status = $(this).val(); // Get the selected status filter
    loadPage(1, entries, search, status); // Load first page with status filter
});
    // Initial load
    loadPage(1, $('#entries').val(), $('#search').val(),$('#status-filter').val());
});







</script>
</body>
</html>