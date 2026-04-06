<?php
require_once __DIR__ . '/../config/database.php';

class Payment
{
    private $conn;
    private $table = 'payments';

    // Properties
    public $id;
    public $payment_code;
    public $student_id;
    public $fee_type_id;
    public $amount_paid;
    public $payment_date;
    public $payment_method;
    public $collected_by;
    public $receipt_number;
    public $notes;
    public $status;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Lấy tất cả thanh toán
     */
    private function applyRBAC(&$query, &$params, array $viewer)
    {
        if (!isset($viewer['role'])) {
            return; // No RBAC if role not provided
        }

        switch (strtolower(trim($viewer['role']))) {
            case 'student':
                $v_sid = $viewer['student_id'] ?? 0;
                if (empty($v_sid)) {
                    $query .= " AND 1=0";
                    return;
                }
                $query .= " AND p.student_id = :viewer_student_id";
                $params[':viewer_student_id'] = $v_sid;
                break;

            case 'teacher':
                $v_cid = $viewer['class_id'] ?? null;
                if (!empty($v_cid)) {
                    if (is_array($v_cid)) {
                        $v_cid = array_filter($v_cid); // remove empty/null
                        if (!empty($v_cid)) {
                            $inQuery = implode(',', array_map(function ($k) {
                                return ":viewer_class_id_$k";
                            }, array_keys($v_cid)));
                            $query .= " AND s.class_id IN ($inQuery)";
                            foreach ($v_cid as $k => $id) {
                                $params[":viewer_class_id_$k"] = $id;
                            }
                        } else {
                            $query .= " AND 1=0"; // Teacher with no classes assigned
                        }
                    } else {
                        $query .= " AND s.class_id = :viewer_class_id";
                        $params[':viewer_class_id'] = $v_cid;
                    }
                } else {
                    $query .= " AND 1=0"; // Block if no class info passed for teacher
                }
                break;

            case 'accountant':
            case 'admin':
                // full access
                break;
        }
    }

    public function getAll(array $filters, array $viewer)
    {
        $query = "
            SELECT p.*, 
                   s.student_code, s.full_name as student_name,
                   c.class_name,
                   ft.fee_name, ft.fee_category,
                   u.full_name as collector_name
            FROM {$this->table} p
            LEFT JOIN students s ON p.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN fee_types ft ON p.fee_type_id = ft.id
            LEFT JOIN users u ON p.collected_by = u.id
            WHERE 1=1
        ";

        $params = [];

        // ===== FILTERS =====
        if (!empty($filters['search'])) {
            $query .= " AND (p.payment_code LIKE :search1 OR s.student_code LIKE :search2 
                        OR s.full_name LIKE :search3 OR p.receipt_number LIKE :search4)";
            $search_val = '%' . $filters['search'] . '%';
            $params[':search1'] = $search_val;
            $params[':search2'] = $search_val;
            $params[':search3'] = $search_val;
            $params[':search4'] = $search_val;
        }

        if (!empty($filters['student_id'])) {
            $query .= " AND p.student_id = :filter_student_id";
            $params[':filter_student_id'] = $filters['student_id'];
        }

        if (!empty($filters['class_id'])) {
            if (is_array($filters['class_id'])) {
                $inQuery = implode(',', array_map(function ($k) {
                    return ":filter_class_id_$k";
                }, array_keys($filters['class_id'])));
                $query .= " AND s.class_id IN ($inQuery)";
                foreach ($filters['class_id'] as $k => $id) {
                    $params[":filter_class_id_$k"] = $id;
                }
            } else {
                $query .= " AND s.class_id = :filter_class_id";
                $params[':filter_class_id'] = $filters['class_id'];
            }
        }

        if (!empty($filters['from_date'])) {
            $query .= " AND p.payment_date >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $query .= " AND p.payment_date <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }

        if (!empty($filters['fee_type_id'])) {
            $query .= " AND p.fee_type_id = :fee_type_id";
            $params[':fee_type_id'] = $filters['fee_type_id'];
        }

        // ===== RBAC (BẮT BUỘC) =====
        $this->applyRBAC($query, $params, $viewer);

