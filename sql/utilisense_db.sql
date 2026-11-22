-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2026 at 04:08 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `utilisense_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `action_type` varchar(50) NOT NULL DEFAULT 'EVENT',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `details`, `ip_address`, `created_at`, `action_type`, `description`) VALUES
(2, NULL, 'LOGOUT', 'User logged out (ID: 4)', '::1', '2026-11-11 14:35:25', 'EVENT', NULL),
(3, NULL, 'LOGIN_FAILED', 'Failed login attempt for g95563484@gmail.com', '::1', '2026-11-11 14:36:44', 'EVENT', NULL),
(4, NULL, 'LOGIN_FAILED', 'Failed login attempt for g95563484@gmail.com', '::1', '2026-11-11 14:36:57', 'EVENT', NULL),
(5, 4, 'CONSUMER_REGISTER', 'Registered consumer account UTS-1919 (g95563484@gmail.com)', '::1', '2026-11-11 14:37:06', 'EVENT', NULL),
(6, 4, 'LOGIN_SUCCESS', 'Logged in as consumer', '::1', '2026-11-11 14:37:20', 'EVENT', NULL),
(7, 4, 'SET_PREFERENCES', 'Selected utility preferences', '::1', '2026-11-11 14:37:35', 'EVENT', NULL),
(8, 4, 'LOGOUT', 'User logged out', '::1', '2026-11-11 14:39:40', 'EVENT', NULL),
(9, NULL, 'LOGIN_SUCCESS', 'Logged in as staff', '::1', '2026-11-11 14:40:32', 'EVENT', NULL),
(10, NULL, 'LOGOUT', 'User logged out', '::1', '2026-11-17 11:16:50', 'EVENT', NULL),
(11, 5, '', '', '::1', '2026-11-17 14:29:17', 'LOGIN', 'Signed in as Consumer'),
(12, 6, '', '', '::1', '2026-11-17 14:33:08', 'LOGIN', 'Signed in as Administrator'),
(13, 6, '', '', '::1', '2026-11-17 14:34:24', 'LOGOUT', 'Signed out: Admin system'),
(14, 7, '', '', '::1', '2026-11-17 14:35:06', 'REGISTER', 'New consumer account registered: staff@utilisense.com'),
(15, 7, '', '', '::1', '2026-11-17 14:35:19', 'LOGIN', 'Signed in as Consumer'),
(16, 7, '', '', '::1', '2026-11-17 14:36:54', 'ENCODE_BILL', 'Encoded Electricity bill (₱0) for User ID #5'),
(17, 7, '', '', '::1', '2026-11-17 14:37:00', 'LOGOUT', 'Signed out: System Staff'),
(18, 5, '', '', '::1', '2026-11-17 14:37:08', 'LOGIN', 'Signed in as Consumer'),
(19, 5, '', '', '::1', '2026-11-17 14:37:15', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(20, 7, '', '', '::1', '2026-11-17 14:37:23', 'LOGIN', 'Signed in as Staff'),
(21, 7, '', '', '::1', '2026-11-17 14:57:12', 'PAY_BILL', 'Marked Bill ID #1 as PAID'),
(22, 7, '', '', '::1', '2026-11-17 14:57:50', 'ENCODE_BILL', 'Encoded Electricity bill (₱109000) for User ID #5'),
(23, 7, '', '', '::1', '2026-11-17 14:57:51', 'LOGOUT', 'Signed out: System Staff'),
(24, 5, '', '', '::1', '2026-11-17 14:58:14', 'LOGIN', 'Signed in as Consumer'),
(25, 5, '', '', '::1', '2026-11-17 14:59:15', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(26, 7, '', '', '::1', '2026-11-17 14:59:23', 'LOGIN', 'Signed in as Staff'),
(27, 7, '', '', '::1', '2026-11-17 14:59:40', 'ENCODE_BILL', 'Encoded Electricity bill (₱11850) for User ID #4'),
(28, 7, '', '', '::1', '2026-11-17 15:00:54', 'ENCODE_BILL', 'Encoded Water bill (₱40040) for User ID #5'),
(29, 7, '', '', '::1', '2026-11-18 18:01:47', 'LOGIN', 'Signed in as Staff'),
(30, 7, '', '', '::1', '2026-11-18 18:15:14', 'LOGOUT', 'Signed out: System Staff'),
(31, 5, '', '', '::1', '2026-11-18 18:15:20', 'LOGIN', 'Signed in as Consumer'),
(32, 5, '', '', '::1', '2026-11-18 18:15:30', 'PAY_BILL', 'Marked Bill ID #2 as PAID'),
(33, 5, '', '', '::1', '2026-11-18 18:15:34', 'PAY_BILL', 'Marked Bill ID #4 as PAID'),
(34, 5, '', '', '::1', '2026-11-18 18:18:03', 'PAY_BILL', 'Marked Bill ID #4 as PAID'),
(35, 5, '', '', '::1', '2026-11-18 18:18:05', 'PAY_BILL', 'Marked Bill ID #4 as PAID'),
(36, 5, '', '', '::1', '2026-11-18 18:18:22', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(37, 5, '', '', '::1', '2026-11-18 18:18:45', 'LOGIN', 'Signed in as Consumer'),
(38, 5, '', '', '::1', '2026-11-18 18:19:04', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(39, 7, '', '', '::1', '2026-11-18 18:19:17', 'LOGIN', 'Signed in as Staff'),
(40, 7, '', '', '::1', '2026-11-18 18:19:39', 'ENCODE_BILL', 'Encoded Water bill (₱43992) for User ID #5'),
(41, 7, '', '', '::1', '2026-11-18 18:20:02', 'ENCODE_BILL', 'Encoded Water bill (₱43992) for User ID #5'),
(42, 7, '', '', '::1', '2026-11-18 18:20:04', 'LOGOUT', 'Signed out: System Staff'),
(43, 5, '', '', '::1', '2026-11-18 18:20:16', 'LOGIN', 'Signed in as Consumer'),
(44, 5, '', '', '::1', '2026-11-18 18:21:17', 'PAY_BILL', 'Marked Bill ID #6 as PAID'),
(45, 5, '', '', '::1', '2026-11-18 18:21:19', 'PAY_BILL', 'Marked Bill ID #5 as PAID'),
(46, 5, '', '', '::1', '2026-11-18 18:22:18', 'PAY_BILL', 'Marked Bill ID #5 as PAID'),
(47, 5, '', '', '::1', '2026-11-18 18:22:30', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(48, 7, '', '', '::1', '2026-11-18 18:22:38', 'LOGIN', 'Signed in as Staff'),
(49, 7, '', '', '::1', '2026-11-18 18:22:50', 'ENCODE_BILL', 'Encoded Internet bill (₱1699) for User ID #5'),
(50, 7, '', '', '::1', '2026-11-18 18:22:58', 'ENCODE_BILL', 'Encoded Electricity bill (₱1303500) for User ID #5'),
(51, 7, '', '', '::1', '2026-11-18 18:23:03', 'ENCODE_BILL', 'Encoded Water bill (₱4235000) for User ID #4'),
(52, 7, '', '', '::1', '2026-11-18 18:23:06', 'LOGOUT', 'Signed out: System Staff'),
(53, 5, '', '', '::1', '2026-11-18 18:23:14', 'LOGIN', 'Signed in as Consumer'),
(54, 5, '', '', '::1', '2026-11-18 18:38:14', 'ONLINE_PAYMENT', 'Paid Bill #8 (₱1303500) via GCash [Ref: UTS-PAY-06B94071]'),
(55, 5, '', '', '::1', '2026-11-18 18:38:38', 'ONLINE_PAYMENT', 'Paid Bill #7 (₱1699) via Maya (PayMaya) [Ref: UTS-PAY-9A6AB74B]'),
(56, 5, '', '', '::1', '2026-11-18 18:38:38', 'ONLINE_PAYMENT', 'Paid Bill #7 (₱1699) via Maya (PayMaya) [Ref: UTS-PAY-CE7931A2]'),
(57, 7, '', '', '::1', '2026-11-19 11:44:49', 'ENCODE_BILL', 'Staff encoded Electricity bill (₱2607) for User #4'),
(58, 7, '', '', '::1', '2026-11-19 11:45:04', 'LOGOUT', 'Signed out: System Staff'),
(59, 5, '', '', '::1', '2026-11-19 11:45:23', 'LOGIN', 'Signed in as Consumer'),
(60, 5, '', '', '::1', '2026-11-19 11:45:34', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(61, 7, '', '', '::1', '2026-11-19 11:45:43', 'LOGIN', 'Signed in as Staff'),
(62, 7, '', '', '::1', '2026-11-19 11:45:50', 'LOGOUT', 'Signed out: System Staff'),
(63, 7, '', '', '::1', '2026-11-19 11:55:49', 'LOGIN', 'Signed in as Staff'),
(64, 7, '', '', '::1', '2026-11-19 13:09:34', 'ENCODE_BILL', 'Staff encoded Electricity bill (₱2442) for User #4'),
(65, 7, '', '', '::1', '2026-11-19 13:09:37', 'LOGOUT', 'Signed out: System Staff'),
(66, 5, '', '', '::1', '2026-11-19 13:09:45', 'LOGIN', 'Signed in as Consumer'),
(67, 5, '', '', '::1', '2026-11-19 13:09:50', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(68, 7, '', '', '::1', '2026-11-19 13:10:08', 'LOGIN', 'Signed in as Staff'),
(69, 7, '', '', '::1', '2026-11-19 13:10:17', 'ENCODE_BILL', 'Staff encoded Internet bill (₱1500) for User #5'),
(70, 7, '', '', '::1', '2026-11-19 13:10:19', 'LOGOUT', 'Signed out: System Staff'),
(71, 5, '', '', '::1', '2026-11-19 13:10:26', 'LOGIN', 'Signed in as Consumer'),
(72, 5, '', '', '::1', '2026-11-19 13:10:44', 'ONLINE_PAYMENT', 'Paid Bill #12 (₱1500) via GCash [Ref: UTS-PAY-23AFCD5F]'),
(73, 5, '', '', '::1', '2026-11-19 13:11:15', 'ONLINE_PAYMENT', 'Paid Bill #12 (₱1500) via GCash [Ref: UTS-PAY-C528B657]'),
(74, 5, '', '', '::1', '2026-11-19 13:11:20', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(75, 7, '', '', '::1', '2026-11-19 13:11:36', 'LOGIN', 'Signed in as Staff'),
(76, 7, '', '', '::1', '2026-11-19 13:15:50', 'OVERDUE_BILL', 'Staff flagged Bill #11 as OVERDUE'),
(77, 7, '', '', '::1', '2026-11-19 13:16:24', 'OVERDUE_BILL', 'Staff flagged Bill #11 as OVERDUE'),
(78, 7, '', '', '::1', '2026-11-19 13:25:40', 'LOGOUT', 'Signed out: System Staff'),
(79, 5, '', '', '::1', '2026-11-19 13:26:33', 'LOGIN', 'Signed in as Consumer'),
(80, 5, '', '', '::1', '2026-11-19 13:27:23', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(81, 7, '', '', '::1', '2026-11-19 13:27:30', 'LOGIN', 'Signed in as Staff'),
(82, 7, '', '', '::1', '2026-11-19 13:29:25', 'LOGOUT', 'Signed out: System Staff'),
(83, 5, '', '', '::1', '2026-11-19 13:29:33', 'LOGIN', 'Signed in as Consumer'),
(84, 5, '', '', '::1', '2026-11-19 13:33:40', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(85, NULL, '', '', '::1', '2026-11-19 13:36:11', 'REGISTER', 'New consumer account registered: JennyRedondo@gmail.com'),
(86, NULL, '', '', '::1', '2026-11-19 13:36:50', 'REGISTER', 'New consumer account registered: Shancamerino@gmail.com'),
(87, 10, '', '', '::1', '2026-11-19 13:40:06', 'REGISTER', 'New consumer account registered: shancamerino@gmail.com'),
(88, 10, '', '', '::1', '2026-11-19 13:42:27', 'LOGIN', 'Signed in as Consumer'),
(89, 10, '', '', '::1', '2026-11-19 13:43:11', 'UPDATE_UTILITIES', 'Updated subscribed utilities (Electricity, Water, Internet)'),
(90, 10, '', '', '::1', '2026-11-19 13:43:23', 'LOGOUT', 'Signed out: Shan Camerino'),
(91, 5, '', '', '::1', '2026-11-19 14:03:45', 'LOGIN', 'Signed in as Consumer'),
(92, 5, '', '', '::1', '2026-11-19 14:09:49', 'ENCODE_BILL', 'Encoded Water bill (₱426621618.5) for User ID #5'),
(93, 5, '', '', '::1', '2026-11-19 14:12:37', 'WALKIN_SLIP', 'Generated walk-in branch payment slip (UTS-BRANCH-C90D7BFF) for Bill #13'),
(94, 5, '', '', '::1', '2026-11-19 14:15:30', 'WALKIN_SLIP', 'Generated walk-in branch payment slip (UTS-BRANCH-8EFA8BAF) for Bill #13'),
(95, 5, '', '', '::1', '2026-11-19 14:15:35', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(96, 6, '', '', '::1', '2026-11-19 14:15:42', 'LOGIN', 'Signed in as Administrator'),
(97, 6, '', '', '::1', '2026-11-19 14:18:54', 'LOGOUT', 'Signed out: Admin system'),
(98, 5, '', '', '::1', '2026-11-19 14:19:01', 'LOGIN', 'Signed in as Consumer'),
(99, 5, '', '', '::1', '2026-11-19 14:23:29', 'ONLINE_PAYMENT', 'Paid Bill #13 (₱99999999.99) via GCash [Ref: UTS-PAY-33093311]'),
(100, 5, '', '', '::1', '2026-11-19 14:23:32', 'ONLINE_PAYMENT', 'Paid Bill #13 (₱99999999.99) via GCash [Ref: UTS-PAY-6A36C114]'),
(101, 5, '', '', '::1', '2026-11-19 14:23:34', 'ONLINE_PAYMENT', 'Paid Bill #13 (₱99999999.99) via GCash [Ref: UTS-PAY-CD8D77F5]'),
(102, 5, '', '', '::1', '2026-11-19 14:44:46', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(103, 10, '', '', '::1', '2026-11-19 14:45:48', 'LOGIN', 'Signed in as Consumer'),
(104, 10, '', '', '::1', '2026-11-19 14:46:17', 'LOGOUT', 'Signed out: Shan Camerino'),
(105, 7, '', '', '::1', '2026-11-19 14:46:23', 'LOGIN', 'Signed in as Staff'),
(106, 7, '', '', '::1', '2026-11-19 14:49:39', 'ENCODE_BILL', 'Staff encoded Electricity bill (₱2398) for User #10'),
(107, 7, '', '', '::1', '2026-11-19 14:50:44', 'LOGOUT', 'Signed out: System Staff'),
(108, 10, '', '', '::1', '2026-11-19 14:50:56', 'LOGIN', 'Signed in as Consumer'),
(109, 10, '', '', '::1', '2026-11-19 14:51:14', 'ONLINE_PAYMENT', 'Paid Bill #14 (₱2398) via GCash [Ref: UTS-PAY-F31D847A]'),
(110, 10, '', '', '::1', '2026-11-19 14:54:06', 'LOGOUT', 'Signed out: Shan Camerino'),
(111, 6, '', '', '::1', '2026-11-19 14:54:51', 'LOGIN', 'Signed in as Administrator'),
(112, 6, '', '', '::1', '2026-11-19 14:55:45', 'UPDATE_STATUS', 'Set User #10 status to inactive'),
(113, 6, '', '', '::1', '2026-11-19 14:55:48', 'LOGOUT', 'Signed out: Admin system'),
(114, 6, '', '', '::1', '2026-11-19 14:56:31', 'LOGIN', 'Signed in as Administrator'),
(115, 6, '', '', '::1', '2026-11-19 14:56:34', 'UPDATE_STATUS', 'Set User #10 status to active'),
(116, 6, '', '', '::1', '2026-11-19 14:56:52', 'LOGOUT', 'Signed out: Admin system'),
(117, 5, '', '', '::1', '2026-11-19 14:57:25', 'LOGIN', 'Signed in as Consumer'),
(118, 5, '', '', '::1', '2026-11-19 14:58:12', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(119, 5, '', '', '::1', '2026-11-19 14:58:54', 'LOGIN', 'Signed in as Consumer'),
(120, 5, '', '', '::1', '2026-11-21 10:02:24', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(121, 5, '', '', '::1', '2026-11-21 11:04:01', 'LOGIN', 'Signed in as Consumer'),
(122, 5, '', '', '::1', '2026-11-21 11:04:39', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(123, 6, '', '', '::1', '2026-11-21 11:12:01', 'LOGIN', 'Signed in as Administrator'),
(124, 6, '', '', '::1', '2026-11-21 11:22:23', 'UPDATE_STATUS', 'Set User #4 status to inactive'),
(125, 6, '', '', '::1', '2026-11-21 11:22:51', 'UPDATE_STATUS', 'Set User #4 status to inactive'),
(126, 6, '', '', '::1', '2026-11-21 11:22:56', 'UPDATE_STATUS', 'Set User #4 status to inactive'),
(127, 6, '', '', '::1', '2026-11-21 11:27:01', 'LOGOUT', 'Signed out: Admin system'),
(128, 5, '', '', '::1', '2026-11-21 11:27:32', 'LOGIN', 'Signed in as Consumer'),
(129, 5, '', '', '::1', '2026-11-21 11:32:40', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(130, 5, '', '', '::1', '2026-11-21 11:38:18', 'LOGIN', 'Signed in as Consumer'),
(131, 5, '', '', '::1', '2026-11-21 11:40:26', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(132, 6, '', '', '::1', '2026-11-22 14:56:56', 'LOGIN', 'Signed in as Administrator'),
(133, 6, '', '', '::1', '2026-11-22 14:57:20', 'UPDATE_ROLE', 'Changed User #5 to role_id 1'),
(134, 6, '', '', '::1', '2026-11-22 14:57:27', 'LOGOUT', 'Signed out: Admin system'),
(135, 5, '', '', '::1', '2026-11-22 14:57:40', 'LOGIN', 'Signed in as Administrator'),
(136, 5, '', '', '::1', '2026-11-22 14:58:04', 'LOGOUT', 'Signed out: Pedrieta, Marcus Jeffry O.'),
(137, 6, '', '', '::1', '2026-11-22 14:58:15', 'LOGIN', 'Signed in as Administrator'),
(138, 6, '', '', '::1', '2026-11-22 14:58:22', 'UPDATE_ROLE', 'Changed User #5 to role_id 3');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `bill_id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `utility_id` int(11) NOT NULL,
  `billing_period` varchar(50) NOT NULL,
  `consumption_units` decimal(10,2) NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`bill_id`, `consumer_id`, `utility_id`, `billing_period`, `consumption_units`, `amount_due`, `due_date`, `status`, `created_by`, `created_at`) VALUES
