<?php
require_once __DIR__ . '/../config/database.php';

class FeeType
{
    private $conn;
    private $table = 'fee_types';

    // Properties
    public $id;
    public $fee_name;
    public $description;
    public $amount;
    public $fee_category;
    public $is_mandatory;
    public $academic_year;
    public $semester;
    public $is_active;
    public $start_date;
    public $end_date;
    public $status;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Lấy tất cả khoản thu
     */
    public function getAll($search = '', $category = '', $year = '', $page = 1, $per_page = 10)
    {
        $offset = ($page - 1) * $per_page;

        $query = "SELECT * FROM {$this->table} WHERE 1=1";

        if (!empty($search)) {
            $query .= " AND (fee_name LIKE :search1 OR description LIKE :search2)";
        }

        if (!empty($category)) {
            $query .= " AND fee_category = :category";
        }

        if (!empty($year)) {
            $query .= " AND academic_year = :year";
        }

        $query .= " ORDER BY created_at DESC LIMIT :offset, :per_page";

        $stmt = $this->conn->prepare($query);

        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
        }

        if (!empty($category)) {
            $stmt->bindValue(':category', $category);
        }

        if (!empty($year)) {
            $stmt->bindValue(':year', $year);
        }

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Đếm tổng số khoản thu
     */
    public function countAll($search = '', $category = '', $year = '')
    {
        $query = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";

        if (!empty($search)) {
            $query .= " AND (fee_name LIKE :search1 OR description LIKE :search2)";
        }

        if (!empty($category)) {
            $query .= " AND fee_category = :category";
        }

        if (!empty($year)) {
            $query .= " AND academic_year = :year";
        }

        $stmt = $this->conn->prepare($query);

        if (!empty($search)) {
            $search_param = "%{$search}%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
        }

        if (!empty($category)) {
            $stmt->bindValue(':category', $category);
        }

        if (!empty($year)) {
            $stmt->bindValue(':year', $year);
        }

        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }

