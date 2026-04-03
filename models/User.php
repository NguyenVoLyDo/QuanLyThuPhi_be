<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table = 'users';
    
    // Properties
    public $id;
    public $username;
    public $password;
    public $full_name;
    public $email;
    public $phone;
    public $role_id;
    public $student_id;
    public $is_active;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Đăng nhập
     */
    public function login($username, $password) {
        $query = "SELECT u.*, r.role_name 
                  FROM {$this->table} u
                  INNER JOIN roles r ON u.role_id = r.id
                  WHERE u.username = :username AND u.is_active = 1
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            
            // Verify password
            if (password_verify($password, $row['password'])) {
                return $row;
            }
        }
        
        return false;
    }
    
    /**
     * Lấy tất cả user (có phân trang và tìm kiếm)
     */
    public function getAll($search = '', $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT u.*, r.role_name 
                  FROM {$this->table} u
                  INNER JOIN roles r ON u.role_id = r.id
                  WHERE 1=1";
        
        if (!empty($search)) {
            $query .= " AND (u.username LIKE :search1 OR u.full_name LIKE :search2 OR u.email LIKE :search3)";
        }
        
        $query .= " ORDER BY u.created_at DESC LIMIT :offset, :per_page";
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
            $stmt->bindValue(':search3', $search_param);
        }
        
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Đếm tổng số user
     */
    public function countAll($search = '') {
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        
        if (!empty($search)) {
            $query .= " AND (username LIKE :search1 OR full_name LIKE :search2 OR email LIKE :search3)";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
            $stmt->bindValue(':search3', $search_param);
        }
        
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }
    
    /**
     * Lấy user theo ID
     */
    public function getById($id) {
        $query = "SELECT u.*, r.role_name 
                  FROM {$this->table} u
                  INNER JOIN roles r ON u.role_id = r.id
                  WHERE u.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Kiểm tra username đã tồn tại
     */
    public function usernameExists($username, $exclude_id = null) {
        $query = "SELECT id FROM {$this->table} WHERE username = :username";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Lấy user theo username
     */
    public function getByUsername($username) {
        $query = "SELECT * FROM {$this->table} WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Tạo user mới
     */
    public function create() {
        // Kiểm tra trùng username
        if ($this->usernameExists($this->username)) {
            return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại!'];
        }
        
        $query = "INSERT INTO {$this->table} 
                  (username, password, full_name, email, phone, role_id, student_id, is_active)
                  VALUES (:username, :password, :full_name, :email, :phone, :role_id, :student_id, :is_active)";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash password
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        
        // Bind data
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':role_id', $this->role_id);
        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':is_active', $this->is_active);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Tạo tài khoản thành công!', 'id' => $this->conn->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }
    
    /**
     * Cập nhật user
     */
    public function update() {
        // Kiểm tra trùng username
        if ($this->usernameExists($this->username, $this->id)) {
            return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại!'];
        }
        
        $query = "UPDATE {$this->table} 
                  SET username = :username,
                      full_name = :full_name,
                      email = :email,
                      phone = :phone,
                      role_id = :role_id,
                      student_id = :student_id,
                      is_active = :is_active";
        
        // Nếu có đổi password
        if (!empty($this->password)) {
            $query .= ", password = :password";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind data
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':role_id', $this->role_id);
        $stmt->bindParam(':student_id', $this->student_id);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);
        
        if (!empty($this->password)) {
            $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
            $stmt->bindParam(':password', $hashed_password);
        }
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật thành công!'];
        }
        
        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }
    
    /**
     * Xóa user
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Xóa thành công!'];
        }
        
        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }
    
    /**
     * Lấy tất cả roles
     */
    public function getRoles() {
        $query = "SELECT * FROM roles ORDER BY id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}