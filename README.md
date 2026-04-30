<<<<<<< HEAD
# 🍳 CookStream
=======
<H1> COOKSTREAM </H1>
🍳 CookStream
>>>>>>> aeabfdd5629350d0ada17e72ab0371f135fbeb69

> **A YouTube-style cooking video platform** — stream recipes, share your culinary creations, and build a community of food lovers.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=flat-square&logo=apache&logoColor=white)
![PHPMailer](https://img.shields.io/badge/PHPMailer-OTP_Auth-green?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Database Schema](#-database-schema)
- [Installation & Setup](#-installation--setup)
- [Configuration](#-configuration)
- [Usage Guide](#-usage-guide)
- [API Endpoints](#-api-endpoints)
- [Screenshots](#-screenshots)
- [Contributing](#-contributing)

<<<<<<< HEAD
---
=======
>>>>>>> aeabfdd5629350d0ada17e72ab0371f135fbeb69

## 🌟 Overview

**CookStream** is a full-stack cooking video-sharing platform built with PHP and MySQL. It allows food enthusiasts to:

- Upload and stream **cooking videos** with full recipe metadata (ingredients + steps)
- Share quick **Shorts** (vertical video clips)
- Create and manage a **personal Channel**
- **Subscribe** to channels, **like** videos, and leave **comments**
- Filter content by **Veg / Non-Veg** categories
- Register securely with **Email OTP Verification**

<<<<<<< HEAD
---
=======
>>>>>>> aeabfdd5629350d0ada17e72ab0371f135fbeb69

## ✨ Features

### 👤 Authentication
| Feature | Details |
|---|---|
| User Registration | Name, email, password with role selection |
| Email OTP Verification | 6-digit OTP sent via SMTP (PHPMailer/Gmail) |
| OTP Expiry | Configurable expiry window |
| Secure Login | Password hashing (bcrypt) |
| Session Management | PHP native sessions |
| Logout | Session destruction |

### 📺 Videos
| Feature | Details |
|---|---|
| Video Upload | MP4, WebM, OGG, MOV — up to 500 MB |
| Thumbnail Upload | JPEG, PNG, WebP, GIF |
| Recipe Metadata | Ingredients (JSON array) + Steps (JSON array) |
| Categories | Veg / Non-Veg badge system |
| View Counter | Incremented on each watch |
| Like / Unlike | Heart toggle with AJAX (no page reload) |
| Comments | Real-time comment submission |
| Watch Page | YouTube-style player with sidebar recommendations |

### 📱 Shorts
| Feature | Details |
|---|---|
| Short Video Upload | Vertical format cooking clips |
| Shorts Feed | TikTok/Reels-style full-screen scroll |
| Like & Comment | Per-short engagement system |
| Channel Shorts Tab | Dedicated shorts section on channel page |

### 📡 Channel
| Feature | Details |
|---|---|
| Channel Creation | Name, description, banner image |
| Channel Dashboard | Video & shorts management |
| Subscriber Count | Live subscription counter |
| Subscribe / Unsubscribe | Bell animation + toast notification |
| Creator Controls | Upload, manage, and delete content |

### 🏠 Homepage
- Featured video grid
- Shorts preview strip
- Category filter (All / Veg / Non-Veg)
- View counts and time-ago timestamps
- Responsive card layout

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.1+ (procedural + OOP) |
| **Database** | MySQL 8.0+ via MySQLi |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Email** | PHPMailer + Gmail SMTP |
| **Server** | Apache (XAMPP) |
| **File Storage** | Local filesystem (`/uploads`) |
| **Session** | PHP native sessions |

<<<<<<< HEAD
---

## 📁 Project Structure
=======
📁 Project Structure
>>>>>>> aeabfdd5629350d0ada17e72ab0371f135fbeb69

```
cookstream/
├── index.php                  # Homepage — video grid + shorts strip
│
├── auth/
│   ├── register.php           # User registration form & handler
│   ├── verify_otp.php         # OTP input & verification
│   ├── login.php              # Login form & session init
│   └── logout.php             # Session destroy & redirect
│
├── video/
│   ├── upload.php             # Video upload form (title, recipe, category)
│   └── watch.php              # Video player page (likes, comments, subscribe)
│
├── shorts/
│   ├── upload.php             # Shorts upload form
│   └── view.php               # Full-screen shorts feed
│
├── channel/
│   ├── create.php             # Channel creation form
│   └── dashboard.php          # Channel management (videos + shorts tabs)
│
├── api/
│   ├── like.php               # AJAX — toggle video like
│   ├── comment.php            # AJAX — post video comment
│   ├── subscribe.php          # AJAX — toggle channel subscription
│   ├── short_like.php         # AJAX — toggle short like
│   └── short_comment.php      # AJAX — post short comment
│
├── includes/
│   ├── config.php             # DB connection + auto-create tables + session
│   ├── functions.php          # Utility helpers (formatViews, timeAgo, OTP, etc.)
│   ├── auth.php               # Auth guard (requireLogin helper)
│   └── mailer.php             # PHPMailer SMTP wrapper (sendOtpEmail)
│
├── assets/
│   ├── css/                   # Global stylesheets
│   ├── js/                    # Frontend scripts
│   └── img/                   # Static images & icons
│
├── uploads/                   # User-uploaded files (git-ignored)
│   ├── videos/                # Full-length cooking videos
│   ├── thumbnails/            # Video thumbnail images
│   └── shorts/                # Short video clips
│
└── README.md
```

---

## 🗄 Database Schema

CookStream uses **9 tables** that are **auto-created** on first run via `includes/config.php`.

```
users
 ├── id (PK)
 ├── name, email (UNIQUE), password
 ├── is_verified, otp, otp_expires_at
 ├── avatar
 └── created_at

channels
 ├── id (PK)
 ├── user_id → users.id
 ├── name, description, banner
 └── created_at

videos
 ├── id (PK)
 ├── channel_id → channels.id
 ├── title, description
 ├── ingredients (JSON), steps (JSON)
 ├── category (ENUM: veg | non-veg)
 ├── video_path, thumbnail_path
 ├── views
 └── created_at

shorts
 ├── id (PK)
 ├── channel_id → channels.id
 ├── title, description
 ├── category (ENUM: veg | non-veg)
 ├── video_path, thumbnail_path
 ├── views
 └── created_at

likes              → (video_id, user_id) UNIQUE
comments           → (video_id, user_id, comment)
subscriptions      → (user_id, channel_id) UNIQUE
shorts_likes       → (short_id, user_id) UNIQUE
short_comments     → (short_id, user_id, comment)
```

---

## 🚀 Installation & Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8.1+)
- [Composer](https://getcomposer.org/) (for PHPMailer)
- A **Gmail account** with an [App Password](https://support.google.com/accounts/answer/185833) enabled

### Step 1 — Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/your-username/cookstream.git
cd cookstream
```

### Step 2 — Install PHPMailer

```bash
composer require phpmailer/phpmailer
```

> If you don't have Composer, download PHPMailer manually and place it in `vendor/`.

### Step 3 — Configure the App

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Your MySQL password (blank for default XAMPP)
define('DB_NAME', 'cookstream');
define('SITE_URL', 'http://localhost/cookstream');
```

Edit `includes/mailer.php` with your Gmail SMTP credentials:

```php
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';   // Gmail App Password
```

### Step 4 — Create Upload Directories

```bash
mkdir uploads\videos
mkdir uploads\thumbnails
mkdir uploads\shorts
```

Or let the app auto-create them on first upload.

### Step 5 — Start XAMPP

1. Open **XAMPP Control Panel**
2. Start **Apache** and **MySQL**
3. Visit `http://localhost/cookstream`

> ✅ The database and all tables are **created automatically** on first page load — no SQL import needed!

---

## ⚙️ Configuration

### `includes/config.php`

| Constant | Default | Description |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | `password` | MySQL password |
| `DB_NAME` | `cookstream` | Database name |
| `SITE_URL` | `http://localhost/cookstream` | Base URL |
| `UPLOAD_DIR` | `../uploads/` | Absolute path to uploads |
| `MAX_VIDEO_SIZE` | `500 MB` | Max video file size |

### `includes/mailer.php`

| Setting | Description |
|---|---|
| `SMTPHost` | `smtp.gmail.com` |
| `SMTPPort` | `587` (TLS) |
| `Username` | Your Gmail address |
| `Password` | Your Gmail **App Password** (not your login password) |

---

## 📖 Usage Guide

### For Viewers
1. **Register** at `/auth/register.php` → verify email via OTP
2. **Browse** videos on the homepage — filter by Veg / Non-Veg
3. **Watch** a video → like ❤️, comment 💬, subscribe 🔔
4. **Scroll Shorts** at `/shorts/view.php`

### For Creators
1. **Create a Channel** at `/channel/create.php`
2. **Upload Videos** at `/video/upload.php` — add ingredients & steps
3. **Upload Shorts** at `/shorts/upload.php`
4. **Manage Content** via your Channel Dashboard at `/channel/dashboard.php`

---

## 🔌 API Endpoints

All API endpoints are AJAX-based and return JSON responses.

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `/api/like.php` | POST | ✅ | Toggle like on a video |
| `/api/comment.php` | POST | ✅ | Post a comment on a video |
| `/api/subscribe.php` | POST | ✅ | Toggle channel subscription |
| `/api/short_like.php` | POST | ✅ | Toggle like on a short |
| `/api/short_comment.php` | POST | ✅ | Post a comment on a short |

### Request Format (JSON body or form-data)

```json
// Like a video
POST /api/like.php
{ "video_id": 42 }

// Subscribe to a channel
POST /api/subscribe.php
{ "channel_id": 7 }
```

### Response Format

```json
{ "success": true, "liked": true, "count": 128 }
{ "success": false, "message": "Not logged in" }
```

---

## 🎨 UI Highlights

- **Dark-themed** premium design with gradient accents
- **Pill-shaped action buttons** — like (heart) + subscribe (bell)
- **Toast notifications** for user feedback (no page reloads)
- **Veg / Non-Veg badges** — green dot (Veg), red dot (Non-Veg)
- **Responsive grid** layout for all screen sizes
- **YouTube-style watch page** with collapsible recipe panel

---

## 🔐 Security

- Passwords hashed with **`password_hash()` / `password_verify()`** (bcrypt)
- All user inputs sanitized with `htmlspecialchars` + `strip_tags`
- **Prepared statements** used for all database queries (no raw SQL injection)
- OTP expires after a configurable time window
- Session-based authentication guard on all protected pages

<<<<<<< HEAD
---
=======
>>>>>>> aeabfdd5629350d0ada17e72ab0371f135fbeb69

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "Add my feature"`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a Pull Request

---

## 📄 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**Prakash** — Built with ❤️ for cooking enthusiasts everywhere.

> *"Share your recipes. Inspire the world."*
<<<<<<< HEAD
=======

>>>>>>> aeabfdd5629350d0ada17e72ab0371f135fbeb69
