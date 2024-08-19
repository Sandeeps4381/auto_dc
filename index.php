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
         SELECT testcases.*,
       urls.url_1 AS production_link,
       urls.url_2 AS uat_link,
       comparisonresults.image_path_1,
       comparisonresults.image_path_2
FROM testcases
JOIN urls ON testcases.url_id = urls.url_id
LEFT JOIN comparisonresults ON urls.url_id = comparisonresults.url_id
$whereClause

        ";
    } else {
        $testSql = "
           SELECT testcases.*,
       urls.url_1 AS production_link,
       urls.url_2 AS uat_link,
       comparisonresults.image_path_1,
       comparisonresults.image_path_2
FROM testcases
JOIN urls ON testcases.url_id = urls.url_id
LEFT JOIN comparisonresults ON urls.url_id = comparisonresults.url_id
$whereClause
LIMIT $offset, $limit
";
    }

    $testResult = $conn->query($testSql);

    // Fetch total count for pagination controls
    $totalSql = "SELECT COUNT(*) as total 
    FROM testcases
   JOIN urls ON testcases.url_id = urls.url_id
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
    <title>DC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome CDN -->

    <link rel="stylesheet" href="styles.css">
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
    <div class="sidebar">
        <div class="logo">
            <img src="/AUTO_DC/img/vTech lcompany_logo white 1 1.png" alt="Logo"> <!-- Replace with your logo -->
        </div>
        <div class="links">
            <a href="#" id="dashboardLink" class="link active"><i class="fas fa-home"></i> Dashboard</a>
            <!-- Changed to home icon -->
            <a href="#" id="suitesLink" class="link"><i class="fa fa-briefcase"></i> Suites</a>
            <a href="#" id="testMarticsLink" class="link"><i class="fas fa-list"></i> Test Martics</a>
           
        </div>
    </div>

    <div class="content">
        <div class="topbar">
            <span class="menu-toggle" onclick="toggleMenu()">&#9776;</span>
            <div class="logo">
                <img src="/AUTO_DC/img/vTech lcompany_logo white 1 1.png" alt="Logo"> <!-- Replace with your logo -->
            </div>
        </div>






        <!-- Main content goes here -->
        <div id="dashboardContent" class="container">
        <div class="ss">
            <div class="card">
                <!-- Automation Report code -->
                <div class="report-header">
                    <h2>Automation Report</h2>
                    <p id="date_take">0</p> <!-- Dynamic Date -->
                    <div class="test-cases-container">
                        <h3>TEST CASES</h3>
                        <h4 class="test-cases-number" id="totalCount">0</h4>
                    </div>
                    <div class="circle-container">
                        <canvas id="progressCircle" width="160" height="160"></canvas>
                    </div>
                    <div class="results-container">
                        <div class="result passed">
                            <p>Passed <span id="passedCount">0</span></p>
                        </div>
                        <div class="result failed">
                            <p>Failed <span id="failedCount">0</span></p>
                        </div>
                    </div>
                    <div class="time-taken">
                        <i class="fas fa-clock"></i>
                        <span id="timeTaken">Time taken 00:00 Hrs</span>
                    </div>
                </div>
            </div>

            <!-- Trend Card code -->
            <div class="trend-card">
                <div class="trend-header">
                    <h2>Trends</h2>
                    <button onclick="downloadExcel()">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <div class="card-content">
                    <canvas id="myChart" width="700" ></canvas>
                </div>
            </div>
            
           </div>
            <!-- Test Suites code -->
            <div class="combined-card">
                <h2>Test Suites:
                    <?php
                    // Database connection settings
                    include 'db.php';

                    // Query to count total number of suite_id
                    $sql = "SELECT COUNT(suite_id) AS total_suites FROM testsuites";
                    $resultc = $conn->query($sql);

                    // Fetch the result
                    $row = $resultc->fetch_assoc();
                    $total_suites = $row['total_suites'];

                    // Output the result
                    echo $total_suites;

                    // Close connection
                    
                    ?>
                </h2>
                <div class="combined-card-content">
                    <div class="chart-container">
                        <canvas id="testChart"></canvas>
                    </div>

                    <div class="legend-container"></div>
                </div>
            </div>
        </div>

        <!-- page 2 -->


        <!-- Placeholder for Test Martics content -->
        <div id="testMarticsContent" style="display: none;">
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

      

        <!-- Placeholder for Suites content -->
        <div id="suitesContent" style="display: none;">
    <div class="suites_background">
        <div class="show-search">
            <div class="left-section">
                <label for="entries_suit">Show</label>
                <select id="entries_suit" onchange="updateTable(1)">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="100">100</option>
                    <option value="All">All</option>
                </select>
                <label for="entries_suit">entries</label>
            </div>
            <div class="center-section">
                <img src="/AUTO_Dc/img/logo-dark.png" alt="Logo" class="center-logo"> <!-- Center logo -->
            </div>
            <div class="right-section">
                <p>Search: </p> 
                <input type="text" id="search_suit" placeholder="Search..." oninput="updateTable(1)">
            </div>
        </div>

        <div class="table_class">
            <table class="table row-border tablecard" id="suitesTable">
                <thead>
                    <tr class="table-header">
                        <th>Suites</th>
                        <th>Pass</th>
                        <th>Fail</th>
                    </tr>
                </thead>
                <tbody id="suitesTableBody">
                    <!-- Data will be loaded here via AJAX -->
                </tbody>
            </table>
        </div>
        <div class="cs">
            <div class="pagination-left" id="paginationInfo_suit">
                <!-- Pagination info will be loaded here -->
            </div>
            <div class="pagination-right" id="paginationControls_suit">
                <!-- Pagination controls will be loaded here -->
            </div>
        </div>
    </div>
