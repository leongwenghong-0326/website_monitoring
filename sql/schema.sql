-- Website Monitoring System with Telegram Alerts
-- Import this file in phpMyAdmin if you are not using install.php

CREATE DATABASE IF NOT EXISTS `website_monitoring` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `website_monitoring`;

CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(120) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `websites` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `interval_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
    `status` ENUM('up','down','unknown') NOT NULL DEFAULT 'unknown',
    `last_checked` DATETIME DEFAULT NULL,
    `response_time` INT UNSIGNED DEFAULT NULL,
    `slow_threshold_ms` INT UNSIGNED NOT NULL DEFAULT 3000,
    `show_on_status_page` TINYINT(1) NOT NULL DEFAULT 1,
    `last_alert_status` VARCHAR(20) DEFAULT NULL,
    `is_slow` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `website_id` INT UNSIGNED NOT NULL,
    `status` ENUM('up','down') NOT NULL,
    `response_time` INT UNSIGNED DEFAULT NULL,
    `http_code` INT DEFAULT NULL,
    `error_message` VARCHAR(255) DEFAULT NULL,
    `is_slow` TINYINT(1) NOT NULL DEFAULT 0,
    `checked_at` DATETIME NOT NULL,
    CONSTRAINT `fk_logs_website` FOREIGN KEY (`website_id`) REFERENCES `websites`(`id`) ON DELETE CASCADE,
    INDEX `idx_website_checked` (`website_id`, `checked_at`),
    INDEX `idx_checked_at` (`checked_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alerts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `website_id` INT UNSIGNED NOT NULL,
    `alert_type` ENUM('down','recovery','slow') NOT NULL,
    `message` TEXT,
    `created_at` DATETIME NOT NULL,
    CONSTRAINT `fk_alerts_website` FOREIGN KEY (`website_id`) REFERENCES `websites`(`id`) ON DELETE CASCADE,
    INDEX `idx_alerts_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
