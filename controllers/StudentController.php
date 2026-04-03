<?php
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/ClassModel.php';

class StudentController
{
    private $studentModel;

    public function __construct()
    {
        $this->studentModel = new Student();
    }

    /**
     * Danh sách học sinh
     */
    public function index()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);

        $search = $_GET['search'] ?? '';
        $class_id = $_GET['class_id'] ?? '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        // Teacher restriction
        if (strcasecmp(trim($_SESSION['role_name']), 'Teacher') === 0) {
            $classModel = new ClassModel();
            $teacherClassIds = $classModel->getClassIdsByTeacher($_SESSION['user_id']);
            // Normalize IDs to strings for comparison
            $validIds = array_map('strval', $teacherClassIds);

            // If teacher selects a specific class, check if it's assigned to them
            if (!empty($_GET['class_id']) && in_array((string) $_GET['class_id'], $validIds)) {
                $class_id = $_GET['class_id'];
            } else {
                // Otherwise show all assigned classes
                $class_id = $teacherClassIds; // Keep original IDs (likely ints) for query

                // If teacher has no classes, ensure they see nothing (pass dummy ID)
                if (empty($class_id)) {
                    $class_id = [-1];
                }
            }
        }

        $total = $this->studentModel->countAll($search, $class_id);
        $pagination = paginate($total, $per_page, $page);

        $students = $this->studentModel->getAll($search, $class_id, $page, $per_page);
        $classes = $this->studentModel->getClasses();

        // Filter classes for Teacher (only show assigned classes in dropdown)
        $role_name = $_SESSION['role_name'] ?? $_SESSION['role'] ?? '';
        if (strcasecmp(trim($role_name), 'Teacher') === 0 && isset($teacherClassIds)) {
            $classes = array_filter($classes, function ($c) use ($teacherClassIds) {
                return in_array($c['id'], $teacherClassIds);
            });

            if (empty($teacherClassIds)) {
                set_flash('warning', 'Bạn chưa được phân công chủ nhiệm lớp nào. Vui lòng liên hệ Admin.', 'warning');
            }
        }

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'students' => $students,
                    'classes' => array_values($classes), 
                    'pagination' => $pagination
                ]
            ]);
        }
        
        // Fallback for direct backend access
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Export Excel (CSV)
     */
    public function export()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);

        $search = $_GET['search'] ?? '';
        $class_id = $_GET['class_id'] ?? '';

        // Teacher restriction
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

        // Get all students (limit large enough)
        $students = $this->studentModel->getAll($search, $class_id, 1, 100000);

        // Filename
        $filename = 'DanhSachHocSinh_' . date('YmdHis') . '.csv';

        // Clear buffer to avoid garbage
        if (ob_get_level())
            ob_end_clean();

        // Headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Output
        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8
        fputs($output, "\xEF\xBB\xBF");

        // Header row
        fputcsv($output, ['STT', 'Mã HS', 'Họ tên', 'Ngày sinh', 'Giới tính', 'Lớp', 'Phụ huynh', 'SĐT', 'Trạng thái']);

        $stt = 1;
        foreach ($students as $s) {
            fputcsv($output, [
                $stt++,
                $s['student_code'],
                $s['full_name'],
                format_date($s['date_of_birth']),
                $s['gender'] == 'Male' ? 'Nam' : 'Nữ',
                $s['class_name'],
                $s['parent_name'],
                $s['parent_phone'],
                $s['is_active'] ? 'Đang học' : 'Nghỉ học'
            ]);
        }

        fclose($output);
        exit();
    }

    /**
     * Hiển thị form thêm học sinh
     */
    public function create()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);
        $classes = $this->studentModel->getClasses();

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['classes' => array_values($classes)]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý thêm học sinh
     */
    public function store()
    {
        check_permission(['Admin', 'Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
            exit();
        }

        // Lấy dữ liệu từ form
        $this->studentModel->student_code = clean_input($_POST['student_code']);
        $this->studentModel->full_name = clean_input($_POST['full_name']);
        $this->studentModel->date_of_birth = $_POST['date_of_birth'];
        $this->studentModel->gender = $_POST['gender'];
        $this->studentModel->class_id = $_POST['class_id'];
        $this->studentModel->parent_name = clean_input($_POST['parent_name']);
        $this->studentModel->parent_phone = clean_input($_POST['parent_phone']);
        $this->studentModel->parent_email = clean_input($_POST['parent_email']);
        $this->studentModel->address = clean_input($_POST['address']);
        $this->studentModel->is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate
        $errors = $this->validateStudent();

        if (!empty($errors)) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
            }
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=create');
            exit();
        }

        // Thêm học sinh
        $result = $this->studentModel->create();

        if (defined('API_MODE')) {
            json_response($result);
        }

        if ($result['success']) {
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
        exit();
    }

    /**
     * Hiển thị form sửa học sinh
     */
    public function edit()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);

        $id = $_GET['id'] ?? 0;
        $student = $this->studentModel->getById($id);

        if (!$student) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Student not found'], 404);
            }
            die("Student not found");
        }

        $classes = $this->studentModel->getClasses();

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['student' => $student, 'classes' => array_values($classes)]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý cập nhật học sinh
     */
    public function update()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
            exit();
        }

        $this->studentModel->id = $_POST['id'];
        $this->studentModel->student_code = clean_input($_POST['student_code']);
        $this->studentModel->full_name = clean_input($_POST['full_name']);
        $this->studentModel->date_of_birth = $_POST['date_of_birth'];
        $this->studentModel->gender = $_POST['gender'];
        $this->studentModel->class_id = $_POST['class_id'];
        $this->studentModel->parent_name = clean_input($_POST['parent_name']);
        $this->studentModel->parent_phone = clean_input($_POST['parent_phone']);
        $this->studentModel->parent_email = clean_input($_POST['parent_email']);
        $this->studentModel->address = clean_input($_POST['address']);
        // $this->studentModel->notes = clean_input($_POST['notes'] ?? ''); // Notes handled separately to prevent overwrite
        $this->studentModel->is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate
        $errors = $this->validateStudent($this->studentModel->id);

        if (!empty($errors)) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
            }
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=edit&id=' . $this->studentModel->id);
            exit();
        }

        // Cập nhật
        $result = $this->studentModel->update();

        if (defined('API_MODE')) {
            json_response($result);
        }

        if ($result['success']) {
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        // Redirect back to view page for better UX
        header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=view&id=' . $this->studentModel->id);
        exit();
    }

    /**
     * Xóa học sinh
     */
    public function delete()
    {
        check_permission(['Admin']);

        $id = $_GET['id'] ?? 0;
        $result = $this->studentModel->delete($id);

        if (defined('API_MODE')) {
            json_response($result);
        }

        if ($result['success']) {
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
        exit();
    }

    /**
     * Xem chi tiết học sinh
     */
    public function view()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);
        $id = $_GET['id'] ?? 0;
        $student = $this->studentModel->getById($id);

        if (!$student) {
            set_flash('error', 'Không tìm thấy học sinh!', 'danger');
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
            exit();
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
            exit();
        }

        // Teacher restriction
        if ($_SESSION['role_name'] === 'Teacher') {
            $classModel = new ClassModel();
            $teacherClassIds = $classModel->getClassIdsByTeacher($_SESSION['user_id']);
            if (!in_array($student['class_id'], $teacherClassIds)) {
                set_flash('error', 'Bạn không có quyền xem học sinh này!', 'danger');
                header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
                exit();
            }
        }

        $debts = $this->studentModel->getDebts($id);
        $payments = $this->studentModel->getPaymentHistory($id);

        // Exemption data
        require_once __DIR__ . '/../models/Exemption.php';
        $exemptionModel = new Exemption();
        $studentExemptions = $exemptionModel->getStudentExemptions($id);
        $allExemptions = $exemptionModel->getAll('', 1, 100); // Get all for dropdown

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'student' => $student,
                    'debts' => $debts,
                    'payments' => $payments,
                    'exemptions' => $studentExemptions
                ]
            ]);
        }

        die("Backend API - please use frontend for UI.");
    }

    /**
     * Validate dữ liệu học sinh
     */
    private function validateStudent($exclude_id = null)
    {
        $errors = [];

        if (empty($this->studentModel->student_code)) {
            $errors['student_code'] = 'Vui lòng nhập mã học sinh!';
        }

        if (empty($this->studentModel->full_name)) {
            $errors['full_name'] = 'Vui lòng nhập họ tên!';
        }

        if (empty($this->studentModel->date_of_birth)) {
            $errors['date_of_birth'] = 'Vui lòng chọn ngày sinh!';
        }

        if (empty($this->studentModel->gender)) {
            $errors['gender'] = 'Vui lòng chọn giới tính!';
        }

        if (empty($this->studentModel->class_id)) {
            $errors['class_id'] = 'Vui lòng chọn lớp!';
        }

        return $errors;
    }

    /**
     * Hiển thị trang import Excel
     */
    public function import()
    {
        check_permission(['Admin', 'Accountant']);
        if (defined('API_MODE')) {
            json_response(['success' => true, 'message' => 'Upload CSV to processImport']);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý import Excel
     */
    public function processImport()
    {
        check_permission(['Admin', 'Accountant']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=import');
            exit();
        }

        if (!isset($_FILES['file']['name']) || $_FILES['file']['name'] == '') {
            set_flash('error', 'Vui lòng chọn file!', 'danger');
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=import');
            exit();
        }

        $allowed_extensions = array('csv');
        $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($extension), $allowed_extensions)) {
            set_flash('error', 'Chỉ chấp nhận file CSV!', 'danger');
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=import');
            exit();
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if ($handle === false) {
            set_flash('error', 'Không thể mở file!', 'danger');
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=import');
            exit();
        }

        // Get all classes for mapping
        $classes = $this->studentModel->getClasses();
        $classMap = [];
        foreach ($classes as $c) {
            $classMap[strtolower(trim($c['class_name']))] = $c['id'];
        }

        // Process CSV
        $count = 0;
        $errors = [];
        $row = 0;

        // Skip header row
        $headers = fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;

            // Skip empty rows
            if (count($data) < 5 || empty(trim($data[0]))) {
                continue;
            }

            // Expected order: Student Code, Full Name, DOB, Gender, Class Name, Parent Name, Phone, Email, Address
            $student_code = trim($data[0]);
            $full_name = trim($data[1]);
            $dob_input = trim($data[2]); // Format DD/MM/YYYY
            $gender_input = trim($data[3]);
            $class_name = trim($data[4]);
            $parent_name = isset($data[5]) ? trim($data[5]) : '';
            $phone = isset($data[6]) ? trim($data[6]) : '';
            $email = isset($data[7]) ? trim($data[7]) : '';
            $address = isset($data[8]) ? trim($data[8]) : '';

            // Validate required fields
            if (empty($student_code) || empty($full_name)) {
                $errors[] = "Dòng $row: Thiếu Mã HS hoặc Họ tên.";
                continue;
            }

            // Normalize gender - accept both Vietnamese and English
            $gender = 'Male'; // Default
            $gender_lower = strtolower($gender_input);
            if (in_array($gender_lower, ['nữ', 'nu', 'female', 'f'])) {
                $gender = 'Female';
            } elseif (in_array($gender_lower, ['nam', 'male', 'm'])) {
                $gender = 'Male';
            }

            // Convert date from DD/MM/YYYY to YYYY-MM-DD for MySQL
            $dob = '';
            if (!empty($dob_input)) {
                // Check if format is DD/MM/YYYY or DD/MM/YY
                $date_parts = explode('/', $dob_input);
                if (count($date_parts) == 3) {
                    $day = (int) $date_parts[0];
                    $month = (int) $date_parts[1];
                    $year = (int) $date_parts[2];

                    // Handle 2-digit year (YY -> YYYY)
                    if ($year < 100) {
                        $year += ($year <= 50) ? 2000 : 1900;
                    }

                    // Validate date
                    if (checkdate($month, $day, $year)) {
                        $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    } else {
                        $errors[] = "Dòng $row: Ngày sinh không hợp lệ '$dob_input'. Định dạng đúng: DD/MM/YYYY (VD: 15/03/2010)";
                        continue;
                    }
                } else {
                    $errors[] = "Dòng $row: Ngày sinh không hợp lệ '$dob_input'. Định dạng đúng: DD/MM/YYYY (VD: 15/03/2010)";
                    continue;
                }
            }

            // Find class ID
            $class_id = $classMap[strtolower($class_name)] ?? null;
            if (!$class_id) {
                $errors[] = "Dòng $row: Không tìm thấy lớp '$class_name'.";
                continue;
            }

            // Check duplicate
            if ($this->studentModel->codeExists($student_code)) {
                $errors[] = "Dòng $row: Mã học sinh '$student_code' đã tồn tại.";
                continue;
            }

            // Prepare data for model
            $this->studentModel->student_code = $student_code;
            $this->studentModel->full_name = $full_name;
            $this->studentModel->date_of_birth = $dob;
            $this->studentModel->gender = $gender;
            $this->studentModel->class_id = $class_id;
            $this->studentModel->parent_name = $parent_name;
            $this->studentModel->parent_phone = $phone;
            $this->studentModel->parent_email = $email;
            $this->studentModel->address = $address;
            $this->studentModel->is_active = 1;
            $this->studentModel->notes = 'Imported via CSV on ' . date('Y-m-d H:i:s');

            $result = $this->studentModel->create();
            if ($result['success']) {
                $count++;
            } else {
                $errors[] = "Dòng $row: " . $result['message'];
            }
        }

        fclose($handle);

        if ($count > 0) {
            $msg = "Đã nhập thành công $count học sinh.";
            if (!empty($errors)) {
                $msg .= " Tuy nhiên có " . count($errors) . " lỗi: <br>" . implode('<br>', array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $msg .= "<br>... và " . (count($errors) - 10) . " lỗi khác.";
                }
                set_flash('warning', $msg, 'warning');
            } else {
                set_flash('success', $msg, 'success');
            }
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=index');
        } else {
            $error_msg = 'Không nhập được học sinh nào.';
            if (!empty($errors)) {
                $error_msg .= ' <br>' . implode('<br>', array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $error_msg .= "<br>... và " . (count($errors) - 10) . " lỗi khác.";
                }
            }
            set_flash('error', $error_msg, 'danger');
            header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=import');
        }
        exit();
    }
    /**
     * Cập nhật ghi chú (Safe Action)
     */
    public function update_note()
    {
        check_permission(['Admin', 'Accountant', 'Teacher']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=student&action=index');
            exit;
        }

        $id = $_POST['id'];
        $notes = clean_input($_POST['notes']);

        $res = $this->studentModel->updateNote($id, $notes);

        // Reset user info to reload specific student page with message
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }

        // Redirect back to view page for better UX
        header('Location: /QuanLyThuPhi/backend/index.php?controller=student&action=view&id=' . $id);
        exit;
    }
}
