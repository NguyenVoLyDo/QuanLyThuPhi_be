<?php
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../config/database.php';

class ClassController {
    private $classModel;
    
    public function __construct() {
        $this->classModel = new ClassModel();
    }
    
    public function index() {
        check_permission(['Admin', 'Accountant']);
        $classes = $this->classModel->getAll($_GET['search'] ?? '');
        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['classes' => $classes]]);
        }
        die("Backend API - please use frontend for UI.");
    }
    
    public function create() {
        check_permission(['Admin']);
        $teachers = $this->classModel->getTeachers();
        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['teachers' => $teachers]]);
        }
        die("Backend API - please use frontend for UI.");
    }
    
    public function store() {
        check_permission(['Admin']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->classModel->class_name = $_POST['class_name'];
            $this->classModel->grade_level = $_POST['grade_level'];
            $this->classModel->description = $_POST['description'] ?? null;
            $this->classModel->teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
            
            if ($this->classModel->create()) {
                if (defined('API_MODE')) json_response(['success' => true, 'message' => 'Thêm lớp thành công!']);
                set_flash('success', 'Thêm lớp thành công!');
                header("Location: index.php?controller=class&action=index");
                exit;
            } else {
                if (defined('API_MODE')) json_response(['success' => false, 'message' => 'Có lỗi xảy ra!'], 400);
                set_flash('error', 'Có lỗi xảy ra!');
                header("Location: index.php?controller=class&action=create");
                exit;
            }
        }
    }
    
    public function edit() {
        check_permission(['Admin']);
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header("Location: index.php?controller=class&action=index");
            exit;
        }
        $class = $this->classModel->getById($id);
        $teachers = $this->classModel->getTeachers();
        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['class' => $class, 'teachers' => $teachers]]);
        }
        die("Backend API - please use frontend for UI.");
    }
    
    public function update() {
        check_permission(['Admin']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->classModel->id = $_POST['id'];
            $this->classModel->class_name = $_POST['class_name'];
            $this->classModel->grade_level = $_POST['grade_level'];
            $this->classModel->description = $_POST['description'] ?? null;
            $this->classModel->teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
            
            if ($this->classModel->update()) {
                set_flash('success', 'Cập nhật thành công!');
                header("Location: index.php?controller=class&action=index");
                exit;
            } else {
                set_flash('error', 'Có lỗi xảy ra!');
                header("Location: index.php?controller=class&action=edit&id=" . $_POST['id']);
                exit;
            }
        }
    }
    
    public function delete() {
        check_permission(['Admin']);
        $id = $_GET['id'] ?? null;
        if ($id && $this->classModel->delete($id)) {
            set_flash('success', 'Xóa thành công!');
        } else {
            set_flash('error', 'Không thể xóa lớp đã có học sinh!');
        }
        header("Location: index.php?controller=class&action=index");
        exit;
    }
}
?>
