<?php
// Database connection
include 'db.php';
// SQL Query





$sql = "SELECT name AS Suit_name, current_date_and_time AS date, num_of_pass_test_case AS passed, num_of_fail_test_case AS failed FROM testsuites ORDER BY current_date_and_time ASC";



$Result = $conn->query($sql);

$data = array();

if ($Result->num_rows > 0) {
    while ($row = $Result->fetch_assoc()) {
        $data[] = $row;
    }
} 


echo json_encode($data);

$conn->close();
?>
