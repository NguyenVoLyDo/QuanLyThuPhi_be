<?php
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/FeeType.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/AuditLog.php';

class PaymentController
{
    private $paymentModel;
    private $studentModel;
    private $feeTypeModel;
    private $auditLog;

    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->studentModel = new Student();
        $this->feeTypeModel = new FeeType();
        $this->auditLog = new AuditLog();
    }

    /**
     * Danh sách thanh toán
     */
    public function index()
    {
        check_permission(['Accountant', 'Teacher', 'Student']);

        $search = $_GET['search'] ?? '';

        $role_id = $_SESSION['role_id'] ?? 0;
        $session_student_id = $_SESSION['student_id'] ?? 0;
        $role_name = $_SESSION['role_name'] ?? $_SESSION['role'] ?? '';

        $is_student = ($role_id == 4 || $session_student_id > 0 || strcasecmp(trim($role_name), 'Student') === 0);

        if ($is_student) {
            // Use the session student ID. .
            $student_id = ($session_student_id > 0) ? $session_student_id : -1;
        } else {
            $student_id = $_GET['student_id'] ?? '';
        }

        // Logic for Teachers
        $is_teacher = ($role_id == 3 || strcasecmp(trim($role_name), 'Teacher') === 0);

        $from_date = $_GET['from_date'] ?? '';
        $to_date = $_GET['to_date'] ?? '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        $class_id = $_GET['class_id'] ?? '';
        $fee_type_id = $_GET['fee_type_id'] ?? '';

        // For teachers which classes they can see
        $teacher_class_filter = null;
        if ($is_teacher) {
            $classModel = new ClassModel();
            $teacherClassIds = $classModel->getClassIdsByTeacher($_SESSION['user_id']);

            if (!empty($_GET['class_id']) && in_array((int) $_GET['class_id'], $teacherClassIds)) {
                // Teacher selected a specific class they own
                $teacher_class_filter = (int) $_GET['class_id'];
            } else {
                // Show all classes teacher owns
                $teacher_class_filter = $teacherClassIds;
                if (empty($teacher_class_filter)) {
                    $teacher_class_filter = [-1]; 
                }
            }
        }

        $filters = [
            'search' => $search,
            'student_id' => $student_id,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'class_id' => ($is_teacher) ? '' : $class_id, 
            'fee_type_id' => $fee_type_id,
            'page' => $page,
            'per_page' => $per_page
        ];

        $viewer = [
            'role' => $role_name,
            'student_id' => $_SESSION['student_id'] ?? null,
            'class_id' => ($is_teacher) ? $teacher_class_filter : null
        ];

        $total = $this->paymentModel->countAll($filters, $viewer);
        $pagination = paginate($total, $per_page, $page);

        $payments = $this->paymentModel->getAll($filters, $viewer);

        // Fetch options for filters
        $classModel = new ClassModel();
        $classes = $classModel->getAll();

        // Filter classes for Teacher
        if (strcasecmp(trim($_SESSION['role_name']), 'Teacher') === 0) {
            if (!isset($teacherClassIds)) {
                $teacherClassIds = $classModel->getClassIdsByTeacher($_SESSION['user_id']);
            }
            $classes = array_filter($classes, function ($c) use ($teacherClassIds) {
                return in_array($c['id'], $teacherClassIds);
            });
        }

        $feeTypes = $this->feeTypeModel->getAll();

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'payments' => $payments,
                    'feeTypes' => $feeTypes,
                    'classes' => array_values($classes),
                    'pagination' => $pagination,
                    'search' => $search,
                    'student_id' => $student_id,
                    'from_date' => $from_date,
                    'to_date' => $to_date,
                    'class_id' => $class_id,
                    'fee_type_id' => $fee_type_id
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Export Excel (CSV)
     */
    public function export()
    {
        check_permission(['Accountant', 'Teacher']);

        $search = $_GET['search'] ?? '';
        $student_id = $_GET['student_id'] ?? '';
        $from_date = $_GET['from_date'] ?? '';
        $to_date = $_GET['to_date'] ?? '';
        $class_id = $_GET['class_id'] ?? '';
        $fee_type_id = $_GET['fee_type_id'] ?? '';

        if (strcasecmp(trim($_SESSION['role_name']), 'Teacher') === 0) {
            $classModel = new ClassModel();
            $teacherClassIds = $classModel->getClassIdsByTeacher($_SESSION['user_id']);
            $validIds = array_map('strval', $teacherClassIds);

            if (!empty($_GET['class_id']) && in_array((string) $_GET['class_id'], $validIds)) {
                $class_id = $_GET['class_id'];
            } else {
                $class_id = $teacherClassIds;
                if (empty($class_id)) {
                    $class_id = [-1];
                }
            }
        }

        // Detect role robustly
        $role_name = $_SESSION['role_name'] ?? $_SESSION['role'] ?? '';
        $is_teacher = (strcasecmp(trim($role_name), 'Teacher') === 0);

        $filters = [
            'search' => $search,
            'student_id' => $student_id,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'class_id' => $class_id,
            'fee_type_id' => $fee_type_id,
            'page' => 1,
            'per_page' => 100000
        ];

        $viewer = [
            'role' => $role_name,
            'student_id' => $_SESSION['student_id'] ?? null,
            'class_id' => ($is_teacher) ? $class_id : null
        ];

        // Get all payments
        $payments = $this->paymentModel->getAll($filters, $viewer);

        $filename = 'DanhSachThanhToan_' . date('YmdHis') . '.csv';

        // Clear buffer to avoid garbage
        if (ob_get_level())
            ob_end_clean();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, ['STT', 'Mã phiếu', 'Mã HS', 'Họ tên', 'Lớp', 'Khoản thu', 'Số tiền', 'Ngày đóng', 'Phương thức', 'Người thu', 'Ghi chú']);

        $stt = 1;
        foreach ($payments as $p) {
            fputcsv($output, [
                $stt++,
                $p['payment_code'],
                $p['student_code'],
                $p['student_name'],
                $p['class_name'],
                $p['fee_name'],
                $p['amount_paid'],
                format_date($p['payment_date']),
                $p['payment_method'],
                $p['collector_name'],
                $p['notes']
            ]);
        }

        fclose($output);
        exit();
    }

    public function create()
    {
        check_permission(['Accountant', 'Teacher', 'Student']);

        $selected_student_id = $_GET['student_id'] ?? '';

        // Nếu là học sinh, tự chọn mình
        if ($_SESSION['role_name'] === 'Student' && isset($_SESSION['student_id'])) {
            $selected_student_id = $_SESSION['student_id'];
        }

        $students = $this->studentModel->getAll('', '', 1, 1000); // Lấy tất cả
        $fee_types = $this->feeTypeModel->getActive();
        $payment_methods = $this->paymentModel->getPaymentMethods();

        $student_debts = [];

        if ($selected_student_id) {
            $student_debts = $this->studentModel->getDebts($selected_student_id);
        }

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'students' => $students,
                    'fee_types' => $fee_types,
                    'payment_methods' => $payment_methods,
                    'selected_student_id' => $selected_student_id,
                    'student_debts' => $student_debts
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý thu phí
     */
    public function store()
    {
        check_permission(['Accountant', 'Teacher', 'Student']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=payment&action=index');
            exit();
        }

        if (isset($_POST['is_multi']) && $_POST['is_multi']) {
            return $this->multiStore();
        }

        // Setup data
        $this->paymentModel->student_id = $_POST['student_id'];

        // set status Pending
        if ($_SESSION['role_name'] === 'Student') {
            if (isset($_SESSION['student_id'])) {
                $this->paymentModel->student_id = $_SESSION['student_id'];
            }
            $this->paymentModel->status = 'Pending';
        } else {
            $this->paymentModel->status = 'Completed';
        }

        $this->paymentModel->fee_type_id = $_POST['fee_type_id'];
        $this->paymentModel->amount_paid = floatval($_POST['amount_paid']);
        $this->paymentModel->payment_date = $_POST['payment_date'];
        $this->paymentModel->payment_method = $_POST['payment_method'];
        $this->paymentModel->collected_by = $_SESSION['user_id'];
        $this->paymentModel->receipt_number = clean_input($_POST['receipt_number']);
        $this->paymentModel->notes = clean_input($_POST['notes']);
        

        
        $errors = $this->validatePayment();

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: index.php?controller=payment&action=create');
            exit();
        }

        $result = $this->paymentModel->create();

        if ($result['success']) {
            $this->auditLog->log($_SESSION['user_id'], 'CREATE_PAYMENT', 'payment', $result['id'], "Mã phiếu: {$result['payment_code']}, Số tiền: {$this->paymentModel->amount_paid}");
            set_flash('success', $result['message'], 'success');
            header('Location: index.php?controller=payment&action=receipt&id=' . $result['id']);
            exit();
        } else {
            set_flash('error', $result['message'], 'danger');
            header('Location: index.php?controller=payment&action=create');
            exit();
        }
    }

    /**
     * Xử lý thu nhiều phí cùng lúc
     */
    public function multiStore()
    {
        check_permission(['Accountant']);

        $student_id = $_POST['student_id'];
        $fee_type_ids = $_POST['fee_type_ids'] ?? [];
        $amounts = $_POST['amounts'] ?? [];

        if (empty($fee_type_ids)) {
            set_flash('error', 'Vui lòng chọn ít nhất một khoản thu!', 'danger');
            header('Location: index.php?controller=payment&action=create&student_id=' . $student_id);
            exit();
        }

        $payments_data = [];
        foreach ($fee_type_ids as $ft_id) {
            if (isset($amounts[$ft_id]) && $amounts[$ft_id] > 0) {
                $payments_data[] = [
                    'fee_type_id' => $ft_id,
                    'amount_paid' => floatval($amounts[$ft_id])
                ];
            }
        }

        $common_data = [
            'payment_date' => $_POST['payment_date'],
            'payment_method' => $_POST['payment_method'],
            'collected_by' => $_SESSION['user_id'],
            'receipt_number' => clean_input($_POST['receipt_number']),
            'notes' => clean_input($_POST['notes']),
            'status' => 'Completed'
        ];

        $result = $this->paymentModel->createMultiple($student_id, $payments_data, $common_data);

        if ($result['success']) {
            $this->auditLog->log($_SESSION['user_id'], 'CREATE_MULTI_PAYMENT', 'student', $student_id, "Thu " . count($payments_data) . " khoản phí");
            set_flash('success', $result['message'], 'success');
            header('Location: index.php?controller=student&action=view&id=' . $student_id);
        } else {
            set_flash('error', $result['message'], 'danger');
            header('Location: index.php?controller=payment&action=create&student_id=' . $student_id);
        }
        exit();
    }

    public function receipt()
    {
        check_permission(['Accountant', 'Teacher', 'Student']);

        $id = $_GET['id'] ?? 0;
        $payment = $this->paymentModel->getById($id);

        if (!$payment) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Receipt not found'], 404);
            }
            die("Receipt not found");
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['payment' => $payment]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    public function view()
    {
        check_permission(['Accountant', 'Student']);

        $id = $_GET['id'] ?? 0;
        $payment = $this->paymentModel->getById($id);

        if (!$payment) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Payment not found'], 404);
            }
            die("Payment not found");
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['payment' => $payment]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xóa thanh toán (chỉ Admin)
     */
    public function delete()
    {
        check_permission(['Accountant']);

        $id = $_GET['id'] ?? 0;
        $result = $this->paymentModel->delete($id);

        if ($result['success']) {
            $this->auditLog->log($_SESSION['user_id'], 'DELETE_PAYMENT', 'payment', $id, "Xóa thanh toán ID: $id");
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=payment&action=index');
        exit();
    }

    public function refund()
    {
        check_permission(['Accountant']);

        $id = $_GET['id'] ?? 0;
        $payment = $this->paymentModel->getById($id);

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['payment' => $payment]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Danh sách hoàn tiền
     */
    public function refunds()
    {
        check_permission(['Accountant']);
        require_once __DIR__ . '/../models/Refund.php';
        $refundModel = new Refund();

        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        $total = $refundModel->countAll();
        $pagination = paginate($total, $per_page, $page);
        $refunds = $refundModel->getAll($page, $per_page);

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'refunds' => $refunds,
                    'pagination' => $pagination
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Danh sách nợ của tôi (Student role)
     */
    public function myDebts()
    {
        check_permission(['Student']);

        $student_id = $_SESSION['student_id'] ?? 0;

        $database = new Database();
        $conn = $database->connect();

        // Lấy danh sách nợ kèm thông tin học kỳ
        $stmt = $conn->prepare("
            SELECT sd.*, ft.fee_name, ft.fee_category, ft.academic_year, ft.semester,
                   (sd.total_amount - sd.paid_amount) as remaining_amount,
                   (SELECT COUNT(*) FROM payment_proofs pp 
                    WHERE pp.student_id = sd.student_id 
                    AND pp.fee_type_id = sd.fee_type_id 
                    AND pp.status = 'Pending') as has_pending_proof
            FROM student_debts sd
            INNER JOIN fee_types ft ON sd.fee_type_id = ft.id
            WHERE sd.student_id = :student_id AND sd.status != 'Paid'
            ORDER BY ft.academic_year DESC, ft.semester DESC, sd.due_date ASC
        ");
        $stmt->execute(['student_id' => $student_id]);
        $raw_debts = $stmt->fetchAll();

        // Group debts by Academic Year and Semester
        $grouped_debts = [];
        foreach ($raw_debts as $debt) {
            $year = $debt['academic_year'] ?: 'Năm học khác';
            $sem = $debt['semester'] ? 'Học kỳ ' . $debt['semester'] : 'Học kỳ khác';

            if (!isset($grouped_debts[$year])) {
                $grouped_debts[$year] = [];
            }
            if (!isset($grouped_debts[$year][$sem])) {
                $grouped_debts[$year][$sem] = [];
            }
            $grouped_debts[$year][$sem][] = $debt;
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['grouped_debts' => $grouped_debts]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    public function paymentMethod()
    {
        check_permission(['Student']);

        $fee_type_id = $_GET['fee_type_id'] ?? 0;
        $amount = $_GET['amount'] ?? 0;
        $fee_name = urldecode($_GET['fee_name'] ?? '');

        // Load settings for bank info
        $config_path = __DIR__ . '/../config/settings.json';
        $settings = [];
        if (file_exists($config_path)) {
            $settings = json_decode(file_get_contents($config_path), true);
        }

        $bank_info = [
            'bank_name' => $settings['bank_name'] ?? 'MB Bank',
            'bank_id' => $settings['bank_id'] ?? 'MB',
            'account_no' => $settings['account_no'] ?? '0000000000',
            'account_name' => $settings['account_name'] ?? 'TRUONG HOC ABC'
        ];

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'fee_type_id' => $fee_type_id,
                    'amount' => $amount,
                    'fee_name' => $fee_name,
                    'bank_info' => $bank_info
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * API: Lấy công nợ học sinh (AJAX)
     */
    public function getStudentDebts()
    {
        check_permission(['Accountant']);

        header('Content-Type: application/json');

        $student_id = $_GET['student_id'] ?? 0;

        if (empty($student_id)) {
            echo json_encode(['success' => false, 'message' => 'Không có student_id']);
            exit();
        }

        $debts = $this->studentModel->getDebts($student_id);

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => $debts]);
        }

        echo json_encode([
            'success' => true,
            'debts' => $debts
        ]);
        exit();
    }

    /**
     * Validate
     */
    private function validatePayment()
    {
        $errors = [];

        if (empty($this->paymentModel->student_id)) {
            $errors['student_id'] = 'Vui lòng chọn học sinh!';
        }

        if (empty($this->paymentModel->fee_type_id)) {
            $errors['fee_type_id'] = 'Vui lòng chọn khoản thu!';
        }

        if (empty($this->paymentModel->amount_paid) || $this->paymentModel->amount_paid <= 0) {
            $errors['amount_paid'] = 'Vui lòng nhập số tiền hợp lệ!';
        }

        if (empty($this->paymentModel->payment_date)) {
            $errors['payment_date'] = 'Vui lòng chọn ngày thanh toán!';
        }

        if (empty($this->paymentModel->payment_method)) {
            $errors['payment_method'] = 'Vui lòng chọn phương thức thanh toán!';
        }

        return $errors;
    }
    public function uploadProof()
    {
        check_permission(['Student']);

        $fee_type_id = $_GET['fee_type_id'] ?? 0;
        $amount = $_GET['amount'] ?? 0;
        $fee_name = urldecode($_GET['fee_name'] ?? '');

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['fee_type_id' => $fee_type_id, 'amount' => $amount, 'fee_name' => $fee_name]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý lưu minh chứng
     */
    public function storeProof()
    {
        check_permission(['Student']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=payment&action=myDebts');
            exit();
        }

        $fee_type_id = $_POST['fee_type_id'] ?? 0;
        $amount = $_POST['amount'] ?? 0;

        if (!$fee_type_id || !$amount) {
            set_flash('error', 'Dữ liệu không hợp lệ! Vui lòng thử lại.');
            header('Location: index.php?controller=payment&action=myDebts');
            exit();
        }

        require_once __DIR__ . '/../models/PaymentProof.php';
        $proofModel = new PaymentProof();

        // Handle File Upload
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $filename = $_FILES['proof_image']['name'];
            $filetype = $_FILES['proof_image']['type'];
            $filesize = $_FILES['proof_image']['size'];

            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), $allowed)) {
                set_flash('error', 'Chỉ chấp nhận file ảnh (JPG, PNG) hoặc PDF!');
                $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?controller=payment&action=myDebts';
                header('Location: ' . $referer);
                exit();
            }

            // Create uploads directory if not exists
            $upload_dir = __DIR__ . '/../uploads/proofs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'proof_' . time() . '_' . $_SESSION['student_id'] . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $destination)) {
                $proofModel->student_id = $_SESSION['student_id'];
                $proofModel->fee_type_id = $_POST['fee_type_id'];
                $proofModel->amount = $_POST['amount'];
                $proofModel->image_path = 'uploads/proofs/' . $new_filename;

                $result = $proofModel->create();

                if ($result['success']) {
                    set_flash('success', 'Đã gửi minh chứng thành công! Kế toán sẽ duyệt sớm.');
                    header('Location: index.php?controller=payment&action=myDebts');
                } else {
                    set_flash('error', $result['message']);
                    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?controller=payment&action=myDebts';
                    header('Location: ' . $referer);
                }
            } else {
                error_log("Upload Error: move_uploaded_file failed to " . $destination);
                set_flash('error', 'Lỗi khi upload file vào thư mục đích!');
                $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?controller=payment&action=myDebts';
                header('Location: ' . $referer);
            }
        } else {
            $err_code = $_FILES['proof_image']['error'] ?? 'No file';
            error_log("Upload Error - Code: " . $err_code);
            set_flash('error', 'Vui lòng chọn file minh chứng hợp lệ! (Lỗi: ' . $err_code . ')');
            $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?controller=payment&action=myDebts';
            header('Location: ' . $referer);
        }
        exit();
    }
    /**
     * Quản lý minh chứng (Admin/Accountant)
     */
    public function manageProofs()
    {
        check_permission(['Accountant']);

        require_once __DIR__ . '/../models/PaymentProof.php';
        $proofModel = new PaymentProof();

        $status = $_GET['status'] ?? 'Pending';
        $proofs = $proofModel->getAll('', $status);

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['proofs' => $proofs, 'status' => $status]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Duyệt minh chứng
     */
    public function approveProof()
    {
        check_permission(['Accountant']);

        $id = $_GET['id'] ?? 0;

        require_once __DIR__ . '/../models/PaymentProof.php';
        $proofModel = new PaymentProof();
        $proof = $proofModel->getById($id);

        if (!$proof || $proof['status'] != 'Pending') {
            set_flash('error', 'Minh chứng không tồn tại hoặc đã được xử lý!');
            header('Location: index.php?controller=payment&action=manageProofs');
            exit();
        }

        // 1. Tạo thanh toán
        $this->paymentModel->student_id = $proof['student_id'];
        $this->paymentModel->fee_type_id = $proof['fee_type_id'];
        $this->paymentModel->amount_paid = $proof['amount'];
        $this->paymentModel->payment_date = date('Y-m-d H:i:s');
        $this->paymentModel->payment_method = 'Transfer'; // Chuyển khoản
        $this->paymentModel->collected_by = $_SESSION['user_id'];
        $this->paymentModel->receipt_number = 'CK-' . $proof['id']; // Mã biên lai tự sinh
        $this->paymentModel->notes = 'Duyệt minh chứng chuyển khoản #' . $proof['id'];
        $this->paymentModel->status = 'Completed';

        $result = $this->paymentModel->create();

        if ($result['success']) {
            // 2. Cập nhật status minh chứng
            $proofModel->updateStatus($id, 'Approved', 'Đã duyệt và tạo phiếu thu: ' . $result['payment_code']);

            $this->auditLog->log($_SESSION['user_id'], 'APPROVE_PROOF', 'payment_proof', $id, "Duyệt minh chứng #$id, tạo phiếu thu " . $result['payment_code']);

            set_flash('success', 'Đã duyệt minh chứng và tạo phiếu thu thành công!');
        } else {
            set_flash('error', 'Lỗi khi tạo phiếu thu: ' . $result['message']);
        }

        header('Location: index.php?controller=payment&action=manageProofs');
        exit();
    }

    /**
     * Từ chối minh chứng
     */
    public function rejectProof()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=payment&action=manageProofs');
            exit();
        }

        $id = $_POST['id'];
        $reason = $_POST['reason'] ?? '';

        require_once __DIR__ . '/../models/PaymentProof.php';
        $proofModel = new PaymentProof();
        $proofModel->updateStatus($id, 'Rejected', $reason);

        $this->auditLog->log($_SESSION['user_id'], 'REJECT_PROOF', 'payment_proof', $id, "Từ chối minh chứng #$id. Lý do: $reason");

        set_flash('success', 'Đã từ chối minh chứng!');
        header('Location: index.php?controller=payment&action=manageProofs');
        exit();
    }
}
?>
