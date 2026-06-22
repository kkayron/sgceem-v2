<?php
$host = 'sql111.infinityfree.com';
$user = 'if0_42243179';
$pass = 'mPRkpYEduOM';
$dbname = 'if0_42243179_sgceem';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read the SQL file
$sql = file_get_contents('../sgceem_v2_clean.sql');
if(!$sql) die("Could not read SQL file");

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Database imported successfully!";
} else {
    echo "Error importing database: " . $conn->error;
}
$conn->close();
?>
