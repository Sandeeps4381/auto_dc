<?php
include 'db.php';
// SQL query to fetch data
$sql = "SELECT name AS test_name, num_of_pass_test_case AS pass_count, num_of_fail_test_case AS fail_count FROM testsuites";

$result = $conn->query($sql);


$data = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    echo "0 results";
}
// Assuming you have a database connection $conn


$conn->close();

// Return data as JSON
echo json_encode($data);
?>
