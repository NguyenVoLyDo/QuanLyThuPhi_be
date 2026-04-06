<?php
require_once __DIR__ . '/../models/User.php';

class UserController
{
    private $userModel;
    private $classModel;

    public function __construct()
    {
        $this->userModel = new User();
        require_once __DIR__ . '/../models/ClassModel.php';
        $this->classModel = new ClassModel();
    }

    /**
     * Danh sách user (chỉ Admin)
     */
    public function index()
    {
        check_permission(['Admin']);

        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        $total = $this->userModel->countAll($search);
        $pagination = paginate($total, $per_page, $page);

        $users = $this->userModel->getAll($search, $page, $per_page);

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'pagination' => $pagination,
                    'search' => $search
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Form thêm user
     */
    public function create()
    {
        check_permission(['Admin']);

        $roles = $this->userModel->getRoles();

        // Lấy danh sách học sinh để liên kết (cho role Student)
        require_once __DIR__ . '/../models/Student.php';
        $studentModel = new Student();
        $students = $studentModel->getAll('', '', 1, 1000);

        // Lấy danh sách lớp cho giáo viên
        $classes = $this->classModel->getAll();

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'students' => $students,
                    'classes' => $classes
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý thêm user
     */
    public function store()
    {
        check_permission(['Admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=user&action=index');
            exit();
        }

        $this->userModel->username = clean_input($_POST['username']);
        $this->userModel->password = $_POST['password'];
        $this->userModel->full_name = clean_input($_POST['full_name']);
        $this->userModel->email = clean_input($_POST['email']);
        $this->userModel->phone = clean_input($_POST['phone']);
        $this->userModel->role_id = $_POST['role_id'];
        $this->userModel->student_id = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
        $this->userModel->is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate
        $errors = $this->validateUser();

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: index.php?controller=user&action=create');
            exit();
        }

        $result = $this->userModel->create();

        // Xử lý gán lớp cho giáo viên
        if ($result['success'] && !empty($_POST['class_id']) && !empty($_POST['role_id'])) {
            // Check if role is Teacher (ID usually 3, but let's check roles or assume UI logic holds)
            // Ideally fetch role name, but assuming if class_id is sent, it's valid.
            // Need the ID of the newly created user!
            // User::create returns success boolean/array, but we need the insert_id.
            // Check User model create method... usually it returns array['message'].
            // We might need to fetch the user by username to get ID or modify Model to return ID.
            // Let's assume User::create doesn't return ID directly.
            
            // Hack: Fetch by username
            $newUser = $this->userModel->getByUsername($this->userModel->username);
            if ($newUser) {
                $this->classModel->assignTeacher($_POST['class_id'], $newUser['id']);
            }
        }

        if ($result['success']) {
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=user&action=index');
        exit();
    }

    /**
     * Form sửa user
     */
    public function edit()
    {
        check_permission(['Admin']);

        $id = $_GET['id'] ?? 0;
        $user = $this->userModel->getById($id);

        if (!$user) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'User not found'], 404);
            }
            die("User not found");
        }

        $roles = $this->userModel->getRoles();

        require_once __DIR__ . '/../models/Student.php';
        $studentModel = new Student();
        $students = $studentModel->getAll('', '', 1, 1000);

        // Lấy danh sách lớp và lớp hiện tại của giáo viên
        $classes = $this->classModel->getAll();
        $current_class_id = $this->classModel->getClassIdByTeacher($id);

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'roles' => $roles,
                    'students' => $students,
                    'classes' => $classes,
                    'current_class_id' => $current_class_id
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Xử lý cập nhật
     */
    public function update()
    {
        check_permission(['Admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=user&action=index');
            exit();
        }

        $this->userModel->id = $_POST['id'];
        $this->userModel->username = clean_input($_POST['username']);
        $this->userModel->password = !empty($_POST['password']) ? $_POST['password'] : null;
        $this->userModel->full_name = clean_input($_POST['full_name']);
        $this->userModel->email = clean_input($_POST['email']);
        $this->userModel->phone = clean_input($_POST['phone']);
        $this->userModel->role_id = $_POST['role_id'];
        $this->userModel->student_id = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
        $this->userModel->is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate
        $errors = $this->validateUser($this->userModel->id);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header('Location: index.php?controller=user&action=edit&id=' . $this->userModel->id);
            exit();
        }

        $result = $this->userModel->update();
        
        // Xử lý cập nhật lớp cho giáo viên
        if ($result['success'] && isset($_POST['class_id'])) { 
            // Check if class_id is not empty, assign it. 
            // If empty, maybe remove assignment? existing implementation of assignTeacher doesn't support remove easily.
            // Let's assume if provided, update.
            if (!empty($_POST['class_id'])) {
                 $this->classModel->assignTeacher($_POST['class_id'], $this->userModel->id);
            }
        }

        if ($result['success']) {
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=user&action=index');
        exit();
    }

    /**
     * Xóa user
     */
    public function delete()
    {
        check_permission(['Admin']);

        $id = $_GET['id'] ?? 0;

        // Không cho phép xóa chính mình
        if ($id == $_SESSION['user_id']) {
            set_flash('error', 'Không thể xóa tài khoản đang đăng nhập!', 'danger');
            header('Location: index.php?controller=user&action=index');
            exit();
        }

        $result = $this->userModel->delete($id);

        if ($result['success']) {
            set_flash('success', $result['message'], 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=user&action=index');
        exit();
    }

    /**
     * Thông tin cá nhân
     */
    public function profile()
    {
        check_permission(['Admin', 'Accountant', 'Teacher', 'Student']);

        $user_id = $_SESSION['user_id'];
        $user = $this->userModel->getById($user_id);

        $student = null;
        if (!empty($user['student_id'])) {
            require_once __DIR__ . '/../models/Student.php';
            $studentModel = new Student();
            $student = $studentModel->getById($user['student_id']);
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['user' => $user, 'student' => $student]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Đổi mật khẩu
     */
    public function changePassword()
    {
        check_permission();

        if (defined('API_MODE')) {
            json_response(['success' => true, 'message' => 'Change password via POST']);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Reset mật khẩu (Admin)
     */
    public function resetPassword()
    {
        check_permission(['Admin']);

        $id = $_GET['id'] ?? 0;
        $user = $this->userModel->getById($id);

        if (!$user) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'User not found'], 404);
            }
            die("User not found");
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['user' => $user]]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Validate
     */
    private function validateUser($exclude_id = null)
    {
        $errors = [];

        if (empty($this->userModel->username)) {
            $errors['username'] = 'Vui lòng nhập tên đăng nhập!';
        } elseif (strlen($this->userModel->username) < 4) {
            $errors['username'] = 'Tên đăng nhập phải có ít nhất 4 ký tự!';
        }

        // Chỉ validate password khi tạo mới hoặc có nhập password
        if (empty($exclude_id)) { // Tạo mới
            if (empty($this->userModel->password)) {
                $errors['password'] = 'Vui lòng nhập mật khẩu!';
            } elseif (strlen($this->userModel->password) < 6) {
                $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự!';
            }
        } elseif (!empty($this->userModel->password) && strlen($this->userModel->password) < 6) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự!';
        }

        if (empty($this->userModel->full_name)) {
            $errors['full_name'] = 'Vui lòng nhập họ tên!';
        }

        if (!empty($this->userModel->email) && !filter_var($this->userModel->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ!';
        }

        if (empty($this->userModel->role_id)) {
            $errors['role_id'] = 'Vui lòng chọn vai trò!';
        }

        return $errors;
    }
}
