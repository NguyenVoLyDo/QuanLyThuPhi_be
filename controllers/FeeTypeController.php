<?php
require_once __DIR__ . '/../models/FeeType.php';
require_once __DIR__ . '/../models/AuditLog.php';

class FeeTypeController
{
    private $feeTypeModel;
    private $auditLog;

    public function __construct()
    {
        $this->feeTypeModel = new FeeType();
        $this->auditLog = new AuditLog();
    }

    /**
     * Danh sách khoản thu
     */
    public function index()
    {
        check_permission(['Accountant', 'Teacher']);

        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $year = $_GET['year'] ?? '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        $total = $this->feeTypeModel->countAll($search, $category, $year);
        $pagination = paginate($total, $per_page, $page);

        $fee_types = $this->feeTypeModel->getAll($search, $category, $year, $page, $per_page);
        $categories = $this->feeTypeModel->getCategories();

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'fee_types' => $fee_types,
                    'categories' => $categories,
                    'pagination' => $pagination,
                    'search' => $search,
                    'category' => $category,
                    'year' => $year
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Form thêm khoản thu
     */
    public function create()
    {
        check_permission(['Accountant']);

        $categories = $this->feeTypeModel->getCategories();
        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['categories' => $categories]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý thêm khoản thu
     */
    public function store()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=feetype&action=index');
            exit();
        }

        $this->feeTypeModel->fee_name = clean_input($_POST['fee_name']);
        $this->feeTypeModel->description = clean_input($_POST['description']);
        $this->feeTypeModel->amount = floatval($_POST['amount']);
        $this->feeTypeModel->fee_category = $_POST['fee_category'];
        $this->feeTypeModel->is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        $this->feeTypeModel->academic_year = clean_input($_POST['academic_year']);
        $this->feeTypeModel->semester = !empty($_POST['semester']) ? $_POST['semester'] : null;
        $this->feeTypeModel->semester = !empty($_POST['semester']) ? $_POST['semester'] : null;
        $this->feeTypeModel->start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $this->feeTypeModel->end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $this->feeTypeModel->status = $_POST['status'] ?? 'Active';
        $this->feeTypeModel->is_active = ($this->feeTypeModel->status === 'Active') ? 1 : 0;

        // Validate
        $errors = $this->validateFeeType();

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: index.php?controller=feetype&action=create');
            exit();
        }

        $result = $this->feeTypeModel->create();

        if ($result['success']) {
            $this->auditLog->log($_SESSION['user_id'], 'CREATE_FEE_TYPE', 'fee_type', $result['id'], "Tên: {$this->feeTypeModel->fee_name}, Số tiền: {$this->feeTypeModel->amount}");
            set_flash('success', $result['message'], 'success');

            // Nếu checkbox tạo công nợ được chọn
            if (isset($_POST['create_debt'])) {
                $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                $debt_result = $this->feeTypeModel->createDebtForAllStudents($result['id'], $due_date);

                if ($debt_result['success']) {
                    $this->auditLog->log($_SESSION['user_id'], 'CREATE_DEBT_ALL', 'fee_type', $result['id'], "Tạo công nợ cho tất cả học sinh");
                    set_flash('success', $debt_result['message'], 'info');
                }
            }
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=feetype&action=index');
        exit();
    }

