<?php
require_once __DIR__ . '/../config/database.php';

class AuditLog {
    private $conn;
    private $table = 'audit_logs';
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Ghi log hành động
     */
    public function log($user_id, $action, $target_type, $target_id = null, $details = null) {
        try {
            $query = "INSERT INTO {$this->table} (user_id, action, target_type, target_id, details, ip_address)
                      VALUES (:user_id, :action, :target_type, :target_id, :details, :ip_address)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                'user_id' => $user_id,
                'action' => $action,
                'target_type' => $target_type,
                'target_id' => $target_id,
                'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lấy danh sách log với filters
     */
    public function getAll($search = '', $user_id = '', $action = '', $from_date = '', $to_date = '', $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT l.*, u.full_name, u.username, r.role_name
                  FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (l.action LIKE :search OR l.target_type LIKE :search OR u.full_name LIKE :search OR l.details LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        if (!empty($user_id)) {
            $query .= " AND l.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        if (!empty($action)) {
            $query .= " AND l.action = :action";
            $params[':action'] = $action;
        }
        
        if (!empty($from_date)) {
            $query .= " AND DATE(l.created_at) >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if (!empty($to_date)) {
            $query .= " AND DATE(l.created_at) <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        $query .= " ORDER BY l.created_at DESC LIMIT :offset, :per_page";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function countAll($search = '', $user_id = '', $action = '', $from_date = '', $to_date = '') {
        $query = "SELECT COUNT(*) as total FROM {$this->table} l
                  LEFT JOIN users u ON l.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (l.action LIKE :search OR l.target_type LIKE :search OR u.full_name LIKE :search OR l.details LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        if (!empty($user_id)) {
            $query .= " AND l.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        if (!empty($action)) {
            $query .= " AND l.action = :action";
            $params[':action'] = $action;
        }
        
        if (!empty($from_date)) {
            $query .= " AND DATE(l.created_at) >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if (!empty($to_date)) {
            $query .= " AND DATE(l.created_at) <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }
}
?>
