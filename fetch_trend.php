<?php
header('Content-Type: application/json');

// Database connection details
include 'db.php';
// SQL query to fetch data
$sql = "SELECT  current_date_and_time AS date, num_of_pass_test_case AS passed, num_of_fail_test_case AS failed FROM testsuites ORDER BY current_date_and_time ASC";

// Execute the query
$result = $conn->query($sql);

// Fetch data
$data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Return data as JSON
echo json_encode($data);

// Close the connection
$conn->close();
?>
