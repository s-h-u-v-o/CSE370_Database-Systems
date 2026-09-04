<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "club_collab";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
  die(json_encode(["error" => "Connection failed: " . mysqli_connect_error()]));
}
?>