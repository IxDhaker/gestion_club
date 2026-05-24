<?php
$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'isetclubs';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die('Error: ' . $conn->connect_error); }

$result = $conn->query('SHOW TABLES');
if ($result->num_rows > 0) {
    while($row = $result->fetch_row()) {
        echo $row[0] . "\n";
    }
} else {
    echo 'No tables found\n';
}
$conn->close();
?>