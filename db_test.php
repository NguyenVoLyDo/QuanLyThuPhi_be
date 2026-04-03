<?php
// File kiểm tra kết nối DB và Quét biến môi trường trên Railway
// Truy cập: quanlythuphibe-production.up.railway.app/db_test.php

require_once __DIR__ . '/config/database.php';

echo "<h1>Kiểm tra kết nối Database & Biến môi trường</h1>";

// 1. Quét toàn bộ biến môi trường
echo "<h3>Danh sách biến môi trường (Lọc MYSQL):</h3>";
$all_env = getenv();
echo "<ul>";
foreach ($all_env as $key => $value) {
    if (strpos($key, 'MYSQL') !== false) {
        // Ẩn nội dung nhạy cảm
        $display_value = (strpos($key, 'PASSWORD') !== false || strpos($key, 'URL') !== false) ? "********" : $value;
        echo "<li><strong>$key</strong>: $display_value</li>";
    }
}
echo "</ul>";

// 2. Thử kết nối
echo "<h3>Kết quả thử kết nối:</h3>";
try {
    $db = new Database();
    $conn = $db->connect();
    
    if ($conn) {
        echo "✅ Kết nối thành công!<br>";
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Số lượng bảng: " . count($tables);
    } else {
        echo "❌ Kết nối thất bại (null).";
    }
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}
