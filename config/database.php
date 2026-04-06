<?php
/**
 * Database Configuration - PDO Connection
 */

// Disable direct error output to avoid corrupting JSON in API mode
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// error_log path is typically handled by server config, but ensures no echo occurs.

class Database
{
    // Thông tin kết nối - tự động nhận diện MySQL hoặc PostgreSQL (Railway/Render)
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $driver; // 'mysql' hoặc 'pgsql'
    private $charset = 'utf8'; // pgsql dùng 'utf8', mysql dùng 'utf8mb4'

    public function __construct()
    {
        // 1. Thử PostgreSQL (DATABASE_URL hoặc PGHOST)
        $pg_url = getenv('DATABASE_URL');
        if ($pg_url && strpos($pg_url, 'postgres') !== false) {
            $this->driver = 'pgsql';
            $dbvars = parse_url($pg_url);
            $this->host     = $dbvars['host'] ?? 'localhost';
            $this->db_name  = ltrim($dbvars['path'], '/') ?: 'railway';
            $this->username = $dbvars['user'] ?? 'postgres';
            $this->password = $dbvars['pass'] ?? '';
            $this->port     = $dbvars['port'] ?? '5432';
            $this->charset  = 'utf8';
        } elseif (getenv('PGHOST')) {
            $this->driver   = 'pgsql';
            $this->host     = getenv('PGHOST');
            $this->db_name  = getenv('PGDATABASE');
            $this->username = getenv('PGUSER');
            $this->password = getenv('PGPASSWORD');
            $this->port     = getenv('PGPORT') ?: '5432';
            $this->charset  = 'utf8';
        } 
        // 2. Thử MySQL (MYSQL_URL hoặc MYSQLHOST)
        else {
            $this->driver = 'mysql';
            $this->charset = 'utf8mb4';
            $mysql_url = getenv('MYSQL_URL');
            if ($mysql_url) {
                $dbvars = parse_url($mysql_url);
                $this->host     = $dbvars['host'] ?? 'localhost';
                $this->db_name  = ltrim($dbvars['path'], '/') ?: 'railway';
                $this->username = $dbvars['user'] ?? 'root';
                $this->password = $dbvars['pass'] ?? '';
                $this->port     = $dbvars['port'] ?? '3306';
            } else {
                $this->host     = getenv('MYSQLHOST')     ?: 'localhost';
                $this->db_name  = getenv('MYSQLDATABASE') ?: 'student_fee_management';
                $this->username = getenv('MYSQLUSER')     ?: 'root';
                $this->password = getenv('MYSQLPASSWORD') ?: '';
                $this->port     = getenv('MYSQLPORT')     ?: '3306';
            }
        }
    }

    public $conn;

    /**
     * Kết nối database với PDO
     * @return PDO|null
     */
    public function connect()
    {
        $this->conn = null;

        try {
            if ($this->driver === 'pgsql') {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name};options='--client_encoding=UTF8'";
            } else {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset={$this->charset}";
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ];

            if ($this->driver === 'mysql') {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
            }

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $e) {
            echo "Lỗi kết nối ({$this->driver}): " . $e->getMessage();
            error_log("Database Connection Error: " . $e->getMessage());
        }

        return $this->conn;
    }

    /**
     * Get connection (alias for connect method)
     * @return PDO|null
     */
    public function getConnection()
    {
        if ($this->conn === null) {
            return $this->connect();
        }
        return $this->conn;
    }

    /**
     * Đóng kết nối
     */
    public function disconnect()
    {
        $this->conn = null;
    }
}

/**
 * Helper Functions - Các hàm tiện ích
 */

/**
 * Escape string để tránh SQL Injection (dự phòng)
 */
function clean_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format số tiền VNĐ
 */
function format_currency($amount)
{
    return number_format($amount, 0, ',', '.') . ' đ';
}

/**
 * Format ngày tháng
 */
function format_date($date, $format = 'd/m/Y')
{
    if (empty($date))
        return '';
    return date($format, strtotime($date));
}

/**
 * Format ngày giờ
 */
function format_datetime($date, $format = 'd/m/Y H:i:s')
{
    if (empty($date))
        return '';
    return date($format, strtotime($date));
}

/**
 * Tạo mã code tự động
 */
function generate_code($prefix, $length = 6)
{
    $timestamp = time();
    $random = str_pad(rand(0, 999999), $length, '0', STR_PAD_LEFT);
    return $prefix . date('Ymd') . $random;
}

/**
 * Kiểm tra quyền truy cập
 */
function check_permission($allowed_roles = [])
{
    if (!isset($_SESSION['user_id'])) {
        if (defined('API_MODE')) {
            json_response(['success' => false, 'message' => 'Unauthorized - Please login', 'code' => 401], 401);
        }
        header('Location: index.php?action=login');
        exit();
    }

    $user_role = $_SESSION['role_name'] ?? $_SESSION['role'] ?? '';

    if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
        if (defined('API_MODE')) {
            json_response(['success' => false, 'message' => 'Forbidden - No Permission', 'code' => 403], 403);
        }
        header('Location: index.php?action=dashboard&error=no_permission');
        exit();
    }

    return true;
}

function json_response($data, $status = 200)
{
    // --- CORS ---
    $allowed_origins = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:5500',
        'http://localhost:8080',
        'http://127.0.0.1',
        'http://127.0.0.1:5500',
        'http://127.0.0.1:8080',
    ];

    // Thêm origin production từ biến môi trường (set trên Railway Dashboard)
    $prod_origin = getenv('ALLOWED_ORIGIN');
    if ($prod_origin) {
        $allowed_origins[] = rtrim($prod_origin, '/');
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");

    // Handle Preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Clean any buffer (whitespace, warnings) that might have leaked
    if (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
}

/**
 * Flash Message
 */
function set_flash($key, $message, $type = 'success')
{
    $_SESSION['flash'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

function get_flash($key)
{
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

/**
 * Pagination Helper
 */
function paginate($total_records, $per_page = 10, $current_page = 1)
{
    $total_pages = ceil($total_records / $per_page);
    $offset = ($current_page - 1) * $per_page;

    return [
        'total_records' => $total_records,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}