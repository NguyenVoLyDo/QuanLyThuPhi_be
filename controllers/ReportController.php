<?php
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Student.php';

class ReportController
{
    private $paymentModel;
    private $studentModel;

    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->studentModel = new Student();
    }

    /**
     * Trang báo cáo tổng quan
     */
    public function index()
    {
        check_permission(['Accountant', 'Teacher']);

        $from_date = $_GET['from_date'] ?? date('Y-m-01'); // Đầu tháng
        $to_date = $_GET['to_date'] ?? date('Y-m-d'); // Hôm nay

        $database = new Database();
        $conn = $database->connect();

        // Thống kê tổng quan
        $revenue = $this->paymentModel->getTotalRevenue($from_date, $to_date);

        // Thống kê theo loại phí
        $stmt = $conn->prepare("
            SELECT ft.fee_category, ft.fee_name,
                   COUNT(p.id) as payment_count,
                   SUM(p.amount_paid) as total_amount
            FROM payments p
            INNER JOIN fee_types ft ON p.fee_type_id = ft.id
            WHERE p.payment_date BETWEEN :from_date AND :to_date
            AND p.status = 'Completed'
            GROUP BY ft.fee_category, ft.fee_name
            ORDER BY total_amount DESC
        ");
        $stmt->execute(['from_date' => $from_date, 'to_date' => $to_date]);
        $revenue_by_category = $stmt->fetchAll();

        // Thống kê theo phương thức thanh toán
        $stmt = $conn->prepare("
            SELECT payment_method,
                   COUNT(*) as payment_count,
                   SUM(amount_paid) as total_amount
            FROM payments
            WHERE payment_date BETWEEN :from_date AND :to_date
            AND status = 'Completed'
            GROUP BY payment_method
        ");
        $stmt->execute(['from_date' => $from_date, 'to_date' => $to_date]);
        $revenue_by_method = $stmt->fetchAll();


        // Danh sách học sinh còn nợ (với tính năng tìm kiếm)
        $debt_search = $_GET['debt_search'] ?? '';
        $query = "
            SELECT s.student_code, s.full_name, c.class_name,
                   SUM(sd.total_amount - sd.paid_amount) as total_debt
            FROM student_debts sd
            INNER JOIN students s ON sd.student_id = s.id
            INNER JOIN classes c ON s.class_id = c.id
            WHERE sd.status != 'Paid'
        ";

        if ($debt_search) {
            $query .= " AND (s.full_name LIKE :debt_search OR s.student_code LIKE :debt_search)";
        }

        $query .= " GROUP BY s.id HAVING total_debt > 0 ORDER BY total_debt DESC LIMIT 50";

        $stmt = $conn->prepare($query);
        if ($debt_search) {
            $stmt->execute(['debt_search' => "%$debt_search%"]);
        } else {
            $stmt->execute();
        }
        $students_with_debt = $stmt->fetchAll();

        if (defined('API_MODE')) {
            json_response([
                'success' => true,
                'data' => [
                    'revenue' => $revenue,
                    'revenue_by_category' => $revenue_by_category,
                    'revenue_by_method' => $revenue_by_method,
                    'students_with_debt' => $students_with_debt,
                    'from_date' => $from_date,
                    'to_date' => $to_date
                ]
            ]);
        }
        die("Backend API - please use frontend for UI.");
    }

    /**
     * Export Excel - Danh sách thanh toán
     */
    /**
     * Export Excel/CSV - Danh sách thanh toán
     */
    public function exportPayments()
    {
        check_permission(['Accountant', 'Teacher']);

        $from_date = $_GET['from_date'] ?? '';
        $to_date = $_GET['to_date'] ?? '';

        // Teacher restriction
        $teacher_class_ids = null;
        if ($_SESSION['role_name'] === 'Teacher') {
            require_once __DIR__ . '/../models/ClassModel.php';
            $classModel = new ClassModel();
            $teacher_class_ids = $classModel->getClassIdsByTeacher($_SESSION['user_id']);
            if (empty($teacher_class_ids)) {
                $teacher_class_ids = [-1];
            }
        }

        // Prepare filters and viewer
        $filters = [
            'search' => '',
            'student_id' => '',
            'from_date' => $from_date,
            'to_date' => $to_date,
            'class_id' => '', 
            'fee_type_id' => '',
            'page' => 1,
            'per_page' => 100000
        ];

        $viewer = [
            'role' => $_SESSION['role_name'],
            'student_id' => null,
            'class_id' => $teacher_class_ids
        ];

        // Lấy dữ liệu
        $payments = $this->paymentModel->getAll($filters, $viewer);

        // Tên file
        $filename = 'BaoCaoThanhToan_' . date('YmdHis') . '.csv';

        // Headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Output
        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8
        fputs($output, "\xEF\xBB\xBF");

        // Header row
        fputcsv($output, ['STT', 'Mã phiếu', 'Mã HS', 'Họ tên', 'Lớp', 'Khoản thu', 'Số tiền', 'Ngày đóng', 'Phương thức', 'Người thu']);

        $stt = 1;
        foreach ($payments as $payment) {
            fputcsv($output, [
                $stt++,
                $payment['payment_code'],
                $payment['student_code'],
                $payment['student_name'],
                $payment['class_name'],
                $payment['fee_name'],
                $payment['amount_paid'],
                format_date($payment['payment_date']),
                $payment['payment_method'],
                $payment['collector_name']
            ]);
        }

        fclose($output);
        exit();
    }

    /**
     * Export Excel - Danh sách công nợ
     */
    /**
     * Export Excel/CSV - Danh sách công nợ
     */
    public function exportDebts()
    {
        check_permission(['Accountant', 'Teacher']);

        $database = new Database();
        $conn = $database->connect();

        $query = "
            SELECT s.student_code, s.full_name, c.class_name,
                   ft.fee_name, ft.fee_category,
                   sd.total_amount, sd.paid_amount, (sd.total_amount - sd.paid_amount) as remaining_amount,
                   sd.due_date, sd.status
            FROM student_debts sd
            INNER JOIN students s ON sd.student_id = s.id
            INNER JOIN classes c ON s.class_id = c.id
            INNER JOIN fee_types ft ON sd.fee_type_id = ft.id
            WHERE sd.status != 'Paid'
        ";

        // Teacher restriction
        $params = [];
        if ($_SESSION['role_name'] === 'Teacher') {
            require_once __DIR__ . '/../models/ClassModel.php';
            $classModel = new ClassModel();
            $class_ids = $classModel->getClassIdsByTeacher($_SESSION['user_id']);
            if (empty($class_ids))
                $class_ids = [-1];

            // Build IN clause
            $inQuery = implode(',', array_map(function ($k) {
                return ":class_id_$k";
            }, array_keys($class_ids)));
            $query .= " AND s.class_id IN ($inQuery)";

            foreach ($class_ids as $k => $id) {
                $params[":class_id_$k"] = $id;
            }
        }

        $query .= " ORDER BY s.class_id, s.student_code";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $debts = $stmt->fetchAll();

        // Tên file
        $filename = 'BaoCaoCongNo_' . date('YmdHis') . '.csv';

        // Headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Output
        $output = fopen('php://output', 'w');

        // BOM
        fputs($output, "\xEF\xBB\xBF");

        // Header row
        fputcsv($output, ['STT', 'Mã HS', 'Họ tên', 'Lớp', 'Khoản thu', 'Loại phí', 'Tổng tiền', 'Đã đóng', 'Còn nợ', 'Hạn đóng']);

        $stt = 1;
        foreach ($debts as $debt) {
            fputcsv($output, [
                $stt++,
                $debt['student_code'],
                $debt['full_name'],
                $debt['class_name'],
                $debt['fee_name'],
                $debt['fee_category'],
                $debt['total_amount'],
                $debt['paid_amount'],
                $debt['remaining_amount'],
                format_date($debt['due_date'])
            ]);
        }

        fclose($output);
        exit();
    }
}
?>
