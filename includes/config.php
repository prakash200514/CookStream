<?php
// ─── CookStream Database Configuration ───────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'password');          // Change if your MySQL has a password
define('DB_NAME', 'cookstream');

define('SITE_URL',  'http://localhost/cookstream');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024); // 500 MB

// ─── Connection ───────────────────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die(json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]));
}

// Auto-create database
$conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);

// ─── Auto-create Tables ───────────────────────────────────────────────────────
$tables = [

"users" => "CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  UNIQUE NOT NULL,
    password        VARCHAR(255)  NOT NULL,
    is_verified     TINYINT(1)    DEFAULT 0,
    otp             VARCHAR(6)    DEFAULT NULL,
    otp_expires_at  DATETIME      DEFAULT NULL,
    avatar          VARCHAR(300)  DEFAULT NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

"channels" => "CREATE TABLE IF NOT EXISTS channels (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description TEXT,
    banner      VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB",

"videos" => "CREATE TABLE IF NOT EXISTS videos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    channel_id      INT          NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    ingredients     LONGTEXT,
    steps           LONGTEXT,
    category        ENUM('veg','non-veg') NOT NULL DEFAULT 'veg',
    video_path      VARCHAR(300) NOT NULL,
    thumbnail_path  VARCHAR(300) DEFAULT NULL,
    views           INT          DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
) ENGINE=InnoDB",

"likes" => "CREATE TABLE IF NOT EXISTS likes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    video_id    INT NOT NULL,
    user_id     INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (video_id, user_id),
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB",

"comments" => "CREATE TABLE IF NOT EXISTS comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    video_id    INT  NOT NULL,
    user_id     INT  NOT NULL,
    comment     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB",

"subscriptions" => "CREATE TABLE IF NOT EXISTS subscriptions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    channel_id  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sub (user_id, channel_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
) ENGINE=InnoDB",

"shorts" => "CREATE TABLE IF NOT EXISTS shorts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    channel_id      INT          NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    category        ENUM('veg','non-veg') NOT NULL DEFAULT 'veg',
    video_path      VARCHAR(300) NOT NULL,
    thumbnail_path  VARCHAR(300) DEFAULT NULL,
    views           INT          DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
) ENGINE=InnoDB",

"shorts_likes" => "CREATE TABLE IF NOT EXISTS shorts_likes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    short_id   INT NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_short_like (short_id, user_id),
    FOREIGN KEY (short_id) REFERENCES shorts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB",

"short_comments" => "CREATE TABLE IF NOT EXISTS short_comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    short_id   INT NOT NULL,
    user_id    INT NOT NULL,
    comment    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (short_id) REFERENCES shorts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB",

];

foreach ($tables as $name => $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table '$name': " . $conn->error);
    }
}

// ─── Session ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
