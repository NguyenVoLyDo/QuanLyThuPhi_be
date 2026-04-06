<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/FeeType.php';

class AuthController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function showLogin()
    {
        if (defined('API_MODE')) {
            json_response(['success' => true, 'message' => 'Login required for access'], 200);
        }
        // Fallback for direct backend access
        die("Backend API - please use frontend for UI.");
    }

    public function login()
    {
        // chuyển vào dashboard
        if (isset($_SESSION['user_id'])) {
            if (defined('API_MODE')) {
                json_response(['success' => true, 'redirect' => 'index.php?action=dashboard']);
            }
            header("Location: index.php?action=dashboard");
            exit;
        }

        // Xử lý submit form
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            // Query kiểm tra user
            $query = "SELECT u.*, r.role_name 
                      FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      WHERE u.username = :username AND u.is_active = 1 
                      LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra password
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role'] = $user['role_name'] ?? 'User';
                $_SESSION['role_name'] = $user['role_name'] ?? 'User';
                $_SESSION['full_name'] = $user['full_name'];
                if (!empty($user['student_id'])) {
                    $_SESSION['student_id'] = $user['student_id'];
                }

                // lấy thông tin lớp chủ nhiệm
                if (($user['role_name'] ?? '') === 'Teacher') {
                    // Fetch all assigned class IDs
                    $stmtClass = $this->db->prepare("SELECT id FROM classes WHERE teacher_id = :tid");
                    $stmtClass->execute(['tid' => $user['id']]);
                    $classes = $stmtClass->fetchAll(PDO::FETCH_COLUMN);
                    $_SESSION['teacher_class_ids'] = $classes ?: []; // Store as array
                    if (!empty($classes)) {
                        $_SESSION['teacher_class_id'] = $classes[0];
                    }
                }

                if (defined('API_MODE')) {
                    json_response([
                        'success' => true, 
                        'message' => 'Login successful', 
                        'user' => $user,
                        'redirect' => 'index.php?action=dashboard'
                    ]);
                }
                
                header("Location: index.php?action=dashboard");
                exit;
            } else {
                if (defined('API_MODE')) {
                    json_response(['success' => false, 'message' => 'Invalid credentials'], 401);
                }
                $this->showLogin();
            }
        } else {
            $this->showLogin();
        }
    }

    /**
     * Hiển thị dashboard
     */
    public function dashboard()
    {
        // Kiểm tra đăng nhập
        if (!isset($_SESSION['user_id'])) {
            if (defined('API_MODE')) {
                json_response(['success' => false, 'message' => 'Login required', 'code' => 401], 401);
            }
            header("Location: index.php?action=login");
            exit;
        }

        $paymentModel = new Payment();
        $studentModel = new Student();
        $feeTypeModel = new FeeType();

        $stats = [];

        // Fetch stats based on role
        if (in_array($_SESSION['role_name'], ['Admin', 'Accountant'])) {
            $stats = [
                'total_students' => $studentModel->countAll(),
                'total_fee_types' => $feeTypeModel->countAll(),
                'total_revenue' => $paymentModel->getTotalRevenue()['total_revenue'] ?? 0,
                'unpaid_by_semester' => $studentModel->countUnpaidBySemester()
            ];
        } else if ($_SESSION['role_name'] === 'Teacher') {
            // Use class IDs from session
            $class_ids = $_SESSION['teacher_class_ids'] ?? [];
            if (empty($class_ids)) {
                $class_ids = [-1]; // No classes = no data
            }

            // Student count across all assigned classes
            $stats['total_students'] = $studentModel->countAll('', $class_ids);

            // Payment count using new signature
            $filters = ['class_id' => $class_ids];
            $viewer = ['role' => 'Teacher', 'class_id' => $class_ids];
            $stats['total_payments'] = $paymentModel->countAll($filters, $viewer);
        } else if ($_SESSION['role_name'] === 'Student' && isset($_SESSION['student_id'])) {
            $student_id = $_SESSION['student_id'];
            $debts = $studentModel->getDebts($student_id);
            $total_debt = 0;
            $unpaid_count = 0;
            foreach ($debts as $debt) {
                if ($debt['status'] != 'Paid') {
                    $total_debt += ($debt['total_amount'] - $debt['paid_amount']);
                    $unpaid_count++;
                }
            }
            $stats = [
                'total_debt' => $total_debt,
                'unpaid_count' => $unpaid_count
            ];
        }

        if (defined('API_MODE')) {
            json_response(['success' => true, 'data' => ['stats' => $stats]]);
        }
        $this->showLogin(); // Should not reach here if using API
    }

    /**
     * Đăng xuất
     */
    public function logout()
    {
        session_destroy();
        if (defined('API_MODE')) {
            json_response([
                'success' => true, 
                'message' => 'Logged out successfully',
                'redirect' => 'index.php?controller=auth&action=login'
            ]);
        }
        header("Location: index.php?action=login");
        exit;
    }
}
?>
