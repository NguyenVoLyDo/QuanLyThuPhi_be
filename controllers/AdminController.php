<?php
require_once __DIR__ . '/../config/database.php';

class AdminController {
    
    public function backupPage() {
        check_permission(['Admin']);
        if (defined('API_MODE')) {
            json_response(['success' => true, 'message' => 'Hit backup action to download SQL']);
        }
        die("Backend API - please use frontend for UI.");
    }
    
    public function backup() {
        check_permission(['Admin']);
        
        $database = new Database();
        $conn = $database->connect();
        
        // Filename
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output stream
        $out = fopen('php://output', 'w');
        
        // Get all tables
        $tables = [];
        $query = $conn->query('SHOW TABLES');
        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        // Export
        fwrite($out, "-- Database Backup\n");
        fwrite($out, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        
        foreach ($tables as $table) {
            $result = $conn->query('SELECT * FROM ' . $table);
            $num_fields = $result->columnCount();
            
            fwrite($out, "DROP TABLE IF EXISTS `" . $table . "`;\n");
            
            $row2 = $conn->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
            fwrite($out, "\n" . $row2[1] . ";\n\n");
            
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                fwrite($out, "INSERT INTO `" . $table . "` VALUES(");
                for ($j = 0; $j < $num_fields; $j++) {
                    if (isset($row[$j])) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        fwrite($out, '"' . $row[$j] . '"');
                    } else {
                        fwrite($out, 'NULL');
                    }
                    if ($j < ($num_fields - 1)) {
                        fwrite($out, ',');
                    }
                }
                fwrite($out, ");\n");
            }
            fwrite($out, "\n\n");
        }
        
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
        
        // Log
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../models/AuditLog.php';
            $auditLog = new AuditLog();
            $auditLog->log($_SESSION['user_id'], 'BACKUP_DATABASE', 'System', null, 'Downloaded database backup: ' . $filename);
        }
        
        exit;
    }
    
    public function systemSettings() {
        check_permission(['Admin']);
        
        $database = new Database();
        $conn = $database->connect();
        
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Save settings
            $academic_year = clean_input($_POST['academic_year'] ?? '2025-2026');
            $semester = clean_input($_POST['semester'] ?? 'HK1');
            $school_name = clean_input($_POST['school_name'] ?? '');
            $school_address = clean_input($_POST['school_address'] ?? '');
            
            // Save to config file or database
            // save to a JSON file
            $settings = [
                'academic_year' => $academic_year,
                'semester' => $semester,
                'school_name' => $school_name,
                'school_address' => $school_address,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $config_path = __DIR__ . '/../config/settings.json';
            file_put_contents($config_path, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            set_flash('success', 'Đã lưu cài đặt hệ thống!', 'success');
            header('Location: /QuanLyThuPhi/backend/index.php?controller=admin&action=systemSettings');
            exit();
        }
        
        // Load settings
        $config_path = __DIR__ . '/../config/settings.json';
        if (file_exists($config_path)) {
            $settings = json_decode(file_get_contents($config_path), true);
        } else {
            $settings = [
                'academic_year' => '2025-2026',
                'semester' => 'HK1',
                'school_name' => 'Trường THPT ABC',
                'school_address' => ''
            ];
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['settings' => $settings]]);
        }
        die("Backend API - please use frontend for UI.");
    }
}
?>