(1, 3, 1, 'November 2026', 185.50, 2133.25, '2026-11-25', 'unpaid', 2, '2026-11-11 14:35:23'),
(2, 3, 2, 'November 2026', 24.00, 840.00, '2026-11-25', 'unpaid', 2, '2026-11-11 14:35:23'),
(3, 3, 3, 'November 2026', 1.00, 1500.00, '2026-11-25', 'unpaid', 2, '2026-11-11 14:35:23');

-- --------------------------------------------------------

--
-- Table structure for table `bill_payments`
--

CREATE TABLE `bill_payments` (
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_channel` varchar(80) NOT NULL,
  `account_identifier` varchar(100) DEFAULT NULL,
  `reference_no` varchar(80) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_mode` enum('ONLINE_INSTANT','WALK_IN_BRANCH') DEFAULT 'ONLINE_INSTANT',
  `paid_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bill_payments`
--

INSERT INTO `bill_payments` (`payment_id`, `bill_id`, `user_id`, `payment_channel`, `account_identifier`, `reference_no`, `amount_paid`, `payment_mode`, `paid_at`) VALUES
(2, 7, 5, 'Maya (PayMaya)', '90000000090909090', 'UTS-PAY-9A6AB74B', 1699.00, 'ONLINE_INSTANT', '2026-11-18 18:38:37'),
(3, 7, 5, 'Maya (PayMaya)', '90000000090909090', 'UTS-PAY-CE7931A2', 1699.00, 'ONLINE_INSTANT', '2026-11-18 18:38:38'),
(4, 12, 5, 'GCash', 'NDKANKDSNAKSDKASKDJH', 'UTS-PAY-23AFCD5F', 1500.00, 'ONLINE_INSTANT', '2026-11-19 13:10:44'),
(5, 12, 5, 'GCash', 'NDKANKDSNAKSDKASKDJH', 'UTS-PAY-C528B657', 1500.00, 'ONLINE_INSTANT', '2026-11-19 13:11:15'),
(11, 14, 10, 'GCash', '0999999999999', 'UTS-PAY-F31D847A', 2398.00, 'ONLINE_INSTANT', '2026-11-19 14:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `consumer_subscriptions`
--

CREATE TABLE `consumer_subscriptions` (
  `sub_id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `utility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consumer_subscriptions`
--

INSERT INTO `consumer_subscriptions` (`sub_id`, `consumer_id`, `utility_id`) VALUES
(1, 3, 1),
(2, 3, 2),
(3, 3, 3),
(4, 4, 1),
(5, 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `consumer_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('GCash','Maya','Credit/Debit Card','Bank Transfer') NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `paid_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'admin'),
(3, 'consumer'),
(2, 'staff');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `account_number` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `email`, `account_number`, `password_hash`, `account_status`, `created_at`, `status`, `last_login`) VALUES
(3, 3, 'Juan Dela Cruz', 'consumer@utilisense.com', 'UTS-1001', '$2y$10$jSWuld3FpXVWDTOHUuHV0uD1HZhD9mYS/vr97HFRZ054b2/N30H96', 'active', '2026-11-11 14:35:23', 'active', NULL),
(4, 3, 'Genshin impact', 'g95563484@gmail.com', 'UTS-1919', '$2y$10$WJtzlTsN8YiS94xyBBkZpOjemMSvN4/b15Z0uhecnnU2lhN74eyuy', 'active', '2026-11-11 14:37:06', 'inactive', NULL),
(5, 3, 'Pedrieta, Marcus Jeffry O.', 'pedrietapio@gmail.com', NULL, '$2y$10$AW.2OLGtzJnolAFcKKnV6eTeFFaiDQ2HO5GLZ1kwoPYIaivdfHSOq', 'active', '2026-11-15 14:39:51', 'active', '2026-11-22 22:57:40'),
(6, 1, 'Admin system', 'admin@utilisense.com', NULL, '$2y$10$GZzk5DsL7R6zagZosV/Ok.ACCzLvqaiOlfNWl4FTCiCxhtU8Nurni', 'active', '2026-11-17 11:22:54', 'active', '2026-11-22 22:58:14'),
(7, 2, 'System Staff', 'staff@utilisense.com', NULL, '$2y$10$6TxQ71HTLmMTFbtZgbWK8uOxFmD/ByxbkNx6TgGNkPArgjC7UE/Hy', 'active', '2026-11-17 14:35:06', 'active', '2026-11-19 22:46:23'),
(10, 3, 'Shan Camerino', 'shancamerino@gmail.com', NULL, '$2y$10$ZEjr46HQbBkxNeDzZCiOLeIlPZipP0bPkQGEMXj8CnyLdUDkAjQFO', 'active', '2026-11-19 13:40:06', 'active', '2026-11-19 22:50:56');

-- --------------------------------------------------------

--
-- Table structure for table `user_utilities`
--

CREATE TABLE `user_utilities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `utility_type` varchar(50) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `rate_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_utilities`
--

INSERT INTO `user_utilities` (`id`, `user_id`, `utility_type`, `provider_name`, `rate_per_unit`, `created_at`) VALUES
(9, 5, 'Electricity', 'Meralco', 11.85, '2026-11-17 11:06:22'),
(10, 5, 'Water', 'PrimeWater Pampanga', 38.50, '2026-11-17 11:06:22'),
(11, 5, 'Internet', 'Converge ICT FiberX', 1500.00, '2026-11-17 11:06:22'),
(12, 4, 'Electricity', 'Meralco', 0.00, '2026-11-17 14:59:40'),
(13, 4, 'Water', 'PrimeWater Pampanga', 0.00, '2026-11-18 18:23:03'),
(14, 10, 'Electricity', 'Angeles Electric Corp (AEC)', 11.95, '2026-11-19 13:43:11'),
(15, 10, 'Water', 'PrimeWater Pampanga', 38.50, '2026-11-19 13:43:11'),
(16, 10, 'Internet', 'Converge ICT FiberX', 1500.00, '2026-11-19 13:43:11');

-- --------------------------------------------------------

--
-- Table structure for table `utility_bills`
--

CREATE TABLE `utility_bills` (
  `bill_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `utility_type` varchar(50) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `previous_reading` decimal(10,2) DEFAULT 0.00,
  `present_reading` decimal(10,2) DEFAULT 0.00,
  `consumption` decimal(10,2) DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('PENDING','PAID','OVERDUE') DEFAULT 'PENDING',
  `encoded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utility_bills`
--

INSERT INTO `utility_bills` (`bill_id`, `user_id`, `utility_type`, `provider_name`, `previous_reading`, `present_reading`, `consumption`, `amount`, `due_date`, `status`, `encoded_by`, `created_at`) VALUES
(1, 5, 'Electricity', 'PELCO II', 10000.00, 10000.00, 0.00, 0.00, '2026-12-02', 'PAID', 7, '2026-11-17 14:36:54'),
(3, 4, 'Electricity', 'Meralco', 0.00, 1000.00, 1000.00, 11850.00, '2026-12-02', 'PENDING', 7, '2026-11-17 14:59:40'),
(4, 5, 'Water', 'PrimeWater Pampanga', 100.00, 1140.00, 1040.00, 40040.00, '2026-12-02', 'PAID', 7, '2026-11-17 15:00:54'),
(5, 5, 'Water', 'Angeles City Water District', 122.00, 1344.00, 1222.00, 43992.00, '2026-12-03', 'PAID', 7, '2026-11-18 18:19:39'),
(6, 5, 'Water', 'Angeles City Water District', 122.00, 1344.00, 1222.00, 43992.00, '2026-12-03', 'PAID', 7, '2026-11-18 18:20:02'),
(7, 5, 'Internet', 'PLDT Home Fiber', 0.00, 0.00, 0.00, 1699.00, '2026-12-03', 'PAID', 7, '2026-11-18 18:22:50'),
(10, 4, 'Electricity', 'Meralco', 1200.00, 1420.00, 220.00, 2607.00, '2026-12-04', 'PENDING', 7, '2026-11-19 11:44:49'),
(11, 4, 'Electricity', 'PELCO I', 1200.00, 1420.00, 220.00, 2442.00, '2026-12-04', 'OVERDUE', 7, '2026-11-19 13:09:34'),
(12, 5, 'Internet', 'Converge ICT FiberX', 1200.00, 1420.00, 220.00, 1500.00, '2026-12-04', 'PAID', 7, '2026-11-19 13:10:17'),
(14, 10, 'Electricity', 'PELCO II', 1200.00, 1420.00, 220.00, 2398.00, '2026-12-04', 'PAID', 7, '2026-11-19 14:49:39');

-- --------------------------------------------------------

--
-- Table structure for table `utility_types`
--

CREATE TABLE `utility_types` (
  `utility_id` int(11) NOT NULL,
  `utility_name` varchar(50) NOT NULL,
  `unit_label` varchar(20) NOT NULL,
  `rate_per_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utility_types`
--

INSERT INTO `utility_types` (`utility_id`, `utility_name`, `unit_label`, `rate_per_unit`) VALUES
(1, 'Electricity', 'kWh', 11.50),
(2, 'Water', 'm³', 35.00),
(3, 'Internet', 'Mbps Plan', 1500.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `consumer_id` (`consumer_id`),
  ADD KEY `utility_id` (`utility_id`);

--
-- Indexes for table `bill_payments`
--
ALTER TABLE `bill_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `bill_id` (`bill_id`);

--
-- Indexes for table `consumer_subscriptions`
--
ALTER TABLE `consumer_subscriptions`
  ADD PRIMARY KEY (`sub_id`),
  ADD UNIQUE KEY `consumer_id` (`consumer_id`,`utility_id`),
  ADD KEY `utility_id` (`utility_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `bill_id` (`bill_id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`),
  ADD KEY `consumer_id` (`consumer_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_utilities`
--
ALTER TABLE `user_utilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_utility` (`user_id`,`utility_type`);

--
-- Indexes for table `utility_bills`
--
ALTER TABLE `utility_bills`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `utility_types`
--
ALTER TABLE `utility_types`
  ADD PRIMARY KEY (`utility_id`),
  ADD UNIQUE KEY `utility_name` (`utility_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `bill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bill_payments`
--
ALTER TABLE `bill_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `consumer_subscriptions`
--
ALTER TABLE `consumer_subscriptions`
  MODIFY `sub_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_utilities`
--
ALTER TABLE `user_utilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `utility_bills`
--
ALTER TABLE `utility_bills`
  MODIFY `bill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `utility_types`
--
ALTER TABLE `utility_types`
  MODIFY `utility_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`utility_id`) REFERENCES `utility_types` (`utility_id`) ON UPDATE CASCADE;

--
-- Constraints for table `bill_payments`
--
ALTER TABLE `bill_payments`
  ADD CONSTRAINT `bill_payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `utility_bills` (`bill_id`) ON DELETE CASCADE;

--
-- Constraints for table `consumer_subscriptions`
--
ALTER TABLE `consumer_subscriptions`
  ADD CONSTRAINT `consumer_subscriptions_ibfk_1` FOREIGN KEY (`consumer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consumer_subscriptions_ibfk_2` FOREIGN KEY (`utility_id`) REFERENCES `utility_types` (`utility_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`bill_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`consumer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_utilities`
--
ALTER TABLE `user_utilities`
  ADD CONSTRAINT `user_utilities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `utility_bills`
--
ALTER TABLE `utility_bills`
  ADD CONSTRAINT `utility_bills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