    /**
     * Form sửa khoản thu
     */
    public function edit()
    {
        check_permission(['Accountant']);

        $id = $_GET['id'] ?? 0;
        $fee_type = $this->feeTypeModel->getById($id);

        if (!$fee_type) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Fee type not found'], 404);
            }
            die("Fee type not found");
        }

        $categories = $this->feeTypeModel->getCategories();
        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['fee_type' => $fee_type, 'categories' => $categories]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý cập nhật
     */
    public function update()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=feetype&action=index');
            exit();
        }

        $this->feeTypeModel->id = $_POST['id'];
        $this->feeTypeModel->fee_name = clean_input($_POST['fee_name']);
        $this->feeTypeModel->description = clean_input($_POST['description']);
        $this->feeTypeModel->amount = floatval($_POST['amount']);
        $this->feeTypeModel->fee_category = $_POST['fee_category'];
        $this->feeTypeModel->is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        $this->feeTypeModel->academic_year = clean_input($_POST['academic_year']);
        $this->feeTypeModel->semester = !empty($_POST['semester']) ? $_POST['semester'] : null;
        $this->feeTypeModel->semester = !empty($_POST['semester']) ? $_POST['semester'] : null;
        $this->feeTypeModel->start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $this->feeTypeModel->end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $this->feeTypeModel->status = $_POST['status'] ?? 'Active';
        $this->feeTypeModel->is_active = ($this->feeTypeModel->status === 'Active') ? 1 : 0;

        // Validate
        $errors = $this->validateFeeType($this->feeTypeModel->id);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: index.php?controller=feetype&action=edit&id=' . $this->feeTypeModel->id);
            exit();
        }

        $result = $this->feeTypeModel->update();

        if ($result['success']) {
            $this->auditLog->log($_SESSION['user_id'], 'UPDATE_FEE_TYPE', 'fee_type', $this->feeTypeModel->id, "Cập nhật khoản thu ID: {$this->feeTypeModel->id}");
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=feetype&action=index');
        exit();
    }

    /**
     * Xóa khoản thu
     */
    public function delete()
    {
        check_permission(['Accountant']);

        $id = $_GET['id'] ?? 0;
        $result = $this->feeTypeModel->delete($id);

        if ($result['success']) {
            $this->auditLog->log($_SESSION['user_id'], 'DELETE_FEE_TYPE', 'fee_type', $id, "Xóa khoản thu ID: $id");
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=feetype&action=index');
        exit();
    }

    /**
     * Validate
     */
    private function validateFeeType($exclude_id = null)
    {
        $errors = [];

        if (empty($this->feeTypeModel->fee_name)) {
            $errors['fee_name'] = 'Vui lòng nhập tên khoản thu!';
        }

        if (empty($this->feeTypeModel->amount) || $this->feeTypeModel->amount <= 0) {
            $errors['amount'] = 'Vui lòng nhập số tiền hợp lệ!';
        }

        if (empty($this->feeTypeModel->fee_category)) {
            $errors['fee_category'] = 'Vui lòng chọn loại khoản thu!';
        }

        if (empty($this->feeTypeModel->academic_year)) {
            $errors['academic_year'] = 'Vui lòng nhập năm học!';
        }

        if (empty($this->feeTypeModel->academic_year)) {
            $errors['academic_year'] = 'Vui lòng nhập năm học!';
        }

        if (!empty($this->feeTypeModel->start_date) && !empty($this->feeTypeModel->end_date)) {
            if ($this->feeTypeModel->end_date < $this->feeTypeModel->start_date) {
                $errors['end_date'] = 'Ngày kết thúc phải sau ngày bắt đầu!';
            }
        }

        return $errors;
    }

    /**
     * Hiển thị trang import Excel
     */
    public function import()
    {
        check_permission(['Accountant']);
        if (defined('API_MODE')) {
            json_response(['success' => true, 'message' => 'Upload CSV file to processImport action']);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý import Excel
     */
    public function processImport()
    {
        check_permission(['Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=feetype&action=import');
            exit();
        }

        if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != '') {
            $allowed_extensions = array('csv');
            $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

            if (!in_array(strtolower($extension), $allowed_extensions)) {
                set_flash('error', 'Chỉ chấp nhận file CSV!');
                header('Location: index.php?controller=feetype&action=import');
                exit();
            }

            $handle = fopen($_FILES['file']['tmp_name'], 'r');
            if ($handle === false) {
                set_flash('error', 'Không thể mở file!');
                header('Location: index.php?controller=feetype&action=import');
                exit();
            }

            // Valid Categories
            $validCategories = array_keys($this->feeTypeModel->getCategories());

            $count = 0;
            $errors = [];
            $row = 0;

            // Skip header
            $headers = fgetcsv($handle);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                // Expected: fee_name, amount, fee_category, academic_year, semester, is_mandatory, description
                if (count($data) < 4)
                    continue;

                $fee_name = trim($data[0]);
                $amount = floatval($data[1]);
                $fee_category = trim($data[2]);
                $academic_year = trim($data[3]);
                $semester = isset($data[4]) ? trim($data[4]) : null;
                $is_mandatory = isset($data[5]) ? (int) $data[5] : 0;
                $description = isset($data[6]) ? trim($data[6]) : '';

                if (empty($fee_name) || $amount <= 0 || empty($academic_year)) {
                    $errors[] = "Dòng $row: Thiếu Tên, Năm học hoặc Số tiền không hợp lệ.";
                    continue;
                }

                if (!in_array($fee_category, $validCategories)) {
                    $map = [
                        'Học phí' => 'Tuition',
                        'Tiền ăn' => 'Meal',
                        'Đồng phục' => 'Uniform',
                        'Hoạt động' => 'Activity',
                        'Khác' => 'Other'
                    ];
                    if (isset($map[$fee_category])) {
                        $fee_category = $map[$fee_category];
                    } else {
                        $fee_category = 'Other';
                    }
                }

                $this->feeTypeModel->fee_name = $fee_name;
                $this->feeTypeModel->description = $description;
                $this->feeTypeModel->amount = $amount;
                $this->feeTypeModel->fee_category = $fee_category;
                $this->feeTypeModel->is_mandatory = $is_mandatory;
                $this->feeTypeModel->academic_year = $academic_year;
                $this->feeTypeModel->semester = $semester;
                $this->feeTypeModel->is_active = 1;
                $this->feeTypeModel->status = 'Active';
                $this->feeTypeModel->start_date = null;
                $this->feeTypeModel->end_date = null;

                $result = $this->feeTypeModel->create();

                if ($result['success']) {
                    $count++;
                    $this->auditLog->log($_SESSION['user_id'], 'IMPORT_FEE_TYPE', 'fee_type', $result['id'], "Import Excel: $fee_name");
                } else {
                    $errors[] = "Dòng $row: " . $result['message'];
                }
            }

            fclose($handle);

            if ($count > 0) {
                $msg = "Đã nhập thành công $count khoản thu.";
                if (!empty($errors)) {
                    $msg .= " Có lỗi: <br>" . implode('<br>', $errors);
                    set_flash('warning', $msg);
                } else {
                    set_flash('success', $msg);
                }
                header('Location: index.php?controller=feetype&action=index');
            } else {
                set_flash('error', 'Không nhập được khoản thu nào.<br>' . implode('<br>', $errors));
                header('Location: index.php?controller=feetype&action=import');
            }
        } else {
            set_flash('error', 'Vui lòng chọn file!');
            header('Location: index.php?controller=feetype&action=import');
        }
        exit();
    }
}
?>
