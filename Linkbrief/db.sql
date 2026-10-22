CREATE DATABASE IF NOT EXISTS linkbrief CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE linkbrief;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    short_code VARCHAR(50) NOT NULL UNIQUE,
    long_url TEXT NOT NULL,
    title VARCHAR(255) DEFAULT '',
    hits INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    password_hash VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    CONSTRAINT links_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS clicks_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    link_id INT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT,
    referrer TEXT,
    clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_link_clicked (link_id, clicked_at),
    CONSTRAINT clicks_log_ibfk_1 FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
);
