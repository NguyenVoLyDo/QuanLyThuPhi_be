-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th1 15, 2026 lúc 07:29 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

-- =====================================================
-- XÓA DATABASE CŨ NẾU TỒN TẠI VÀ TẠO DATABASE MỚI
-- =====================================================
DROP DATABASE IF EXISTS `student_fee_management`;
CREATE DATABASE `student_fee_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `student_fee_management`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `student_fee_management`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `target_type`, `target_table`, `target_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Login', 'System', NULL, NULL, 'Admin đăng nhập hệ thống', '127.0.0.1', NULL, '2026-01-10 09:26:58'),
(2, 2, 'Login', 'System', NULL, NULL, 'Kế toán Lan đăng nhập', '192.168.1.5', NULL, '2026-01-10 09:26:58'),
(3, 2, 'Create', 'FeeType', NULL, NULL, 'Tạo khoản thu mới: Học phí HK1', '192.168.1.5', NULL, '2026-01-10 09:26:58'),
(4, 2, 'Collect', 'Payment', NULL, NULL, 'Thu tiền mặt HS Nguyễn Văn An: 3.000.000đ', '192.168.1.5', NULL, '2026-01-10 09:26:58'),
(5, 1, 'Update', 'User', NULL, NULL, 'Cập nhật thông tin giáo viên Hùng', '127.0.0.1', NULL, '2026-01-10 09:26:58'),
(6, 3, 'View', 'Class', NULL, NULL, 'GVCN xem danh sách lớp 10A1', '192.168.1.10', NULL, '2026-01-10 09:26:58'),
(7, 1, 'CREATE_FEE_TYPE', 'fee_type', NULL, 11, 'Tên: Học Phí Kỳ Phụ, Số tiền: 123456789000', '::1', NULL, '2026-01-10 09:59:31'),
(8, 1, 'DELETE_FEE_TYPE', 'fee_type', NULL, 11, 'Xóa khoản thu ID: 11', '::1', NULL, '2026-01-10 22:02:46'),
(9, 1, 'CREATE_PAYMENT', 'payment', NULL, 106, 'Mã phiếu: PT20260115501739, Số tiền: 150000', '::1', NULL, '2026-01-15 12:14:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `grade_level` int(11) DEFAULT 10 COMMENT 'Khối lớp',
  `description` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `grade_level`, `description`, `teacher_id`) VALUES
