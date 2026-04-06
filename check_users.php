<?php
require_once 'c:/xampp/htdocs/QuanLyThuPhi_be/config/database.php';
$db = new Database();
$conn = $db->connect();
$stmt = $conn->query("SELECT u.id, u.username, u.student_id FROM users u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE r.role_name = 'Student'");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
?>
