<?php
// File kiểm tra kết nối DB trên Railway
// Truy cập: quanlythuphibe-production.up.railway.app/db_test.php

require_once __DIR__ . '/config/database.php';

echo "<h1>Kiểm tra kết nối Database Railway</h1>";

try {
    $db = new Database();
    $conn = $db->connect();
    
    if ($conn) {
        echo "✅ Kết nối thành công tới Database!<br>";
        
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Số lượng bảng tìm thấy: " . count($tables) . "<br>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "❌ Kết nối thất bại (null).<br>";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

echo "<hr><p>Cấu hình hiện tại:</p>";
echo "Host: " . (getenv('MYSQLHOST') ?: 'NULL') . "<br>";
echo "User: " . (getenv('MYSQLUSER') ?: 'NULL') . "<br>";
echo "Port: " . (getenv('MYSQLPORT') ?: 'NULL') . "<br>";
echo "DB: " . (getenv('MYSQLDATABASE') ?: 'NULL') . "<br>";
