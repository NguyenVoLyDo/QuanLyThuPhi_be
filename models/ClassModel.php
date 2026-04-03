<?php
require_once __DIR__ . '/../config/database.php';

class ClassModel
{
    private $conn;
    private $table = 'classes';

    public $id;
    public $class_name;
    public $grade_level;
    public $description;
    public $teacher_id;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Get all classes
    public function getAll($search = '')
    {
        $query = "SELECT c.*, u.full_name as teacher_name 
                  FROM {$this->table} c
                  LEFT JOIN users u ON c.teacher_id = u.id
                  WHERE 1=1";

        if (!empty($search)) {
            $query .= " AND c.class_name LIKE :search";
        }

        $query .= " ORDER BY c.grade_level, c.class_name";

        $stmt = $this->conn->prepare($query);

        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindParam(':search', $search_param);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Get class by ID
    public function getById($id)
    {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Create class
    public function create()
    {
        $query = "INSERT INTO {$this->table} (class_name, grade_level, description, teacher_id) 
                  VALUES (:class_name, :grade_level, :description, :teacher_id)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':class_name', $this->class_name);
        $stmt->bindParam(':grade_level', $this->grade_level);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':teacher_id', $this->teacher_id);

        return $stmt->execute();
    }

    // Update class
    public function update()
    {
        $query = "UPDATE {$this->table} 
                  SET class_name = :class_name, 
                      grade_level = :grade_level, 
                      description = :description,
                      teacher_id = :teacher_id
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':class_name', $this->class_name);
        $stmt->bindParam(':grade_level', $this->grade_level);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':teacher_id', $this->teacher_id);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // Delete class
    public function delete($id)
    {
        // Check if students exist
        $query = "SELECT COUNT(*) as total FROM students WHERE class_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        if ($stmt->fetch()['total'] > 0) {
            return false; // Cannot delete class with students
        }

        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Get all teachers
    public function getTeachers()
    {
        $query = "SELECT u.*, r.role_name 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE r.role_name = 'Teacher' 
                  ORDER BY u.full_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    // Get class ID by teacher ID (Single - Deprecated or First)
    public function getClassIdByTeacher($teacher_id)
    {
        $ids = $this->getClassIdsByTeacher($teacher_id);
        return !empty($ids) ? $ids[0] : null;
    }

    // Get all class IDs by teacher ID
    public function getClassIdsByTeacher($teacher_id)
    {
        $query = "SELECT id FROM {$this->table} WHERE teacher_id = :teacher_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Assign teacher to a class (and remove from others if necessary to enforce 1-1 if desired, but let's stick to update)
    public function assignTeacher($class_id, $teacher_id)
    {
        // Optional: Remove teacher from any other class if we want strictly 1-1
        // $clear = "UPDATE {$this->table} SET teacher_id = NULL WHERE teacher_id = :teacher_id";
        // $stmtClear = $this->conn->prepare($clear);
        // $stmtClear->execute(['teacher_id' => $teacher_id]);
        // For now, let's just update the target class.
        
        $query = "UPDATE {$this->table} SET teacher_id = :teacher_id WHERE id = :class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->bindParam(':class_id', $class_id);
        return $stmt->execute();
    }
}
