-- ============================================================
-- Bus Reservation System — Database Schema
-- Run this on your MySQL database after deployment
-- ============================================================

CREATE DATABASE IF NOT EXISTS majorproject;
USE majorproject;

-- Admin table
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `Email_id` VARCHAR(100) NOT NULL,
  `Password` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer table
CREATE TABLE IF NOT EXISTS `costumer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `pwd` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `address` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Buses table
CREATE TABLE IF NOT EXISTS `buses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bus_number` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route table
CREATE TABLE IF NOT EXISTS `route` (
  `sno` INT AUTO_INCREMENT PRIMARY KEY,
  `city1` VARCHAR(100) NOT NULL,
  `city2` VARCHAR(100) NOT NULL,
  `busno` VARCHAR(50) NOT NULL,
  `time` TIME NOT NULL,
  `price` DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Booking table
CREATE TABLE IF NOT EXISTS `booking` (
  `sno` INT AUTO_INCREMENT PRIMARY KEY,
  `id` INT NOT NULL,
  `bus` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `contact` VARCHAR(20) NOT NULL,
  `city1` VARCHAR(100) NOT NULL,
  `city2` VARCHAR(100) NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `seat` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Query / Contact table
CREATE TABLE IF NOT EXISTS `query` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_name` VARCHAR(100) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL,
  `user_subject` VARCHAR(200) DEFAULT NULL,
  `user_qry` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a default admin account
INSERT INTO `admin` (`name`, `Email_id`, `Password`, `phone`)
VALUES ('Admin', 'admin@example.com', 'admin123', '1234567890')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Insert a default customer account
INSERT INTO `costumer` (`name`, `email`, `pwd`, `phone`)
VALUES ('Test User', 'user@example.com', 'user123', '9876543210')
ON DUPLICATE KEY UPDATE `name`=`name`;
