<?php
require_once __DIR__ . '/../models/Exemption.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Student.php';

class ExemptionController
{
    private $exemptionModel;
    private $studentModel;

    public function __construct()
    {
        $this->exemptionModel = new Exemption();
        $this->studentModel = new Student();
    }

    /**
     * Danh sách chính sách miễn giảm (chung)
     */
    public function index()
    {
        check_permission(['Accountant', 'Teacher']);

        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        $total = $this->exemptionModel->countAll($search);
        $pagination = paginate($total, $per_page, $page);
        $exemptions = $this->exemptionModel->getAll($search, $page, $per_page);

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['exemptories' => $exemptions, 'pagination' => $pagination]]);
        }
        die("Backend API - please use frontend for UI.");
    }



    /**
     * Xử lý tạo chính sách
     */
    public function store()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=exemption&action=create');
            exit;
        }

        $this->exemptionModel->name = clean_input($_POST['name']);
        $this->exemptionModel->discount_type = $_POST['discount_type'];
        $this->exemptionModel->discount_value = $_POST['discount_value'];
        $this->exemptionModel->description = clean_input($_POST['description']);

        $res = $this->exemptionModel->create();
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
        header('Location: index.php?controller=exemption&action=index');
        exit;
    }

    /**
     * Form tạo chính sách
     */
    public function create()
    {
        check_permission(['Accountant']);
        require_once __DIR__ . '/../../frontend/views/exemptions/create.php';
    }

    /**
     * Form sửa chính sách
     */
    public function edit()
    {
        check_permission(['Accountant']);
        $id = $_GET['id'] ?? 0;
        $exemption = $this->exemptionModel->getById($id);

        if (!$exemption) {
            set_flash('error', 'Không tìm thấy chính sách!');
            header('Location: index.php?controller=exemption&action=index');
            exit;
        }
        require_once __DIR__ . '/../../frontend/views/exemptions/edit.php';
    }

    /**
     * Xử lý cập nhật
     */
    public function update()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=exemption&action=index');
            exit;
        }

        $this->exemptionModel->id = $_POST['id'];
        $this->exemptionModel->name = clean_input($_POST['name']);
        $this->exemptionModel->discount_type = $_POST['discount_type'];
        $this->exemptionModel->discount_value = $_POST['discount_value'];
        $this->exemptionModel->description = clean_input($_POST['description']);

        $res = $this->exemptionModel->update();
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
        header('Location: index.php?controller=exemption&action=index');
        exit;
    }

    /**
     * Xóa chính sách
     */
    public function delete()
    {
        check_permission(['Accountant']);
        $id = $_GET['id'] ?? 0;
        $res = $this->exemptionModel->delete($id);

        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
        header('Location: index.php?controller=exemption&action=index');
        exit;
    }

    /**
     * Gán chính sách cho học sinh
     */
    public function assign()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $student_id = $_POST['student_id'];
            $exemption_id = $_POST['exemption_id'];

            // Admin/Accountant auto approve
            $status = 'Approved';


            $res = $this->exemptionModel->assignToStudent($student_id, $exemption_id);

            if ($res['success']) {

                
                // Tự động cập nhật lại số tiền nợ cho học sinh
                if ($status === 'Approved') {
                    $this->recalculateStudentDebts($student_id);
                }
                
                set_flash('success', $status === 'Pending' ? 'Đã gửi yêu cầu miễn giảm!' : 'Đã gán miễn giảm thành công! Các khoản nợ đã được cập nhật.');
            } else {
                set_flash('error', $res['message']);
            }

            header('Location: index.php?controller=student&action=view&id=' . $student_id);
            exit;
        }
    }
    
    /**
     * Tính lại số tiền nợ cho học sinh sau khi có miễn giảm mới
     */
    private function recalculateStudentDebts($student_id)
    {
        $db = new Database();
        $conn = $db->connect();
        
        // 1. Lấy tất cả miễn giảm của học sinh
        $exemptionsStmt = $conn->prepare("
            SELECT e.discount_type, e.discount_value 
            FROM student_exemptions se
            INNER JOIN exemptions e ON se.exemption_id = e.id
            WHERE se.student_id = :sid
        ");
        $exemptionsStmt->execute(['sid' => $student_id]);
        $exemptions = $exemptionsStmt->fetchAll();
        
        // Cần tiếp tục chạy ngay cả khi empty($exemptions) để reset về giá gốc
        
        // 2. Lấy tất cả các khoản nợ của học sinh (kể cả đã đóng một phần)
        $debtsStmt = $conn->prepare("
            SELECT sd.id, sd.fee_type_id, sd.paid_amount, ft.amount as original_amount
            FROM student_debts sd
            INNER JOIN fee_types ft ON sd.fee_type_id = ft.id
            WHERE sd.student_id = :sid
        ");
        $debtsStmt->execute(['sid' => $student_id]);
        $debts = $debtsStmt->fetchAll();
        
        // 3. Cập nhật lại từng khoản nợ
        foreach ($debts as $debt) {
            $originalAmount = $debt['original_amount'];
            $finalAmount = $originalAmount;
            
            // Chỉ áp dụng miễn giảm nếu khoản nợ chưa được thanh toán hoàn tất theo giá gốc ban đầu
            // Tránh trường hợp đã đóng đủ 100% rồi mới áp dụng miễn giảm gây ra số dư âm (không mong muốn)
            if ($debt['paid_amount'] < $originalAmount) {
                // Áp dụng tất cả miễn giảm
                foreach ($exemptions as $exemption) {
                    if ($exemption['discount_type'] === 'Percent') {
                        $discount = ($originalAmount * $exemption['discount_value']) / 100;
                        $finalAmount -= $discount;
                    } else {
                        $finalAmount -= $exemption['discount_value'];
                    }
                }
            }
            
            // Đảm bảo không âm
            $finalAmount = max(0, $finalAmount);
            
            // Xác định status mới
            $paidAmount = $debt['paid_amount'];
            $newStatus = 'Unpaid';
            if ($paidAmount >= $finalAmount) {
                $newStatus = 'Paid';
            } elseif ($paidAmount > 0) {
                $newStatus = 'Partial';
            }
            
            // Cập nhật total_amount và status
            $updateStmt = $conn->prepare("
                UPDATE student_debts 
                SET total_amount = :total, status = :status 
                WHERE id = :id
            ");
            $updateStmt->execute([
                'total' => $finalAmount,
                'status' => $newStatus,
                'id' => $debt['id']
            ]);
        }
    }

    /**
     * Hủy gán chính sách
     */
    public function revoke()
    {
        check_permission(['Accountant', 'Teacher']); // Teachers usually request, maybe not revoke directly? Let's restrict for safety or allow if owner.

        $student_id = $_GET['student_id'];
        $exemption_id = $_GET['exemption_id'];

        $res = $this->exemptionModel->revokeFromStudent($student_id, $exemption_id);

        if ($res['success']) {
            // Tính lại nợ sau khi gỡ miễn giảm
            $this->recalculateStudentDebts($student_id);
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }

        header('Location: index.php?controller=student&action=view&id=' . $student_id);
        exit;
    }
}
?>