(1, '10A1', 10, 'Lớp Chọn Toán', 3),
(2, '10A2', 10, 'Lớp Chọn Văn', 4),
(3, '10A3', 10, 'Lớp Đại trà', NULL),
(4, '11B1', 11, 'Lớp KHTN', 5),
(5, '11B2', 11, 'Lớp KHXH', NULL),
(6, '12C1', 12, 'Lớp Mũi nhọn', 6),
(7, '12C2', 12, 'Lớp Ôn thi', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `exemptions`
--

CREATE TABLE `exemptions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `discount_type` enum('Percent','Amount') NOT NULL DEFAULT 'Percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '1: Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `exemptions`
--

INSERT INTO `exemptions` (`id`, `name`, `discount_type`, `discount_value`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Hộ nghèo', 'Percent', 100.00, 'Miễn phí toàn bộ', 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(2, 'Hộ cận nghèo', 'Percent', 50.00, 'Giảm 50%', 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(3, 'Con thương binh/Liệt sĩ', 'Percent', 30.00, 'Giảm 30%', 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(4, 'Anh chị em ruột', 'Percent', 10.00, 'Giảm 10% cho bé thứ 2', 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(5, 'Học bổng Tài năng', 'Amount', 1000000.00, 'Trừ trực tiếp 1 triệu', 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(6, 'Khuyến khích học sinh Giỏi', 'Percent', 100.00, '', 1, '2026-01-15 12:15:01', '2026-01-15 12:15:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `fee_types`
--

CREATE TABLE `fee_types` (
  `id` int(11) NOT NULL,
  `fee_name` varchar(100) NOT NULL,
  `fee_category` varchar(50) DEFAULT 'Khác',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `academic_year` varchar(20) DEFAULT '2025-2026',
  `semester` varchar(20) DEFAULT 'Cả năm',
  `is_mandatory` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `fee_types`
--

INSERT INTO `fee_types` (`id`, `fee_name`, `fee_category`, `amount`, `academic_year`, `semester`, `is_mandatory`, `start_date`, `end_date`, `status`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Học phí HK1 (2025-2026)', 'Học phí', 3000000.00, '2025-2026', 'Học kỳ 1', 1, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(2, 'Học phí HK2 (2025-2026)', 'Học phí', 3000000.00, '2025-2026', 'Học kỳ 2', 1, NULL, NULL, 'Inactive', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(3, 'Bảo hiểm Y tế', 'Bảo hiểm', 680400.00, '2025-2026', 'Cả năm', 1, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(4, 'Bảo hiểm Thân thể', 'Bảo hiểm', 150000.00, '2025-2026', 'Cả năm', 0, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(5, 'Tiền ăn bán trú T9', 'Tiền ăn', 800000.00, '2025-2026', 'Tháng 9', 0, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(6, 'Đồng phục Mùa hè', 'Đồng phục', 350000.00, '2025-2026', 'Đầu năm', 0, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(7, 'Đồng phục Mùa đông', 'Đồng phục', 450000.00, '2025-2026', 'Đầu năm', 0, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(8, 'Quỹ Khuyến học', 'Quỹ lớp', 200000.00, '2025-2026', 'Cả năm', 0, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(9, 'Nước uống tinh khiết', 'Dịch vụ', 100000.00, '2025-2026', 'Học kỳ 1', 1, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(10, 'Gửi xe đạp điện', 'Dịch vụ', 90000.00, '2025-2026', 'Tháng 9', 0, NULL, NULL, 'Active', NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_code` varchar(20) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `status` enum('Paid','Pending','Unpaid','Completed','Cancelled') DEFAULT 'Pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`id`, `payment_code`, `student_id`, `fee_type_id`, `amount_paid`, `payment_date`, `status`, `payment_method`, `collected_by`, `receipt_number`, `notes`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 1, 3000000.00, '2025-09-05', 'Completed', 'Transfer', 2, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(2, NULL, 1, 3, 680400.00, '2025-09-05', 'Completed', 'Transfer', 2, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(3, NULL, 2, 1, 3000000.00, '2025-09-06', 'Completed', 'Cash', 2, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(4, NULL, 2, 3, 680400.00, '2025-09-06', 'Completed', 'Cash', 2, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(5, NULL, 13, 1, 3000000.00, '2025-09-07', 'Completed', 'VNPAY', NULL, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(6, NULL, 3, 1, 1500000.00, '2025-09-10', 'Completed', 'Cash', 2, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(7, NULL, 4, 5, 800000.00, '2025-09-01', 'Completed', 'Cash', NULL, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(8, NULL, 14, 1, 3000000.00, '2026-01-10', 'Pending', 'Transfer', NULL, NULL, NULL, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(99, NULL, 12, 6, 350000.00, '2025-09-10', 'Cancelled', 'Transfer', NULL, NULL, 'Đóng nhầm, đã hoàn tiền', '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(106, 'PT20260115501739', 2, 4, 150000.00, '2026-01-15', 'Completed', 'Cash', 1, 'BL001', '', '2026-01-15 12:14:43', '2026-01-15 12:14:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `image_path` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `payment_proofs`
--

INSERT INTO `payment_proofs` (`id`, `student_id`, `fee_type_id`, `amount`, `payment_date`, `image_path`, `status`, `admin_note`, `created_at`, `updated_at`) VALUES
(1, 14, 1, 3000000.00, '2026-01-10 23:26:58', 'uploads/proofs/sontung_receipt.jpg', 'Pending', NULL, '2026-01-10 23:26:58', '2026-01-10 23:26:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `refunded_by` int(11) DEFAULT NULL,
  `refunded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `refunds`
--

INSERT INTO `refunds` (`id`, `payment_id`, `amount`, `reason`, `refunded_by`, `refunded_at`) VALUES
(1, 99, 350000.00, 'Phụ huynh đóng nhầm 2 lần', 2, '2026-01-10 09:26:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Accountant'),
(3, 'Teacher'),
(4, 'Student');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `students`
--

INSERT INTO `students` (`id`, `student_code`, `full_name`, `date_of_birth`, `gender`, `class_id`, `parent_name`, `parent_phone`, `parent_email`, `address`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'HS24001', 'Nguyễn Văn An', '2008-05-10', 'Male', 1, 'Nguyễn Văn Ba', '0901000001', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(2, 'HS24002', 'Trần Thị Bình', '2008-08-20', 'Female', 1, 'Trần Văn Bốn', '0901000002', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(3, 'HS24003', 'Lê Hoàng Cường', '2008-02-15', 'Male', 1, 'Lê Văn Năm', '0901000003', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(4, 'HS24004', 'Phạm Mỹ Dung', '2008-11-02', 'Female', 2, 'Phạm Văn Sáu', '0901000004', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(5, 'HS24005', 'Đỗ Trung Đức', '2008-01-30', 'Male', 2, 'Đỗ Văn Bảy', '0901000005', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(6, 'HS24006', 'Hoàng Thu Giang', '2008-12-12', 'Female', 2, 'Hoàng Văn Tám', '0901000006', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(7, 'HS24007', 'Vũ Minh Hiếu', '2008-07-07', 'Male', 3, 'Vũ Văn Chín', '0901000007', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(8, 'HS23001', 'Ngô Kiến Huy', '2007-03-03', 'Male', 4, 'Ngô Văn Mười', '0901000008', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(9, 'HS23002', 'Bùi Bích Phương', '2007-09-09', 'Female', 4, 'Bùi Văn Một', '0901000009', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(10, 'HS23003', 'Đinh Tiến Đạt', '2007-06-01', 'Male', 5, 'Đinh Văn Hai', '0901000010', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(11, 'HS23004', 'Lương Thùy Linh', '2007-10-20', 'Female', 5, 'Lương Văn Ba', '0901000011', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(12, 'HS22001', 'Hà Anh Tuấn', '2006-04-14', 'Male', 6, 'Hà Văn Bốn', '0901000012', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(13, 'HS22002', 'Mỹ Tâm', '2006-01-16', 'Female', 6, 'Ông Mỹ', '0901000013', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(14, 'HS22003', 'Sơn Tùng', '2006-07-05', 'Male', 6, 'Ông Sơn', '0901000014', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(15, 'HS22004', 'Đen Vâu', '2006-05-15', 'Male', 7, 'Ông Đen', '0901000015', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(16, 'HS22005', 'Suboi', '2006-02-28', 'Female', 7, 'Bà Su', '0901000016', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(17, 'HS24008', 'Nguyễn Quang Hải', '2008-04-12', 'Male', 3, 'Ông Hải', '0901000017', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(18, 'HS24009', 'Đoàn Văn Hậu', '2008-09-19', 'Male', 3, 'Ông Hậu', '0901000018', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(19, 'HS24010', 'Công Phượng', '2008-11-25', 'Male', 1, 'Ông Phượng', '0901000019', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(20, 'HS24011', 'Văn Toàn', '2008-06-15', 'Male', 2, 'Ông Toàn', '0901000020', NULL, NULL, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:26:58'),
(22, 'HS001', 'Nguyen Van A', '2010-12-05', 'Male', 1, 'Nguyen Van B', '0912345678', 'phhsA@example.com', 'Ha Noi', 'Imported via CSV on 2026-01-16 00:23:34', 1, '2026-01-15 17:23:34', '2026-01-15 17:23:34');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_debts`
--

CREATE TABLE `student_debts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Số tiền phải đóng',
  `paid_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Số tiền đã đóng',
  `status` enum('Unpaid','Partial','Paid','Overdue') DEFAULT 'Unpaid',
  `due_date` date DEFAULT NULL COMMENT 'Hạn nộp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `student_debts`
--

INSERT INTO `student_debts` (`id`, `student_id`, `fee_type_id`, `total_amount`, `paid_amount`, `status`, `due_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 3000000.00, 3000000.00, 'Paid', '2025-09-30', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(2, 2, 1, 3000000.00, 3000000.00, 'Paid', '2025-09-30', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(3, 3, 1, 3000000.00, 1500000.00, 'Partial', '2025-09-30', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(4, 13, 1, 3000000.00, 3000000.00, 'Paid', '2025-09-30', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(5, 14, 1, 3000000.00, 0.00, 'Unpaid', '2025-09-30', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(6, 15, 1, 0.00, 0.00, 'Paid', '2025-09-30', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(7, 1, 3, 680400.00, 680400.00, 'Paid', '2025-09-15', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(8, 2, 3, 680400.00, 680400.00, 'Paid', '2025-09-15', '2026-01-15 12:06:50', '2026-01-15 12:06:50'),
(9, 14, 3, 680400.00, 0.00, 'Unpaid', '2025-09-15', '2026-01-15 12:06:50', '2026-01-15 12:06:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_exemptions`
--

CREATE TABLE `student_exemptions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exemption_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `student_exemptions`
--

INSERT INTO `student_exemptions` (`id`, `student_id`, `exemption_id`, `assigned_date`) VALUES
(1, 15, 1, '2026-01-10 09:26:58'),
(2, 5, 2, '2026-01-10 09:26:58'),
(3, 8, 3, '2026-01-10 09:26:58'),
(4, 12, 5, '2026-01-10 09:26:58');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role_id`, `student_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$THpvt0KrKfiIMubWK/GV6ep799lG5b.E2OOEyjVmn/jWgSrikaJ62', 'Super Admin', 'admin@school.edu', NULL, 1, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:40:43'),
(2, 'ketoan', '$2y$10$lCwUbYff7f72wN5cOpjmOu6I6Y9mxaDy9M6BDPfg01ClyGBqcQYeq', 'Cô Lan (Kế toán)', 'lan.kt@school.edu', '', 2, NULL, 1, '2026-01-10 09:26:58', '2026-01-10 09:42:42'),
(3, 'gvcn_toan', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Thầy Hùng (Toán)', 'hung.toan@school.edu', '', 3, NULL, 1, '2026-01-10 09:26:58', '2026-01-15 14:45:41'),
(4, 'gvcn_van', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Cô Mai (Văn)', 'mai.van@school.edu', '', 3, NULL, 1, '2026-01-10 09:26:58', '2026-01-15 14:46:07'),
(5, 'gvcn_khtn', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Thầy Lâm (KHTN)', 'lam.khtn@school.edu', '', 3, NULL, 1, '2026-01-10 09:26:58', '2026-01-15 14:46:07'),
(6, 'gvcn_mn', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Cô Hoa (Mũi nhọn)', 'hoa.mn@school.edu', '', 3, NULL, 1, '2026-01-10 09:26:58', '2026-01-15 14:46:07'),
(7, 'hs001', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Bùi Trường Quyền', 'buitruongquyen4@gmail.com', '09762122959', 4, NULL, 1, '2026-01-15 12:17:21', '2026-01-15 12:17:21'),
(8, 'HS24001', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Nguyễn Văn An', NULL, '0901000001', 4, 1, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(9, 'HS24002', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Trần Thị Bình', NULL, '0901000002', 4, 2, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(10, 'HS24003', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Lê Hoàng Cường', NULL, '0901000003', 4, 3, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(11, 'HS24004', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Phạm Mỹ Dung', NULL, '0901000004', 4, 4, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(12, 'HS24005', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Đỗ Trung Đức', NULL, '0901000005', 4, 5, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(13, 'HS24006', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Hoàng Thu Giang', NULL, '0901000006', 4, 6, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(14, 'HS24007', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Vũ Minh Hiếu', NULL, '0901000007', 4, 7, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(15, 'HS23001', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Ngô Kiến Huy', NULL, '0901000008', 4, 8, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(16, 'HS23002', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Bùi Bích Phương', NULL, '0901000009', 4, 9, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(17, 'HS23003', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Đinh Tiến Đạt', NULL, '0901000010', 4, 10, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(18, 'HS23004', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Lương Thùy Linh', NULL, '0901000011', 4, 11, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(19, 'HS22001', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Hà Anh Tuấn', NULL, '0901000012', 4, 12, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(20, 'HS22002', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Mỹ Tâm', NULL, '0901000013', 4, 13, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(21, 'HS22003', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Sơn Tùng', NULL, '0901000014', 4, 14, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(22, 'HS22004', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Đen Vâu', NULL, '0901000015', 4, 15, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(23, 'HS22005', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Suboi', NULL, '0901000016', 4, 16, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(24, 'HS24008', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Nguyễn Quang Hải', NULL, '0901000017', 4, 17, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(25, 'HS24009', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Đoàn Văn Hậu', NULL, '0901000018', 4, 18, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(26, 'HS24010', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Công Phượng', NULL, '0901000019', 4, 19, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23'),
(27, 'HS24011', '$2y$10$23FrYNCuoN0m3HPyZ6DH4uZa4Z81lql3dSpq0UorW9Els.NenMReK', 'Văn Toàn', NULL, '0901000020', 4, 20, 1, '2026-01-15 14:39:23', '2026-01-15 14:39:23');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Chỉ mục cho bảng `exemptions`
--
ALTER TABLE `exemptions`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `fee_types`
--
ALTER TABLE `fee_types`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_type_id` (`fee_type_id`),
  ADD KEY `collected_by` (`collected_by`);

--
-- Chỉ mục cho bảng `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_type_id` (`fee_type_id`);

--
-- Chỉ mục cho bảng `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `refunded_by` (`refunded_by`);

--
-- Chỉ mục cho bảng `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `class_id` (`class_id`);

--
-- Chỉ mục cho bảng `student_debts`
--
ALTER TABLE `student_debts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_type_id` (`fee_type_id`);

--
-- Chỉ mục cho bảng `student_exemptions`
--
ALTER TABLE `student_exemptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exemption_id` (`exemption_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `exemptions`
--
ALTER TABLE `exemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `fee_types`
--
ALTER TABLE `fee_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT cho bảng `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT cho bảng `student_debts`
--
ALTER TABLE `student_debts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `student_exemptions`
--
ALTER TABLE `student_exemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`);

--
-- Các ràng buộc cho bảng `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD CONSTRAINT `payment_proofs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `payment_proofs_ibfk_2` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`);

--
-- Các ràng buộc cho bảng `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  ADD CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`refunded_by`) REFERENCES `users` (`id`);

--
-- Các ràng buộc cho bảng `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`);

--
-- Các ràng buộc cho bảng `student_debts`
--
ALTER TABLE `student_debts`
  ADD CONSTRAINT `student_debts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_debts_ibfk_2` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `student_exemptions`
--
ALTER TABLE `student_exemptions`
  ADD CONSTRAINT `student_exemptions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_exemptions_ibfk_2` FOREIGN KEY (`exemption_id`) REFERENCES `exemptions` (`id`);

--
-- Các ràng buộc cho bảng `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
