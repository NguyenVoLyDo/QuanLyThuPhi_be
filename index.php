<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/config/database.php';

$controller = $_GET['controller'] ?? '';
$action = $_GET['action'] ?? 'dashboard';

if (!isset($_SESSION['user_id']) && $action !== 'login') {
    $action = 'login';
    $controller = '';
}

// Route xử lý
try {
    switch ($controller) {
        case '':
            // Auth actions
            require_once __DIR__ . '/controllers/AuthController.php';
            $authController = new AuthController();

            switch ($action) {
                case 'login':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $authController->login();
                    } else {
                        $authController->showLogin();
                    }
                    break;

                case 'logout':
                    $authController->logout();
                    break;

                case 'dashboard':
                default:
                    $authController->dashboard();
                    break;
            }
            break;

        case 'student':
            // Student Controller
            require_once __DIR__ . '/controllers/StudentController.php';
            $studentController = new StudentController();

            switch ($action) {
                case 'index':
                    $studentController->index();
                    break;
                case 'export':
                    $studentController->export();
                    break;
                case 'create':
                    $studentController->create();
                    break;
                case 'store':
                    $studentController->store();
                    break;
                case 'edit':
                    $studentController->edit();
                    break;
                case 'update':
                    $studentController->update();
                    break;
                case 'delete':
                    $studentController->delete();
                    break;
                case 'view':
                    $studentController->view();
                    break;
                case 'import':
                    $studentController->import();
                    break;
                case 'processImport':
                    $studentController->processImport();
                    break;
                case 'update_note':
                    $studentController->update_note();
                    break;
                default:
                    $studentController->index();
                    break;
            }
            break;

        case 'feetype':
            // Fee Type Controller
            require_once __DIR__ . '/controllers/FeeTypeController.php';
            $feeTypeController = new FeeTypeController();

            switch ($action) {
                case 'index':
                    $feeTypeController->index();
                    break;
                case 'create':
                    $feeTypeController->create();
                    break;
                case 'store':
                    $feeTypeController->store();
                    break;
                case 'edit':
                    $feeTypeController->edit();
                    break;
                case 'update':
                    $feeTypeController->update();
                    break;
                case 'delete':
                    $feeTypeController->delete();
                    break;
                case 'import':
                    $feeTypeController->import();
                    break;
                case 'processImport':
                    $feeTypeController->processImport();
                    break;
                default:
                    $feeTypeController->index();
                    break;
            }
            break;

        case 'payment':
            // Payment Controller
            require_once __DIR__ . '/controllers/PaymentController.php';
            $paymentController = new PaymentController();

            switch ($action) {
                case 'index':
                    $paymentController->index();
                    break;
                case 'export':
                    $paymentController->export();
                    break;
                case 'create':
                    $paymentController->create();
                    break;
                case 'store':
                    $paymentController->store();
                    break;
                case 'receipt':
                    $paymentController->receipt();
                    break;
                case 'view':
                    $paymentController->view();
                    break;
                case 'delete':
                    $paymentController->delete();
                    break;
                case 'refund':
                    $paymentController->refund();
                    break;
                case 'refunds':
                    $paymentController->refunds();
                    break;
                case 'getStudentDebts':
                    $paymentController->getStudentDebts();
                    break;
                case 'myDebts':
                    $paymentController->myDebts();
                    break;
                case 'paymentMethod':
                    $paymentController->paymentMethod();
                    break;
                case 'uploadProof':
                    $paymentController->uploadProof();
                    break;
                case 'storeProof':
                    $paymentController->storeProof();
                    break;
                case 'manageProofs':
                    $paymentController->manageProofs();
                    break;
                case 'approveProof':
                    $paymentController->approveProof();
                    break;
                case 'rejectProof':
                    $paymentController->rejectProof();
                    break;
                default:
                    $paymentController->index();
                    break;
            }
            break;

        case 'report':
            // Report Controller
            require_once __DIR__ . '/controllers/ReportController.php';
            $reportController = new ReportController();

            switch ($action) {
                case 'index':
                    $reportController->index();
                    break;
                case 'exportPayments':
                    $reportController->exportPayments();
                    break;
                case 'exportDebts':
                    $reportController->exportDebts();
                    break;
                default:
                    $reportController->index();
                    break;
            }
            break;

        case 'user':
            // User Management
            require_once __DIR__ . '/controllers/UserController.php';
            $userController = new UserController();

            switch ($action) {
                case 'index':
                    $userController->index();
                    break;
                case 'create':
                    $userController->create();
                    break;
                case 'store':
                    $userController->store();
                    break;
                case 'edit':
                    $userController->edit();
                    break;
                case 'update':
                    $userController->update();
                    break;
                case 'delete':
                    $userController->delete();
                    break;
                case 'profile':
                    $userController->profile();
                    break;
                case 'changePassword':
                    $userController->changePassword();
                    break;
                case 'resetPassword':
                    $userController->resetPassword();
                    break;
                default:
                    $userController->index();
                    break;
            }
            break;

        case 'class':
            // Class Controller
            require_once __DIR__ . '/controllers/ClassController.php';
            $classController = new ClassController();

            switch ($action) {
                case 'index':
                    $classController->index();
                    break;
                case 'create':
                    $classController->create();
                    break;
                case 'store':
                    $classController->store();
                    break;
                case 'edit':
                    $classController->edit();
                    break;
                case 'update':
                    $classController->update();
                    break;
                case 'delete':
                    $classController->delete();
                    break;
                default:
                    $classController->index();
                    break;
            }
            break;

        case 'exemption':
            // Exemption Controller
            require_once __DIR__ . '/controllers/ExemptionController.php';
            $exemptionController = new ExemptionController();

            switch ($action) {
                case 'index':
                    $exemptionController->index();
                    break;
                case 'create':
                    $exemptionController->create();
                    break;
                case 'store':
                    $exemptionController->store();
                    break;
                case 'edit':
                    $exemptionController->edit();
                    break;
                case 'update':
                    $exemptionController->update();
                    break;
                case 'delete':
                    $exemptionController->delete();
                    break;
                case 'assign':
                    $exemptionController->assign();
                    break;
                case 'revoke':
                    $exemptionController->revoke();
                    break;
                default:
                    $exemptionController->index();
                    break;
            }
            break;

        case 'debt':
            // Debt Controller
            require_once __DIR__ . '/controllers/DebtController.php';
            $debtController = new DebtController();

            switch ($action) {
                case 'createBatch':
                    $debtController->createBatch();
                    break;
                case 'storeBatch':
                    $debtController->storeBatch();
                    break;
                default:
                    $debtController->createBatch();
                    break;
            }
            break;

        case 'auditlog':
            // Audit Log
            require_once __DIR__ . '/controllers/AuditLogController.php';
            $auditLogController = new AuditLogController();

            switch ($action) {
                case 'index':
                    $auditLogController->index();
                    break;
                case 'export':
                    $auditLogController->export();
                    break;
                default:
                    $auditLogController->index();
                    break;
            }
            break;

        case 'teacher':
            // Teacher Controller
            require_once __DIR__ . '/controllers/TeacherController.php';
            $teacherController = new TeacherController();

            switch ($action) {
                case 'index':
                    $teacherController->index();
                    break;
                case 'create':
                    $teacherController->create();
                    break;
                case 'store':
                    $teacherController->store();
                    break;
                case 'edit':
                    $teacherController->edit();
                    break;
                case 'update':
                    $teacherController->update();
                    break;
                case 'delete':
                    $teacherController->delete();
                    break;
                default:
                    $teacherController->index();
                    break;
            }
            break;

        case 'admin':
            // Admin Controller
            require_once __DIR__ . '/controllers/AdminController.php';
            $adminController = new AdminController();

            switch ($action) {
                case 'backup':
                    $adminController->backup();
                    break;
                case 'backupPage':
                    $adminController->backupPage();
                    break;
                case 'systemSettings':
                    $adminController->systemSettings();
                    break;
                default:
                    header('Location: index.php?action=dashboard');
                    break;
            }
            break;

        default:
            http_response_code(404);
            echo '<h1>404 - Không tìm thấy trang</h1>';
            echo '<a href="index.php">Về trang chủ</a>';
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
    echo '<h2>Đã xảy ra lỗi!</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><small>File: ' . $e->getFile() . ' (Line: ' . $e->getLine() . ')</small></p>';
    echo '<a href="index.php">Quay lại trang chủ</a>';
    echo '</div>';
    error_log("Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>