<?php
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/FeeType.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/ClassModel.php';

class DebtController
{
    private $studentModel;
    private $feeTypeModel;
    private $classModel;
    private $db;
    private $auditLog;

    public function __construct()
    {
        $this->studentModel = new Student();
        $this->feeTypeModel = new FeeType();
        $this->classModel = new ClassModel();
        $database = new Database();
        $this->db = $database->connect();
        $this->auditLog = new AuditLog();
    }

    /**
     * Hiển thị form tạo công nợ hàng loạt
     */
    public function createBatch()
    {
        check_permission(['Accountant']);

        $semesters = $this->feeTypeModel->getSemesters();
        $classes = $this->classModel->getAll();
        $feeTypes = $this->feeTypeModel->getActive();

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'semesters' => $semesters,
                    'classes' => $classes,
                    'feeTypes' => $feeTypes
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý tạo công nợ hàng loạt
     */
    public function storeBatch()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=debt&action=createBatch');
            exit;
        }

        $year = $_POST['academic_year'];
        $semester = $_POST['semester'];
        $class_id = $_POST['class_id'] ?? '';
        $specific_fee_id = $_POST['fee_type_id'] ?? '';

        if (empty($year) || empty($semester)) {
            set_flash('error', 'Vui lòng chọn Năm học và Học kỳ!');
            header('Location: index.php?controller=debt&action=createBatch');
            exit;
        }

        // 1. Lấy tất cả student active (có lọc theo lớp nếu chọn)
        $students = $this->studentModel->getAll('', $class_id, 1, 10000); // Hack: get all

        // 2. Xác định danh sách khoản thu cần gán
        $fees = [];
        if (!empty($specific_fee_id)) {
            $fee = $this->feeTypeModel->getById($specific_fee_id);
            if ($fee) {
                $fees[] = $fee;
            }
        } else {
             // Lấy tất cả Fee Types active của kỳ đó
            $fees = $this->feeTypeModel->getBySemester($year, $semester);
        }

        if (empty($fees)) {
            set_flash('error', 'Không tìm thấy khoản thu nào!');
            header('Location: index.php?controller=debt&action=createBatch');
            exit;
        }

        $count_success = 0;
        $count_skip = 0;

        foreach ($students as $student) {
            // Lấy danh sách miễn giảm của học sinh 
            $exemptionsStmt = $this->db->prepare("
                SELECT e.discount_type, e.discount_value 
                FROM student_exemptions se
                INNER JOIN exemptions e ON se.exemption_id = e.id
                WHERE se.student_id = :sid
            ");
            $exemptionsStmt->execute(['sid' => $student['id']]);
            $exemptions = $exemptionsStmt->fetchAll();

            foreach ($fees as $fee) {
                // Check if debt exists
                $check = $this->db->prepare("SELECT id FROM student_debts WHERE student_id = :sid AND fee_type_id = :fid");
                $check->execute(['sid' => $student['id'], 'fid' => $fee['id']]);

                if ($check->rowCount() == 0) {
                    // Calculate discounted amount
                    $originalAmount = $fee['amount'];
                    $finalAmount = $originalAmount;
                    
                    // Apply all exemptions
                    foreach ($exemptions as $exemption) {
                        if ($exemption['discount_type'] === 'Percent') {
                            // Percentage discount
                            $discount = ($originalAmount * $exemption['discount_value']) / 100;
                            $finalAmount -= $discount;
                        } else {
                            // Fixed amount discount
                            $finalAmount -= $exemption['discount_value'];
                        }
                    }
                    
                    // Ensure amount is not negative
                    $finalAmount = max(0, $finalAmount);

                    // Create debt with discounted amount
                    $ins = $this->db->prepare("INSERT INTO student_debts (student_id, fee_type_id, total_amount, paid_amount, status, due_date) VALUES (:sid, :fid, :amount, 0, 'Unpaid', :due)");
                    $ins->execute([
                        'sid' => $student['id'],
                        'fid' => $fee['id'],
                        'amount' => $finalAmount,
                        'due' => $fee['end_date']
                    ]);
                    $count_success++;
                } else {
                    $count_skip++;
                }
            }
        }

        $this->auditLog->log($_SESSION['user_id'], 'BATCH_DEBT', 'student_debts', 0, "Tạo $count_success công nợ cho $year - HK$semester (áp dụng miễn giảm)");

        $scopeText = empty($class_id) ? "toàn trường" : "theo lớp đã chọn";
        set_flash('success', "Đã tạo thành công $count_success công nợ mới ($scopeText). Bỏ qua $count_skip đã tồn tại.");
        header('Location: index.php?controller=debt&action=createBatch');
    }
}
?>
