<?php
// Database connection
include 'db.php';

// SQL Query
$testSql = "
    SELECT 
        testcases.case_id,
        testcases.url_id,
        testcases.suite_id,
        testcases.name,
        CASE 
            WHEN testcases.status = 'Pass' THEN 0
            WHEN testcases.status = 'Fail' THEN 1
            ELSE testcases.status -- Keeps any other status values unchanged
        END AS status,
        testcases.time_taken,
        testcases.error_message,
        urls.url_1 AS production_link,
        urls.url_2 AS uat_link,
        testsuites.project_id,
        testsuites.name AS suite_name,
        testsuites.total_test_no_of_case,
        testsuites.num_of_pass_test_case,
        testsuites.num_of_fail_test_case,
        testsuites.total_time_to_run_test_case,
        testsuites.current_date_and_time
    FROM 
        testcases
    LEFT JOIN 
        urls ON testcases.url_id = urls.url_id
    LEFT JOIN 
        testsuites ON testcases.suite_id = testsuites.suite_id
";

// Execute the SQL query
$testResult = $conn->query($testSql);

// Check for query errors
if (!$testResult) {
    die("Query failed: " . $conn->error);
}

$data = [];

// Fetch data using correct column names
while ($row = $testResult->fetch_assoc()) {
    // Process your rows here and add them to the $data array
    $data[] = [
        'Case ID' => $row['case_id'],
        'URL ID' => $row['url_id'],
        'Suite ID' => $row['suite_id'],
        'Name' => $row['name'],
        'Status' => $row['status'], // This should be 0 or 1 from the database
        'Time Taken' => $row['time_taken'],
        'Error Message' => $row['error_message'],
        'Production Link' => $row['production_link'],
        'UAT Link' => $row['uat_link'],
        'Project ID' => $row['project_id'],
        'Suite Name' => $row['suite_name'],
        'Total Test Cases' => $row['total_test_no_of_case'],
        'Passed Test Cases' => $row['num_of_pass_test_case'],
        'Failed Test Cases' => $row['num_of_fail_test_case'],
        'Total Time to Run' => $row['total_time_to_run_test_case'],
        'Date and Time' => $row['current_date_and_time']
    ];
}

// Encode the data array as JSON and output it
echo json_encode($data);

// Close the database connection
$conn->close();
?>
