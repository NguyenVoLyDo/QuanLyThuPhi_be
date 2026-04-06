<?php
require_once __DIR__ . '/../config/database.php';

class Exemption {
    private $conn;
    private $table = 'exemptions';
    
    // Properties
    public $id;
    public $name;
    public $discount_type; // Percent, Amount
    public $discount_value;
    public $description;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Get all exemptions
     */
    public function getAll($search = '', $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        
        if (!empty($search)) {
            $query .= " AND name LIKE :search";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :offset, :per_page";
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search', $search_param);
        }
        
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Count all exemptions
     */
    public function countAll($search = '') {
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        
        if (!empty($search)) {
            $query .= " AND name LIKE :search";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search', $search_param);
        }
        
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }
    
    /**
     * Get exemption by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Create exemption
     */
    public function create() {
        $query = "INSERT INTO {$this->table} 
                  (name, discount_type, discount_value, description)
                  VALUES (:name, :discount_type, :discount_value, :description)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':discount_type', $this->discount_type);
        $stmt->bindParam(':discount_value', $this->discount_value);
        $stmt->bindParam(':description', $this->description);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Tạo chính sách miễn giảm thành công!', 'id' => $this->conn->lastInsertId()];
        }
        return ['success' => false, 'message' => 'Lỗi khi tạo chính sách!'];
    }
    
    /**
     * Update exemption
     */
    public function update() {
        $query = "UPDATE {$this->table} 
                  SET name = :name,
                      discount_type = :discount_type,
                      discount_value = :discount_value,
                      description = :description
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindValue(':name', $this->name);
        $stmt->bindValue(':discount_type', $this->discount_type);
        $stmt->bindValue(':discount_value', $this->discount_value);
        $stmt->bindValue(':description', $this->description);
        $stmt->bindValue(':id', $this->id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật thành công!'];
        }
        return ['success' => false, 'message' => 'Lỗi khi cập nhật!'];
    }
    
    /**
     * Delete exemption
     */
    public function delete($id) {
        // Check if assigned to any student
        $check = $this->conn->prepare("SELECT COUNT(*) as total FROM student_exemptions WHERE exemption_id = :id");
        $check->execute(['id' => $id]);
        if ($check->fetch()['total'] > 0) {
            return ['success' => false, 'message' => 'Không thể xóa chính sách đang được áp dụng cho học sinh!'];
        }
        
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Xóa thành công!'];
        }
        return ['success' => false, 'message' => 'Lỗi khi xóa!'];
    }
    
    /**
     * Assign exemption to student
     */
    public function assignToStudent($student_id, $exemption_id) {
        // Check if already assigned
        $check = $this->conn->prepare("SELECT id FROM student_exemptions WHERE student_id = :sid AND exemption_id = :eid");
        $check->execute(['sid' => $student_id, 'eid' => $exemption_id]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'Học sinh đã có chính sách này!'];
        }
        
        $query = "INSERT INTO student_exemptions (student_id, exemption_id) VALUES (:sid, :eid)";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute(['sid' => $student_id, 'eid' => $exemption_id])) {
            return ['success' => true, 'message' => 'Gán miễn giảm thành công!'];
        }
        return ['success' => false, 'message' => 'Lỗi khi gán miễn giảm!'];
    }
    
    /**
     * Remove exemption from student
     */
    public function revokeFromStudent($student_id, $exemption_id) {
        $query = "DELETE FROM student_exemptions WHERE student_id = :sid AND exemption_id = :eid";
        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute(['sid' => $student_id, 'eid' => $exemption_id])) {
            return ['success' => true, 'message' => 'Hủy miễn giảm thành công!'];
        }
        return ['success' => false, 'message' => 'Lỗi khi hủy miễn giảm!'];
    }
    
    /**
     * Get exemptions by student
     */
    public function getStudentExemptions($student_id) {
        $query = "SELECT e.*, se.assigned_date 
                  FROM exemptions e
                  JOIN student_exemptions se ON e.id = se.exemption_id
                  WHERE se.student_id = :student_id
                  ORDER BY e.discount_type DESC, e.discount_value DESC"; // Prioritize high discounts
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['student_id' => $student_id]);
        return $stmt->fetchAll();
    }
}
?>
