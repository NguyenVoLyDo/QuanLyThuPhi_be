<?php
require_once __DIR__ . '/../config/database.php';

class Student
{
    private $conn;
    private $table = 'students';

    // Properties
    public $id;
    public $student_code;
    public $full_name;
    public $date_of_birth;
    public $gender;
    public $class_id;
    public $parent_name;
    public $parent_phone;
    public $parent_email;
    public $address;
    public $is_active;
    public $notes;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Lấy tất cả học sinh (có phân trang và tìm kiếm)
     */
    public function getAll($search = '', $class_id = '', $page = 1, $per_page = 10)
    {
        $offset = ($page - 1) * $per_page;

        // Try query with debt calculation
        $query = "SELECT s.*, c.class_name, c.grade_level,
                         (SELECT SUM(total_amount - paid_amount) FROM student_debts sd WHERE sd.student_id = s.id AND sd.status != 'Paid') as total_debt
                  FROM {$this->table} s
                  LEFT JOIN classes c ON s.class_id = c.id
                  WHERE 1=1";

        if (!empty($search)) {
            $query .= " AND (s.student_code LIKE :search1 OR s.full_name LIKE :search2 
                        OR s.parent_name LIKE :search3 OR s.parent_phone LIKE :search4)";
        }

        if (!empty($class_id)) {
            if (is_array($class_id)) {
                $inQuery = implode(',', array_map(function ($k) {
                    return ":class_id_$k"; }, array_keys($class_id)));
                $query .= " AND s.class_id IN ($inQuery)";
            } else {
                $query .= " AND s.class_id = :class_id";
            }
        }

        $query .= " ORDER BY s.created_at DESC LIMIT :offset, :per_page";

        try {
            $stmt = $this->conn->prepare($query);
            $this->bindSearchParams($stmt, $search, $class_id, $offset, $per_page);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback: Query WITHOUT debt calculation if table missing
            $query = "SELECT s.*, c.class_name, c.grade_level, 0 as total_debt
                      FROM {$this->table} s
                      LEFT JOIN classes c ON s.class_id = c.id
                      WHERE 1=1";

            if (!empty($search)) {
                $query .= " AND (s.student_code LIKE :search1 OR s.full_name LIKE :search2 
                            OR s.parent_name LIKE :search3 OR s.parent_phone LIKE :search4)";
            }

            if (!empty($class_id)) {
                if (is_array($class_id)) {
                    $inQuery = implode(',', array_map(function ($k) {
                        return ":class_id_$k"; }, array_keys($class_id)));
                    $query .= " AND s.class_id IN ($inQuery)";
                } else {
                    $query .= " AND s.class_id = :class_id";
                }
            }

            $query .= " ORDER BY s.created_at DESC LIMIT :offset, :per_page";

