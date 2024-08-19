<?php
// fetch_suites.php

// Database connection
include 'db.php'; // Use your own connection script

$entries = $_GET['entries'] ?? 10;
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;

// Sanitize inputs
$entries = htmlspecialchars($entries);
$search = htmlspecialchars($search);
$page = htmlspecialchars($page);

$offset = ($page - 1) * $entries;

// If entries is -1, don't apply a limit
$limit = ($entries == -1) ? "" : "LIMIT $offset, $entries";

$whereClause = "";
if (!empty($search)) {
    $whereClause = "WHERE s.name LIKE '%$search%'";
}

$Suitsql = "
  SELECT 
    s.suite_id,
    s.project_id,
    s.name AS suite_name,
    num_of_pass_test_case AS pass,
    num_of_fail_test_case AS fail
  FROM 
    testsuites s
  LEFT JOIN 
    testcases t ON s.suite_id = t.suite_id
  $whereClause
  GROUP BY 
    s.suite_id, s.project_id, s.name
  $limit
";

$result = $conn->query($Suitsql);

// Fetch total number of records for pagination
$totalRecordsSql = "
  SELECT COUNT(DISTINCT s.suite_id) AS total
  FROM 
    testsuites s
  LEFT JOIN 
    testcases t ON s.suite_id = t.suite_id
  $whereClause
";
$totalResult = $conn->query($totalRecordsSql);
$totalRows = $totalResult->fetch_assoc()['total'];

$data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode([
    'data' => $data,
    'total' => $totalRows
]);

?>
