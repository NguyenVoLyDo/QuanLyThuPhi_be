<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/ClassModel.php';

class TeacherController
{
    private $userModel;
    private $classModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->classModel = new ClassModel();
    }

    /**
     * Danh sách Giáo viên
     */
    public function index()
    {
        check_permission(['Admin']);

        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = 10;

        // Custom query to get only teachers and their classes
        $database = new Database();
        $conn = $database->connect();

        $offset = ($page - 1) * $per_page;
        $sql = "SELECT u.*, GROUP_CONCAT(c.class_name SEPARATOR ', ') as classes
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN classes c ON c.teacher_id = u.id
                WHERE r.role_name = 'Teacher'";

        if (!empty($search)) {
            $sql .= " AND (u.username LIKE :search1 OR u.full_name LIKE :search2)";
        }

        $sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT :offset, :per_page";

        $stmt = $conn->prepare($sql);
        if (!empty($search)) {
            $search_param = "%$search%";
            $stmt->bindValue(':search1', $search_param);
            $stmt->bindValue(':search2', $search_param);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        $teachers = $stmt->fetchAll();

        // Count for pagination
        $sqlCount = "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'Teacher'";
        if (!empty($search))
            $sqlCount .= " AND (u.username LIKE :search1 OR u.full_name LIKE :search2)";
        $stmtCount = $conn->prepare($sqlCount);
        if (!empty($search)) {
            $stmtCount->bindValue(':search1', $search_param);
            $stmtCount->bindValue(':search2', $search_param);
        }
        $stmtCount->execute();
        $total = $stmtCount->fetch()['total'];

        $pagination = paginate($total, $per_page, $page);

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['teachers' => $teachers, 'pagination' => $pagination]]);
        }

        require_once __DIR__ . '/../../frontend/views/teachers/index.php';
    }

    /**
     * Form thêm giáo viên
     */
    public function create()
    {
        check_permission(['Admin']);
        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => []]);
        }
        $classes = $this->classModel->getAll();
        require_once __DIR__ . '/../../frontend/views/teachers/create.php';
    }

    /**
     * Lưu giáo viên
     */
    public function store()
    {
        check_permission(['Admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=teacher&action=index');
            exit();
        }

        $this->userModel->username = clean_input($_POST['username']);
        $this->userModel->password = $_POST['password'];
        $this->userModel->full_name = clean_input($_POST['full_name']);
        $this->userModel->email = clean_input($_POST['email']);
        $this->userModel->phone = clean_input($_POST['phone']);
        $this->userModel->role_id = 3; // Role ID for Teacher
        $this->userModel->is_active = 1;

        $result = $this->userModel->create();

        if ($result['success']) {
            $teacher_id = $result['id'];
            if (!empty($_POST['class_ids'])) {
                foreach ($_POST['class_ids'] as $class_id) {
                    $this->classModel->assignTeacher($class_id, $teacher_id);
                }
            }
            set_flash('success', 'Thêm giáo viên thành công!', 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=teacher&action=index');
        exit();
    }

    /**
     * Form sửa giáo viên
     */
    public function edit()
    {
        check_permission(['Admin']);
        $id = $_GET['id'] ?? 0;
        $teacher = $this->userModel->getById($id);

        if (!$teacher || $teacher['role_name'] !== 'Teacher') {
            set_flash('error', 'Không tìm thấy giáo viên!', 'danger');
            header('Location: index.php?controller=teacher&action=index');
            exit();
        }

        $classes = $this->classModel->getAll();
        $assigned_classes = $this->classModel->getClassIdsByTeacher($id);

        require_once __DIR__ . '/../../frontend/views/teachers/edit.php';
    }

    /**
     * Cập nhật giáo viên
     */
    public function update()
    {
        check_permission(['Admin']);

        $id = $_POST['id'];
        $teacher = $this->userModel->getById($id);

        $this->userModel->id = $id;
        $this->userModel->username = clean_input($_POST['username']);
        $this->userModel->full_name = clean_input($_POST['full_name']);
        $this->userModel->email = clean_input($_POST['email']);
        $this->userModel->phone = clean_input($_POST['phone']);
        $this->userModel->role_id = 3;
        $this->userModel->is_active = isset($_POST['is_active']) ? 1 : 0;
        $this->userModel->password = !empty($_POST['password']) ? $_POST['password'] : null;

        $result = $this->userModel->update();

        if ($result['success']) {
            // Clear current assignments (optional but good for clean state)
            $db = new Database();
            $conn = $db->connect();
            $conn->prepare("UPDATE classes SET teacher_id = NULL WHERE teacher_id = ?")->execute([$id]);

            if (!empty($_POST['class_ids'])) {
                foreach ($_POST['class_ids'] as $class_id) {
                    $this->classModel->assignTeacher($class_id, $id);
                }
            }
            set_flash('success', 'Cập nhật giáo viên thành công!', 'success');
        } else {
            set_flash('error', $result['message'], 'danger');
        }

        header('Location: index.php?controller=teacher&action=index');
        exit();
    }

    /**
     * Xóa giáo viên
     */
    public function delete()
    {
        check_permission(['Admin']);
        $id = $_GET['id'] ?? 0;

        // Unlink from classes first
        $db = new Database();
        $conn = $db->connect();
        $conn->prepare("UPDATE classes SET teacher_id = NULL WHERE teacher_id = ?")->execute([$id]);

        $result = $this->userModel->delete($id);
        if ($result['success']) {
            set_flash('success', 'Xóa giáo viên thành công!', 'success');
        } else {
            set_flash('error', 'Không thể xóa giáo viên này!', 'danger');
        }

        header('Location: index.php?controller=teacher&action=index');
        exit();
    }
}
