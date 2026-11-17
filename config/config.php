<?php
/* ==========================================================================
   SMART TOURISM MANAGEMENT SYSTEM
   Database Config (config.php)
   Plain mysqli connection (procedural, no PDO/OOP).
   Update $host / $dbname / $username / $password to match your setup.
   ========================================================================== */

$host     = "sql207.byethost7.com";
$dbname   = "b7_43160650_smart_tourism";
$username = "b7_43160650";
$password = "dtwzc7g8";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>