    /**
     * Lấy khoản thu theo ID
     */
    public function getById($id)
    {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Lấy các khoản thu đang hoạt động
     */
    public function getActive()
    {
        $query = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY fee_category, fee_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Tạo khoản thu mới
     */
    public function create()
    {
        $query = "INSERT INTO {$this->table} 
                  (fee_name, description, amount, fee_category, is_mandatory, 
                   academic_year, semester, is_active, start_date, end_date, status)
                  VALUES (:fee_name, :description, :amount, :fee_category, :is_mandatory,
                          :academic_year, :semester, :is_active, :start_date, :end_date, :status)";

        $stmt = $this->conn->prepare($query);

        // Bind data
        $stmt->bindValue(':fee_name', $this->fee_name);
        $stmt->bindValue(':description', $this->description);
        $stmt->bindValue(':amount', $this->amount);
        $stmt->bindValue(':fee_category', $this->fee_category);
        $stmt->bindValue(':is_mandatory', $this->is_mandatory);
        $stmt->bindValue(':academic_year', $this->academic_year);
        $stmt->bindValue(':semester', $this->semester);
        $stmt->bindValue(':is_active', $this->is_active);
        $stmt->bindValue(':start_date', $this->start_date);
        $stmt->bindValue(':end_date', $this->end_date);
        $stmt->bindValue(':status', $this->status);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Thêm khoản thu thành công!', 'id' => $this->conn->lastInsertId()];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }

    /**
     * Cập nhật khoản thu
     */
    public function update()
    {
        $query = "UPDATE {$this->table} 
                  SET fee_name = :fee_name,
                      description = :description,
                      amount = :amount,
                      fee_category = :fee_category,
                      is_mandatory = :is_mandatory,
                      academic_year = :academic_year,
                      semester = :semester,
                      is_active = :is_active,
                      start_date = :start_date,
                      end_date = :end_date,
                      status = :status
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Bind data
        $stmt->bindValue(':fee_name', $this->fee_name);
        $stmt->bindValue(':description', $this->description);
        $stmt->bindValue(':amount', $this->amount);
        $stmt->bindValue(':fee_category', $this->fee_category);
        $stmt->bindValue(':is_mandatory', $this->is_mandatory);
        $stmt->bindValue(':academic_year', $this->academic_year);
        $stmt->bindValue(':semester', $this->semester);
        $stmt->bindValue(':is_active', $this->is_active);
        $stmt->bindValue(':start_date', $this->start_date);
        $stmt->bindValue(':end_date', $this->end_date);
        $stmt->bindValue(':status', $this->status);
        $stmt->bindValue(':id', $this->id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật thành công!'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }

    /**
     * Xóa khoản thu
     */
    public function delete($id)
    {
        // Kiểm tra xem khoản thu có thanh toán nào chưa
        $check_query = "SELECT COUNT(*) as total FROM payments WHERE fee_type_id = :id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindValue(':id', $id);
        $check_stmt->execute();
        $check = $check_stmt->fetch();

        if ($check['total'] > 0) {
            return ['success' => false, 'message' => 'Không thể xóa khoản thu đã có thanh toán!'];
        }

        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Xóa thành công!'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }

    /**
     * Lấy các loại phí
     */
    public function getCategories()
    {
        return [
            'Tuition' => 'Học phí',
            'Meal' => 'Tiền ăn',
            'Uniform' => 'Đồng phục',
            'Activity' => 'Hoạt động',
            'Other' => 'Khác'
        ];
    }

    /**
     * Tạo công nợ cho tất cả học sinh
     */
    public function createDebtForAllStudents($fee_type_id, $due_date = null)
    {
        try {
            $this->conn->beginTransaction();

            // Lấy thông tin khoản thu
            $fee = $this->getById($fee_type_id);
            if (!$fee) {
                throw new Exception('Không tìm thấy khoản thu!');
            }

            // Lấy danh sách học sinh đang hoạt động
            $query = "SELECT id FROM students WHERE is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $students = $stmt->fetchAll();

            // Tạo công nợ cho từng học sinh
            $insert_query = "INSERT IGNORE INTO student_debts 
                            (student_id, fee_type_id, total_amount, due_date, status)
                            VALUES (:student_id, :fee_type_id, :amount, :due_date, 'Unpaid')";

            $insert_stmt = $this->conn->prepare($insert_query);
            $count = 0;

            foreach ($students as $student) {
                // Tính toán số tiền sau khi miễn giảm
                $base_amount = $fee['amount'];
                $final_amount = $base_amount;

                // Lấy các chính sách miễn giảm của học sinh này
                $ex_query = "SELECT e.discount_type, e.discount_value 
                            FROM exemptions e 
                            JOIN student_exemptions se ON e.id = se.exemption_id 
                            WHERE se.student_id = :student_id";
                $ex_stmt = $this->conn->prepare($ex_query);
                $ex_stmt->execute(['student_id' => $student['id']]);
                $exemptions = $ex_stmt->fetchAll();

                $percent_discount = 0;
                $amount_discount = 0;

                foreach ($exemptions as $ex) {
                    if ($ex['discount_type'] === 'Percent') {
                        $percent_discount += $ex['discount_value'];
                    } else {
                        $amount_discount += $ex['discount_value'];
                    }
                }

                // Áp dụng phần trăm trước, sau đó trừ số tiền cố định
                $final_amount = $base_amount * (1 - min($percent_discount, 100) / 100);
                $final_amount = max(0, $final_amount - $amount_discount);

                $insert_stmt->bindValue(':student_id', $student['id']);
                $insert_stmt->bindValue(':fee_type_id', $fee_type_id);
                $insert_stmt->bindValue(':amount', $final_amount);
                $insert_stmt->bindValue(':due_date', $due_date);

                if ($insert_stmt->execute()) {
                    $count++;
                }
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => "Đã tạo công nợ cho {$count} học sinh!",
                'count' => $count
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    /**
     * Lấy danh sách khoản thu theo học kỳ
     */
    public function getBySemester($year, $semester)
    {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE academic_year = :year AND semester = :semester AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['year' => $year, 'semester' => $semester]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách các học kỳ có trong hệ thống
     */
    public function getSemesters()
    {
        $query = "SELECT DISTINCT academic_year, semester FROM " . $this->table . " ORDER BY academic_year DESC, semester DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}