-- Database Schema for Live Chat Stream v2 (With Admin & Recipient Support)
CREATE DATABASE IF NOT EXISTS livechat_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE livechat_db;

-- 1. Tabel Settings (Pengaturan Global oleh Admin)
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nilai Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('stream_title', 'Interactive Live Stream Experience'),
('auth_mode', 'registration_open'), -- Opsi: 'anonymous', 'registration_open', 'registered_only'
('require_recipient', '1'),         -- 1 = Wajib isi untuk siapa, 0 = Opsional / Nonaktif
('recipients_list', 'Semua / Umum, Host Streamer, Wisudawan / Pembicara') -- Pilihan default atau manual
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- 2. Tabel Admin
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin default: admin / admin123
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$9BEsrcEpvDkvO0enXGBYeemy9M7.IZHTt42U4eLkZRtLk7WpeyPSS')
ON DUPLICATE KEY UPDATE username=username;

-- 3. Tabel Attendees (Daftar User / Penonton Terdaftar)
CREATE TABLE IF NOT EXISTS attendees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    default_recipient VARCHAR(60) DEFAULT 'Umum',
    code_or_phone VARCHAR(50) DEFAULT NULL,
    is_allowed TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Messages (Komentar & Penerima)
CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL,
    recipient VARCHAR(60) DEFAULT 'Umum',
    message VARCHAR(255) NOT NULL,
    color VARCHAR(20) DEFAULT '#ff0055',
    is_approved TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_id (id),
    INDEX idx_approved (is_approved),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