            $stmt = $this->conn->prepare($query);
            $this->bindSearchParams($stmt, $search, $class_id, $offset, $per_page);
            $stmt->execute();
            return $stmt->fetchAll();
        }
    }

    private function bindSearchParams($stmt, $search, $class_id, $offset, $per_page)
    {
        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
            $stmt->bindValue(':search3', $search_param);
            $stmt->bindValue(':search4', $search_param);
        }

        if (!empty($class_id)) {
            if (is_array($class_id)) {
                foreach ($class_id as $k => $id) {
                    $stmt->bindValue(":class_id_$k", $id);
                }
            } else {
                $stmt->bindParam(':class_id', $class_id);
            }
        }

        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    }

    /**
     * Đếm tổng số học sinh
     */
    public function countAll($search = '', $class_id = '')
    {
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";

        if (!empty($search)) {
            $query .= " AND (student_code LIKE :search1 OR full_name LIKE :search2 
                        OR parent_name LIKE :search3 OR parent_phone LIKE :search4)";
        }

        if (!empty($class_id)) {
            if (is_array($class_id)) {
                $inQuery = implode(',', array_map(function ($k) {
                    return ":class_id_$k"; }, array_keys($class_id)));
                $query .= " AND class_id IN ($inQuery)";
            } else {
                $query .= " AND class_id = :class_id";
            }
        }

        $stmt = $this->conn->prepare($query);

        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
            $stmt->bindValue(':search3', $search_param);
            $stmt->bindValue(':search4', $search_param);
        }

        if (!empty($class_id)) {
            if (is_array($class_id)) {
                foreach ($class_id as $k => $id) {
                    $stmt->bindValue(":class_id_$k", $id);
                }
            } else {
                $stmt->bindParam(':class_id', $class_id);
            }
        }

        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }

    /**
     * Lấy học sinh theo ID
     */
    public function getById($id)
    {
        $query = "SELECT s.*, c.class_name, c.grade_level
                  FROM {$this->table} s
                  LEFT JOIN classes c ON s.class_id = c.id
                  WHERE s.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Kiểm tra mã học sinh đã tồn tại
     */
    public function codeExists($student_code, $exclude_id = null)
    {
        $query = "SELECT id FROM {$this->table} WHERE student_code = :student_code";

        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_code', $student_code);

        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Tạo học sinh mới
     */
    public function create()
    {
        // Kiểm tra trùng mã học sinh
        if ($this->codeExists($this->student_code)) {
            return ['success' => false, 'message' => 'Mã học sinh đã tồn tại!'];
        }

        $query = "INSERT INTO {$this->table} 
                  (student_code, full_name, date_of_birth, gender, class_id, 
                   parent_name, parent_phone, parent_email, address, is_active, notes)
                  VALUES (:student_code, :full_name, :date_of_birth, :gender, :class_id,
                          :parent_name, :parent_phone, :parent_email, :address, :is_active, :notes)";

        $stmt = $this->conn->prepare($query);

        // Bind data
        $stmt->bindParam(':student_code', $this->student_code);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':date_of_birth', $this->date_of_birth);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':class_id', $this->class_id);
        $stmt->bindParam(':parent_name', $this->parent_name);
        $stmt->bindParam(':parent_phone', $this->parent_phone);
        $stmt->bindParam(':parent_email', $this->parent_email);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':notes', $this->notes);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Thêm học sinh thành công!', 'id' => $this->conn->lastInsertId()];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }

    /**
     * Cập nhật học sinh
     */
    public function update()
    {
        // Kiểm tra trùng mã học sinh
        if ($this->codeExists($this->student_code, $this->id)) {
            return ['success' => false, 'message' => 'Mã học sinh đã tồn tại!'];
        }

        $query = "UPDATE {$this->table} 
                  SET student_code = :student_code,
                      full_name = :full_name,
                      date_of_birth = :date_of_birth,
                      gender = :gender,
                      class_id = :class_id,
                      parent_name = :parent_name,
                      parent_phone = :parent_phone,
                      parent_email = :parent_email,
                      address = :address,
                      is_active = :is_active
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Bind data
        $stmt->bindParam(':student_code', $this->student_code);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':date_of_birth', $this->date_of_birth);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':class_id', $this->class_id);
        $stmt->bindParam(':parent_name', $this->parent_name);
        $stmt->bindParam(':parent_phone', $this->parent_phone);
        $stmt->bindParam(':parent_email', $this->parent_email);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật thành công!'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }

    /**
     * Cập nhật ghi chú học sinh (Safe Update)
     */
    public function updateNote($id, $notes)
    {
        $query = "UPDATE {$this->table} SET notes = :notes WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật ghi chú thành công!'];
        }
        return ['success' => false, 'message' => 'Lỗi khi cập nhật ghi chú!'];
    }

    /**
     * Xóa học sinh
     */
    public function delete($id)
    {
        // 1. Kiểm tra lịch sử thanh toán (Giữ nguyên logic bảo vệ doanh thu)
        try {
            $check_query = "SELECT COUNT(*) as total FROM payments WHERE student_id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            $check = $check_stmt->fetch();

            if ($check['total'] > 0) {
                return ['success' => false, 'message' => 'Không thể xóa học sinh đã có lịch sử thanh toán!'];
            }

            // 2. Nếu chưa đóng tiền -> Thực hiện xóa thông minh (Cascade Delete thủ công)
            $this->conn->beginTransaction();

            // Xóa miễn giảm
            $del_exempt = "DELETE FROM student_exemptions WHERE student_id = :id";
            $stmt_e = $this->conn->prepare($del_exempt);
            $stmt_e->execute(['id' => $id]);

            // Xóa công nợ
            $del_debts = "DELETE FROM student_debts WHERE student_id = :id";
            $stmt_d = $this->conn->prepare($del_debts);
            $stmt_d->execute(['id' => $id]);

            // Xóa học sinh
            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Xóa học sinh thành công (đã dọn dẹp các khoản nợ chưa đóng)!'];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            // Trả về thông báo lỗi thân thiện thay vì raw SQL error
            return ['success' => false, 'message' => 'Lỗi khi xóa học sinh: ' . $e->getMessage()];
        }
    }

    /**
     * Lấy tất cả lớp học
     */
    public function getClasses()
    {
        $query = "SELECT * FROM classes ORDER BY grade_level, class_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lấy công nợ của học sinh
     */
    public function getDebts($student_id)
    {
        try {
            $query = "SELECT sd.*, ft.fee_name, ft.amount as fee_amount, ft.fee_category,
                      (sd.total_amount - sd.paid_amount) as remaining_amount
                      FROM student_debts sd
                      INNER JOIN fee_types ft ON sd.fee_type_id = ft.id
                      WHERE sd.student_id = :student_id
                      ORDER BY sd.due_date ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Lấy lịch sử thanh toán
     */
    public function getPaymentHistory($student_id)
    {
        $query = "SELECT p.*, ft.fee_name, u.full_name as collector_name
                  FROM payments p
                  INNER JOIN fee_types ft ON p.fee_type_id = ft.id
                  INNER JOIN users u ON p.collected_by = u.id
                  WHERE p.student_id = :student_id
                  ORDER BY p.payment_date DESC, p.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }
    /**
     * Thống kê số lượng học sinh chưa hoàn thành đóng phí theo học kỳ
     */
    public function countUnpaidBySemester()
    {
        try {
            $query = "SELECT ft.academic_year, ft.semester, COUNT(DISTINCT s.id) as unpaid_count
                      FROM student_debts sd
                      JOIN students s ON sd.student_id = s.id
                      JOIN fee_types ft ON sd.fee_type_id = ft.id
                      WHERE sd.status != 'Paid'
                      GROUP BY ft.academic_year, ft.semester
                      ORDER BY ft.academic_year DESC, ft.semester DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}