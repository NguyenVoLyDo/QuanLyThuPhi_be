<?php
require_once __DIR__ . '/../config/database.php';

class PaymentProof
{
    private $conn;
    private $table = 'payment_proofs';

    public $id;
    public $student_id;
    public $fee_type_id;
    public $amount;
    public $image_path;
    public $status;
    public $admin_note;
    public $created_at;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Tạo minh chứng mới
     */
    public function create()
    {
        $query = "INSERT INTO " . $this->table . " 
                  (student_id, fee_type_id, amount, image_path, status)
                  VALUES (:student_id, :fee_type_id, :amount, :image_path, 'Pending')";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(':student_id', $this->student_id);
        $stmt->bindValue(':fee_type_id', $this->fee_type_id);
        $stmt->bindValue(':amount', $this->amount);
        $stmt->bindValue(':image_path', $this->image_path);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Gửi minh chứng thành công!'];
        }

        return ['success' => false, 'message' => 'Lỗi khi lưu minh chứng!'];
    }

    /**
     * Lấy tất cả minh chứng
     */
    public function getAll($student_id = '', $status = '')
    {
        $query = "SELECT pp.*, s.student_code, s.full_name as student_name, ft.fee_name 
                  FROM " . $this->table . " pp
                  JOIN students s ON pp.student_id = s.id
                  JOIN fee_types ft ON pp.fee_type_id = ft.id
                  WHERE 1=1";

        if (!empty($student_id)) {
            $query .= " AND pp.student_id = :student_id";
        }

        if (!empty($status)) {
            $query .= " AND pp.status = :status";
        }

        $query .= " ORDER BY pp.created_at DESC";

        $stmt = $this->conn->prepare($query);

        if (!empty($student_id)) {
            $stmt->bindValue(':student_id', $student_id);
        }

        if (!empty($status)) {
            $stmt->bindValue(':status', $status);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy chi tiết minh chứng
     */
    public function getById($id)
    {
        $query = "SELECT pp.*, s.student_code, s.full_name as student_name, ft.fee_name 
                  FROM " . $this->table . " pp
                  JOIN students s ON pp.student_id = s.id
                  JOIN fee_types ft ON pp.fee_type_id = ft.id
                  WHERE pp.id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cập nhật trạng thái minh chứng
     */
    public function updateStatus($id, $status, $note = '')
    {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status, admin_note = :note 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':note', $note);
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Cập nhật trạng thái thành công!'];
        }

        return ['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái!'];
    }
}
?>