        // ===== PAGINATION =====
        $page = max(1, (int) ($filters['page'] ?? 1));
        $per_page = max(1, (int) ($filters['per_page'] ?? 10));
        $offset = ($page - 1) * $per_page;

        $query .= " ORDER BY p.payment_date DESC, p.created_at DESC LIMIT :offset, :per_page";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }


    /**
     * Đếm tổng số thanh toán
     */
    public function countAll(array $filters, array $viewer)
    {
        $query = "SELECT COUNT(*) as total 
                  FROM {$this->table} p
                  LEFT JOIN students s ON p.student_id = s.id
                  LEFT JOIN classes c ON s.class_id = c.id
                  LEFT JOIN fee_types ft ON p.fee_type_id = ft.id
                  LEFT JOIN users u ON p.collected_by = u.id
                  WHERE 1=1";

        $params = [];

        if (!empty($filters['search'])) {
            $query .= " AND (p.payment_code LIKE :search1 OR s.student_code LIKE :search2 
                        OR s.full_name LIKE :search3 OR p.receipt_number LIKE :search4)";
            $search_val = '%' . $filters['search'] . '%';
            $params[':search1'] = $search_val;
            $params[':search2'] = $search_val;
            $params[':search3'] = $search_val;
            $params[':search4'] = $search_val;
        }

        if (!empty($filters['student_id'])) {
            $query .= " AND p.student_id = :filter_student_id";
            $params[':filter_student_id'] = $filters['student_id'];
        }

        if (!empty($filters['from_date'])) {
            $query .= " AND p.payment_date >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $query .= " AND p.payment_date <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }

        if (!empty($filters['class_id'])) {
            if (is_array($filters['class_id'])) {
                $inQuery = implode(',', array_map(function ($k) {
                    return ":filter_class_id_$k";
                }, array_keys($filters['class_id'])));
                $query .= " AND s.class_id IN ($inQuery)";
                foreach ($filters['class_id'] as $k => $id) {
                    $params[":filter_class_id_$k"] = $id;
                }
            } else {
                $query .= " AND s.class_id = :filter_class_id";
                $params[':filter_class_id'] = $filters['class_id'];
            }
        }

        if (!empty($filters['fee_type_id'])) {
            $query .= " AND p.fee_type_id = :fee_type_id";
            $params[':fee_type_id'] = $filters['fee_type_id'];
        }

        // Apply RBAC
        $this->applyRBAC($query, $params, $viewer);

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }

    /**
     * Lấy thanh toán theo ID
     */
    public function getById($id)
    {
        $query = "SELECT p.*, 
                         s.student_code, s.full_name as student_name, s.parent_name, s.parent_phone,
                         c.class_name,
                         ft.fee_name, ft.fee_category, ft.amount as fee_amount,
                         u.full_name as collector_name
                  FROM {$this->table} p
                  INNER JOIN students s ON p.student_id = s.id
                  INNER JOIN classes c ON s.class_id = c.id
                  INNER JOIN fee_types ft ON p.fee_type_id = ft.id
                  INNER JOIN users u ON p.collected_by = u.id
                  WHERE p.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Kiểm tra mã thanh toán đã tồn tại
     */
    public function codeExists($payment_code, $exclude_id = null)
    {
        $query = "SELECT id FROM {$this->table} WHERE payment_code = :payment_code";

        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':payment_code', $payment_code);

        if ($exclude_id) {
            $stmt->bindValue(':exclude_id', $exclude_id);
        }

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Tạo thanh toán mới
     */
    public function create()
    {
        try {
            $this->conn->beginTransaction();

            // Generate payment code nếu chưa có
            if (empty($this->payment_code)) {
                $this->payment_code = generate_code('PT', 6);

                // Đảm bảo mã không trùng
                while ($this->codeExists($this->payment_code)) {
                    $this->payment_code = generate_code('PT', 6);
                }
            }

            $query = "INSERT INTO {$this->table} 
                      (payment_code, student_id, fee_type_id, amount_paid, payment_date,
                       payment_method, collected_by, receipt_number, notes, status)
                      VALUES (:payment_code, :student_id, :fee_type_id, :amount_paid, :payment_date,
                              :payment_method, :collected_by, :receipt_number, :notes, :status)";

            $stmt = $this->conn->prepare($query);

            // Bind data
            $stmt->bindValue(':payment_code', $this->payment_code);
            $stmt->bindValue(':student_id', $this->student_id);
            $stmt->bindValue(':fee_type_id', $this->fee_type_id);
            $stmt->bindValue(':amount_paid', $this->amount_paid);
            $stmt->bindValue(':payment_date', $this->payment_date);
            $stmt->bindValue(':payment_method', $this->payment_method);
            $stmt->bindValue(':collected_by', $this->collected_by);
            $stmt->bindValue(':receipt_number', $this->receipt_number);
            $stmt->bindValue(':notes', $this->notes);
            $stmt->bindValue(':status', $this->status);

            if ($stmt->execute()) {
                $payment_id = $this->conn->lastInsertId();

                // Cập nhật công nợ nếu trạng thái là Completed
                if ($this->status === 'Completed') {
                    $update_debt = "UPDATE student_debts 
                                   SET paid_amount = paid_amount + :amount,
                                       status = CASE 
                                           WHEN (paid_amount + :amount) >= total_amount THEN 'Paid'
                                           WHEN (paid_amount + :amount) > 0 THEN 'Partial'
                                           ELSE 'Unpaid'
                                       END
                                   WHERE student_id = :student_id AND fee_type_id = :fee_type_id";
                    $update_stmt = $this->conn->prepare($update_debt);
                    $update_stmt->execute([
                        'amount' => $this->amount_paid,
                        'student_id' => $this->student_id,
                        'fee_type_id' => $this->fee_type_id
                    ]);
                }

                $this->conn->commit();

                return [
                    'success' => true,
                    'message' => 'Thanh toán thành công!',
                    'id' => $payment_id,
                    'payment_code' => $this->payment_code
                ];
            }

            throw new Exception('Không thể tạo thanh toán!');

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Tạo nhiều thanh toán cùng lúc
     */
    public function createMultiple($student_id, $payments_data, $common_data)
    {
        try {
            $this->conn->beginTransaction();

            $results = [];
            $total_paid = 0;

            foreach ($payments_data as $data) {
                $payment_code = generate_code('PT', 6);
                while ($this->codeExists($payment_code)) {
                    $payment_code = generate_code('PT', 6);
                }

                $query = "INSERT INTO {$this->table} 
                          (payment_code, student_id, fee_type_id, amount_paid, payment_date,
                           payment_method, collected_by, receipt_number, notes, status)
                          VALUES (:payment_code, :student_id, :fee_type_id, :amount_paid, :payment_date,
                                  :payment_method, :collected_by, :receipt_number, :notes, :status)";

                $stmt = $this->conn->prepare($query);

                $status = $common_data['status'] ?? 'Completed';

                $stmt->execute([
                    'payment_code' => $payment_code,
                    'student_id' => $student_id,
                    'fee_type_id' => $data['fee_type_id'],
                    'amount_paid' => $data['amount_paid'],
                    'payment_date' => $common_data['payment_date'],
                    'payment_method' => $common_data['payment_method'],
                    'collected_by' => $common_data['collected_by'],
                    'receipt_number' => $common_data['receipt_number'] ?? '',
                    'notes' => $common_data['notes'] ?? '',
                    'status' => $status
                ]);

                $payment_id = $this->conn->lastInsertId();
                $results[] = $payment_id;

                // Cập nhật công nợ
                if ($status === 'Completed') {
                    $update_debt = "UPDATE student_debts 
                                   SET paid_amount = paid_amount + :amount,
                                       status = CASE 
                                           WHEN (paid_amount + :amount) >= total_amount THEN 'Paid'
                                           WHEN (paid_amount + :amount) > 0 THEN 'Partial'
                                           ELSE 'Unpaid'
                                       END
                                   WHERE student_id = :student_id AND fee_type_id = :fee_type_id";
                    $update_stmt = $this->conn->prepare($update_debt);
                    $update_stmt->execute([
                        'amount' => $data['amount_paid'],
                        'student_id' => $student_id,
                        'fee_type_id' => $data['fee_type_id']
                    ]);
                }
            }

            $this->conn->commit();
            return ['success' => true, 'message' => 'Đã thu phí thành công cho ' . count($payments_data) . ' khoản!', 'ids' => $results];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cập nhật thanh toán
     */
    public function update()
    {
        $query = "UPDATE {$this->table} 
                  SET amount_paid = :amount_paid,
                      payment_date = :payment_date,
                      payment_method = :payment_method,
                      receipt_number = :receipt_number,
                      notes = :notes,
                      status = :status
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Bind data
        $stmt->bindValue(':amount_paid', $this->amount_paid);
        $stmt->bindValue(':payment_date', $this->payment_date);
        $stmt->bindValue(':payment_method', $this->payment_method);
        $stmt->bindValue(':receipt_number', $this->receipt_number);
        $stmt->bindValue(':notes', $this->notes);
        $stmt->bindValue(':status', $this->status);
        $stmt->bindValue(':id', $this->id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật thành công!'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra!'];
    }

    /**
     * Xóa thanh toán
     */
    public function delete($id)
    {
        try {
            $this->conn->beginTransaction();

            // Lấy thông tin thanh toán trước khi xóa
            $payment = $this->getById($id);
            if (!$payment) {
                throw new Exception('Không tìm thấy thanh toán!');
            }

            // Xóa thanh toán
            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':id', $id);

            if (!$stmt->execute()) {
                throw new Exception('Không thể xóa thanh toán!');
            }

            // Cập nhật lại công nợ
            $update_debt = "UPDATE student_debts 
                           SET paid_amount = paid_amount - :amount,
                               status = CASE 
                                   WHEN (paid_amount - :amount) <= 0 THEN 'Unpaid'
                                   WHEN (paid_amount - :amount) < total_amount THEN 'Partial'
                                   ELSE status
                               END
                           WHERE student_id = :student_id AND fee_type_id = :fee_type_id";

            $update_stmt = $this->conn->prepare($update_debt);
            $update_stmt->bindValue(':amount', $payment['amount_paid']);
            $update_stmt->bindValue(':student_id', $payment['student_id']);
            $update_stmt->bindValue(':fee_type_id', $payment['fee_type_id']);
            $update_stmt->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Xóa thành công!'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lấy phương thức thanh toán
     */
    public function getPaymentMethods()
    {
        return [
            'Cash' => 'Tiền mặt',
            'Bank Transfer' => 'Chuyển khoản',
            'Card' => 'Thẻ tín dụng/ATM',
            'Momo' => 'Ví Momo',
            'VNPay' => 'VNPay',
            'ZaloPay' => 'ZaloPay',
            'Other' => 'Khác'
        ];
    }

    /**
     * Thống kê tổng thu theo thời gian
     */
    public function getTotalRevenue($from_date = '', $to_date = '')
    {
        $query = "SELECT 
                    SUM(amount_paid) as total_revenue,
                    COUNT(*) as total_transactions
                  FROM {$this->table}
                  WHERE status = 'Completed'";

        if (!empty($from_date)) {
            $query .= " AND payment_date >= :from_date";
        }

        if (!empty($to_date)) {
            $query .= " AND payment_date <= :to_date";
        }

        $stmt = $this->conn->prepare($query);

        if (!empty($from_date)) {
            $stmt->bindValue(':from_date', $from_date);
        }

        if (!empty($to_date)) {
            $stmt->bindValue(':to_date', $to_date);
        }

        $stmt->execute();
        return $stmt->fetch();
    }
    /**
     * Lấy doanh thu theo từng tháng trong năm
     */
    public function getMonthlyRevenue($year)
    {
        $query = "SELECT MONTH(payment_date) as month, SUM(amount_paid) as revenue
                  FROM {$this->table}
                  WHERE YEAR(payment_date) = :year AND status = 'Completed'
                  GROUP BY MONTH(payment_date)
                  ORDER BY month";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':year', $year);
        $stmt->execute();

        $results = $stmt->fetchAll();

        // Prepare array for all 12 months
        $revenue_data = array_fill(1, 12, 0);
        foreach ($results as $row) {
            $revenue_data[(int) $row['month']] = (float) $row['revenue'];
        }

        return array_values($revenue_data); // Return simple array [rev1, rev2, ...]
    }
}