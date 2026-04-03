<?php
require_once __DIR__ . '/../config/database.php';

class Refund {
    private $conn;
    private $table = 'refunds';
    
    public $id;
    public $payment_id;
    public $amount;
    public $reason;
    public $refunded_by;
    public $refunded_at;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Tạo hoàn tiền mới
     */
    public function create($payment_id, $amount, $reason, $refunded_by) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Kiểm tra thanh toán có tồn tại không
            $p_query = "SELECT * FROM payments WHERE id = :id";
            $p_stmt = $this->conn->prepare($p_query);
            $p_stmt->execute(['id' => $payment_id]);
            $payment = $p_stmt->fetch();
            
            if (!$payment) {
                throw new Exception('Không tìm thấy thanh toán gốc!');
            }
            
            // 2. Kiểm tra số tiền hoàn không vượt quá số tiền đã đóng
            if ($amount > $payment['amount_paid']) {
                throw new Exception('Số tiền hoàn không thể lớn hơn số tiền đã đóng!');
            }
            
            // 3. Tạo bản ghi hoàn tiền
            $query = "INSERT INTO {$this->table} (payment_id, amount, reason, refunded_by)
                      VALUES (:payment_id, :amount, :reason, :refunded_by)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                'payment_id' => $payment_id,
                'amount' => $amount,
                'reason' => $reason,
                'refunded_by' => $refunded_by
            ]);
            
            // 4. Cập nhật lại công nợ học sinh (tăng nợ lên vì tiền đã trả lại)
            $update_debt = "UPDATE student_debts 
                           SET paid_amount = paid_amount - :amount,
                               status = CASE 
                                   WHEN (paid_amount - :amount) <= 0 THEN 'Unpaid'
                                   WHEN (paid_amount - :amount) < total_amount THEN 'Partial'
                                   ELSE status
                               END
                           WHERE student_id = :student_id AND fee_type_id = :fee_type_id";
            
            $update_stmt = $this->conn->prepare($update_debt);
            $update_stmt->execute([
                'amount' => $amount,
                'student_id' => $payment['student_id'],
                'fee_type_id' => $payment['fee_type_id']
            ]);
            
            // 5. Đánh dấu thanh toán này đã có hoàn tiền (tùy chọn, có thể thêm cột refund_status vào payments)
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Hoàn tiền thành công!'];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Lấy lịch sử hoàn tiền
     */
    public function getAll($page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT r.*, p.payment_code, p.amount_paid as original_amount,
                         s.full_name as student_name, ft.fee_name
                  FROM {$this->table} r
                  JOIN payments p ON r.payment_id = p.id
                  JOIN students s ON p.student_id = s.id
                  JOIN fee_types ft ON p.fee_type_id = ft.id
                  ORDER BY r.refunded_at DESC
                  LIMIT :offset, :per_page";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function countAll() {
        $query = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['total'] : 0;
    }
}
?>
