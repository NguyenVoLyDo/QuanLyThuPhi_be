<?php
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../config/database.php';

class AuditLogController {
    private $auditLog;
    
    public function __construct() {
        $this->auditLog = new AuditLog();
    }
    
    /**
     * Danh sách nhật ký với filtering
     */
    public function index() {
        check_permission(['Admin']);
        
        $search = $_GET['search'] ?? '';
        $user_id = $_GET['user_id'] ?? '';
        $log_action = $_GET['log_action'] ?? '';
        $from_date = $_GET['from_date'] ?? '';
        $to_date = $_GET['to_date'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = 20;
        
        // Map log_action to action expected by Model
        $action = $log_action;
        
        $total = $this->auditLog->countAll($search, $user_id, $action, $from_date, $to_date);
        $pagination = paginate($total, $per_page, $page);
        $logs = $this->auditLog->getAll($search, $user_id, $action, $from_date, $to_date, $page, $per_page);
        
        // Get list of users and actions for filter dropdowns
        $database = new Database();
        $conn = $database->connect();
        
        $users = $conn->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
        $actions = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'pagination' => $pagination,
                    'users' => $users,
                    'actions' => $actions,
                    'search' => $search,
                    'user_id' => $user_id,
                    'log_action' => $log_action,
                    'from_date' => $from_date,
                    'to_date' => $to_date
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }
    
    /**
     * Export audit logs to Excel (CSV)
     */
    public function export() {
        check_permission(['Admin']);
        
        $search = $_GET['search'] ?? '';
        $user_id = $_GET['user_id'] ?? '';
        $log_action = $_GET['log_action'] ?? '';
        $from_date = $_GET['from_date'] ?? '';
        $to_date = $_GET['to_date'] ?? '';
        
        // Map log_action to action expected by Model
        $action = $log_action;
        
        // Get all logs matching filters
        $logs = $this->auditLog->getAll($search, $user_id, $action, $from_date, $to_date, 1, 100000);
        
        // Filename
        $filename = 'AuditLogs_' . date('YmdHis') . '.csv';
        
        // Clear buffer to avoid garbage
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        // Output
        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // Header row
        fputcsv($output, ['STT', 'Thời gian', 'Người dùng', 'Hành động', 'Loại', 'Bảng', 'ID', 'Chi tiết', 'IP']);
        
        $stt = 1;
        foreach ($logs as $log) {
            fputcsv($output, [
                $stt++,
                format_datetime($log['created_at']),
                $log['full_name'] ?? 'N/A',
                $log['action'],
                $log['target_type'] ?? '',
                $log['target_table'] ?? '',
                $log['target_id'] ?? '',
                $log['details'] ?? '',
                $log['ip_address'] ?? ''
            ]);
        }
        
        fclose($output);
        exit();
    }
}
?>
