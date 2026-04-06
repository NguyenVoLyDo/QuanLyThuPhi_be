<?php
require_once 'c:/xampp/htdocs/QuanLyThuPhi_be/config/database.php';
$db = new Database();
$conn = $db->connect();
$stmt = $conn->prepare("SELECT * FROM student_debts WHERE student_id = 17");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
?>