</div>



    </div>



  
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>



    <script src="script.js"></script>
    <script>                




function toggleMenu() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}



document.getElementById('status-filter').addEventListener('change', function() {
    const filterValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('tr.table-row');

    rows.forEach(row => {
        const statusCell = row.querySelector('td:nth-child(3)'); // Adjust index if 'Status' column position changes
        const statusText = statusCell.textContent.trim().toLowerCase();

        if (filterValue === '' || statusText === filterValue) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});













function updateTable(page) {
    let entries = document.getElementById('entries_suit').value;
    const search = document.getElementById('search_suit').value;

    // Check if the "All" option is selected
    if (entries === 'All') {
        entries = -1;  // Send a special value to represent "All"
    }

    // Make an AJAX request to fetch the data
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetch.php?entries=${entries}&search=${search}&page=${page}`, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            const data = response.data;
            const total = response.total;

            // Update the table body
            const tableBody = document.getElementById('suitesTableBody');
            tableBody.innerHTML = '';

            if (data.length > 0) {
                data.forEach(function(row) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${row.suite_name}</td>
                        <td>${row.pass}</td>
                        <td>${row.fail}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="3">No data available</td></tr>';
            }

            // Update pagination info
            const from = (page - 1) * entries + 1;
            const to = entries === -1 ? total : Math.min(page * entries, total);
            document.getElementById('paginationInfo_suit').innerText = `Showing ${from} to ${to} of ${total} entries`;

            // Update pagination controls
            const paginationControls = document.getElementById('paginationControls_suit');
            paginationControls.innerHTML = '';

            const totalPages = entries === -1 ? 1 : Math.ceil(total / entries); // If showing all data, only 1 page
            if (totalPages > 1) {
                if (page > 1) {
                    const prevLink = document.createElement('a');
                    prevLink.href = '#';
                    prevLink.innerText = 'Previous';
                    prevLink.onclick = function(e) {
                        e.preventDefault();
                        updateTable(page - 1);
                    };
                    paginationControls.appendChild(prevLink);
                }

                for (let i = 1; i <= totalPages; i++) {
                    const pageLink = document.createElement('a');
                    pageLink.href = '#';
                    pageLink.innerText = i;
                    pageLink.onclick = function(e) {
                        e.preventDefault();
                        updateTable(i);
                    };
                    if (i === page) {
                        pageLink.classList.add('active');
                    }
                    paginationControls.appendChild(pageLink);
                }

                if (page < totalPages) {
                    const nextLink = document.createElement('a');
                    nextLink.href = '#';
                    nextLink.innerText = 'Next';
                    nextLink.onclick = function(e) {
                        e.preventDefault();
                        updateTable(page + 1);
                    };
                    paginationControls.appendChild(nextLink);
                }
            }
        }
    };
    xhr.send();
}

// Call updateTable function on page load to display the initial data
updateTable(1);















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
        url: 'index.php',
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
















function downloadExcel() {
    // Fetch data from both sources concurrently
    Promise.all([
        fetch('suit_fetch.php').then(response => response.json()),
        fetch('test.php').then(response => response.json())
    ])
    .then(([trendData, testData]) => {
        // Check if both responses contain valid data
        if (
            trendData && Array.isArray(trendData) && trendData.length > 0 &&
            testData && Array.isArray(testData) && testData.length > 0
        ) {
            // Process the test data to convert status from 0/1 to Pass/Fail
            const processedTestData = testData.map(item => {
                return {
                    ...item,
                    Status: item.Status == 0 ? 'Pass' : 'Fail'
                };
            });

            // Create a new workbook
            const workbook = XLSX.utils.book_new();

            // Convert the trend data to a worksheet and add it to the workbook
            const trendWorksheet = XLSX.utils.json_to_sheet(trendData);
            XLSX.utils.book_append_sheet(workbook, trendWorksheet, "Trends");

            // Convert the processed test data to a worksheet and add it to the workbook
            const testWorksheet = XLSX.utils.json_to_sheet(processedTestData);
            XLSX.utils.book_append_sheet(workbook, testWorksheet, "Tests Martics");

            // Write the workbook to an Excel file
            XLSX.writeFile(workbook, "Automation_data.xlsx");
        } else {
            console.error('No data available to download from one or both sources');
        }
    })
    .catch(error => console.error('Error fetching trend data:', error));
}





        document.addEventListener("DOMContentLoaded", function () {
            // Get the current page's URL
            const currentPage = window.location.pathname;

            // Get the links
            const dashboardLink = document.getElementById('dashboardLink');
            const suitesLink = document.getElementById('suitesLink');
            const testMarticsLink = document.getElementById('testMarticsLink');
       
            // const ProjectLink = document.getElementById('ProjectLink');


            // Add click event listeners to the links
            dashboardLink.addEventListener('click', function (event) {
                event.preventDefault();
                showContent('dashboard');
                setActiveLink(dashboardLink);
            });

            suitesLink.addEventListener('click', function (event) {
                event.preventDefault();
                showContent('suites');
                setActiveLink(suitesLink);
            });

            testMarticsLink.addEventListener('click', function (event) {
                event.preventDefault();
                showContent('testMartics');
                setActiveLink(testMarticsLink);
            });
            ;


            // Function to show the appropriate content and hide others
            function showContent(contentType) {
                dashboardContent.style.display = 'none';
                testMarticsContent.style.display = 'none';
                suitesContent.style.display = 'none';

               

                if (contentType === 'dashboard') {
                    dashboardContent.style.display = 'block';
                } else if (contentType === 'testMartics') {
                    testMarticsContent.style.display = 'block';
                } else if (contentType === 'suites') {
                    suitesContent.style.display = 'block';
                
                    // } else if (contentType === 'Project') {
                    //     ProjectContent.style.display = 'block';
                }
            }

            // Function to set the active link
            function setActiveLink(activeLink) {
                // Remove active class from all links
                dashboardLink.classList.remove('active');
                suitesLink.classList.remove('active');
                testMarticsLink.classList.remove('active');
             

                // Add active class to the clicked link
                activeLink.classList.add('active');
            }
        });





        document.addEventListener("DOMContentLoaded", function () {
            // Function to draw progress circle
            function drawProgressCircle(passedPercentage, failedPercentage) {
                const canvas = document.getElementById('progressCircle');
                const ctx = canvas.getContext('2d');
                const radius = canvas.width / 2;
                const lineWidth = 15; // Border width for the passed portion
                const failedLineWidth = 10; // Thinner border width for the failed portion

                ctx.clearRect(0, 0, canvas.width, canvas.height);

                // Draw background circle
                ctx.strokeStyle = '#f3f3f3';
                ctx.lineWidth = lineWidth;
                ctx.beginPath();
                ctx.arc(radius, radius, radius - lineWidth / 2, 0, Math.PI * 2);
                ctx.stroke();

                // Draw progress arc (passed portion) with thicker border
                ctx.strokeStyle = '#00B69B'; // Green for pass
                ctx.lineWidth = lineWidth;
                ctx.beginPath();
                const startAngle = -Math.PI / 2; // Start angle (top of the circle)
                const endAngle = startAngle + (Math.PI * 2 * (passedPercentage / 100));
                ctx.arc(radius, radius, radius - lineWidth / 2, startAngle, endAngle);
                ctx.stroke();

                // Draw remaining arc (failed portion) with thinner border
                ctx.strokeStyle = '#FF6262'; // Red for fail
                ctx.lineWidth = failedLineWidth; // Thinner border for the failed portion
                ctx.beginPath();
                const failStartAngle = endAngle;
                const failEndAngle = failStartAngle + (Math.PI * 2 * (failedPercentage / 100));
                ctx.arc(radius, radius, radius - failedLineWidth / 2, failStartAngle, failEndAngle);
                ctx.stroke();

                // Draw white circle for text background
                ctx.fillStyle = '#fff';
                ctx.beginPath();
                ctx.arc(radius, radius, radius - Math.max(lineWidth, failedLineWidth) / 2 - 5, 0, Math.PI * 2); // Slightly smaller radius
                ctx.fill();

                // Draw percentage text
                ctx.fillStyle = '#00B69B'; // Green for text
                ctx.font = 'bold 24px Arial'; // Bold text
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(`${Math.round(passedPercentage)}%`, radius, radius);

                // Draw "Test Cases Passed" text
                ctx.fillStyle = '#979797'; // Grey text for label
                ctx.font = '14px Arial'; // Regular text
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('Test Cases Passed', radius, radius + 15); // Positioned below the percentage text
            }

            // Fetch data from PHP script
            fetch('fetch_test_case.php')
                .then(response => response.json())
                .then(data => {
                    const total = data.total;
                    const passed = data.passed;
                    const failed = data.failed;
                    const totalTimeTaken = data.total_time_taken;
                    const dateTake = new Date(data.Date_take); // Convert to Date object

                    // Format the date to "Dec 2nd 2024"
                    const options = { year: 'numeric', month: 'short', day: 'numeric' };
                    const formattedDate = dateTake.toLocaleDateString('en-US', options);

                    // Add ordinal suffix to the day
                    const day = dateTake.getDate();
                    const suffix = (day % 10 === 1 && day !== 11) ? 'st' :
                        (day % 10 === 2 && day !== 12) ? 'nd' :
                            (day % 10 === 3 && day !== 13) ? 'rd' : 'th';
                    const finalDate = formattedDate.replace(/\d+/, day + suffix);

                    // Update the HTML with the fetched data
                    document.getElementById('totalCount').textContent = total;
                    document.getElementById('passedCount').textContent = passed;
                    document.getElementById('failedCount').textContent = failed;
                    document.getElementById('timeTaken').textContent = `Time taken ${totalTimeTaken}`;
                    document.getElementById('date_take').textContent = finalDate; // Update formatted date

                    drawProgressCircle(data.passedPercentage, data.failedPercentage);
                })
                .catch(error => console.error('Error fetching data:', error));





            // Fetch trend data from PHP script
            fetch('fetch_trend.php')
                .then(response => response.json())
                .then(data => {
                    const dates = data.map(item => item.date);
                    const passedData = data.map(item => item.passed);
                    const failedData = data.map(item => item.failed);

                    const ctx = document.getElementById('myChart').getContext('2d');
                    const myChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: dates,
                            datasets: [
                                {
                                    label: 'Failed Data',
                                    data: failedData,
                                    borderColor: '#ff6262',
                                    tension: 0.4,
                                    pointRadius: 6,
                                    pointBackgroundColor: '#ff6262'
                                },
                                {
                                    label: 'Passed Data',
                                    data: passedData,
                                    borderColor: '#00B69B',
                                    tension: 0.4,
                                    pointRadius: 6,
                                    pointBackgroundColor: '#00B69B'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        autoSkip: true,
                                        maxTicksLimit: 5
                                    }
                                },
                                y: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        min: 0,
                                        max: 100,
                                        stepSize: 25
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error fetching trend data:', error));
        });









        document.addEventListener("DOMContentLoaded", function () {
            // Fetch data from the PHP script
            fetch('fetch_data_test.php')
                .then(response => response.json())
                .then(data => {
                    const labels = data.map(item => item.test_name);
                    const passData = data.map(item => item.pass_count);
                    const failData = data.map(item => item.fail_count);

                    // Calculate the maximum values for pass and fail
                    const maxPass = Math.max(...passData, 0); // Ensure at least 0
                    const maxFail = Math.max(...failData, 0); // Ensure at least 0
                    const maxValue = Math.max(maxPass, maxFail);

                    // Set maximum value for y-axis to maxValue + 500
                    const yAxisMax = maxValue + 500;

                    const ctx = document.getElementById('testChart').getContext('2d');
                    const chartData = {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Pass',
                                data: passData,
                                backgroundColor: '#00B69B', // Green for passed cases
                                barThickness: 15,
                                borderWidth: 1,
                                borderRadius: {
                                    topLeft: 7.5,
                                    topRight: 7.5,
                                    bottomLeft: 7.5,
                                    bottomRight: 7.5
                                },
                                barPercentage: 0.2,
                                categoryPercentage: 0.8
                            },
                            {
                                label: 'Fail',
                                data: failData,
                                backgroundColor: '#FF6262', // Red for failed cases
                                barThickness: 15,
                                borderWidth: 1,
                                borderRadius: {
                                    topLeft: 7.5,
                                    topRight: 7.5,
                                    bottomLeft: 7.5,
                                    bottomRight: 7.5
                                },
                                barPercentage: 0.2,
                                categoryPercentage: 0.8
                            }
                        ]
                    };

                    const config = {
                        type: 'bar',
                        data: chartData,
                        options: {
                            layout: {
                                padding: {
                                    bottom: 10
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,

                                    font: {
                                        size: 16
                                    },
                                    padding: {
                                        bottom: 10
                                    },
                                    position: 'left'
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    stacked: false,
                                    title: {
                                        display: true,
                                    },
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                    },
                                    beginAtZero: true,
                                    max: yAxisMax, // Set maximum value dynamically
                                    ticks: {
                                        stepSize: 100,
                                        padding: 0
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    };

                    const testChart = new Chart(ctx, config);

                    // Create the custom legend
                    const legendContainer = document.querySelector('.legend-container');
                    const legendItems = [
                        { color: '#00B69B', label: 'Passed' },
                        { color: '#FF6262', label: 'Failed' }
                    ];

                    legendItems.forEach(item => {
                        const legendItem = document.createElement('div');
                        legendItem.classList.add('legend-item');

                        const colorCircle = document.createElement('div');
                        colorCircle.classList.add('legend-color');
                        colorCircle.style.backgroundColor = item.color;

                        const label = document.createElement('span');
                        label.textContent = item.label;

                        legendItem.appendChild(colorCircle);
                        legendItem.appendChild(label);
                        legendContainer.appendChild(legendItem);
                    });

                    // Display testCount in the HTML
                    document.getElementById('suitesCount').textContent = data.reduce((total, item) => total + parseInt(item.testCount, 10), 0);
                })
                .catch(error => console.error('Error fetching data:', error));
        });


    </script>
</body>

</html>
