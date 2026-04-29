<H1> COOKSTREAM </H1>
🍳 CookStream

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


## 🌟 Overview

**CookStream** is a full-stack cooking video-sharing platform built with PHP and MySQL. It allows food enthusiasts to:

- Upload and stream **cooking videos** with full recipe metadata (ingredients + steps)
- Share quick **Shorts** (vertical video clips)
- Create and manage a **personal Channel**
- **Subscribe** to channels, **like** videos, and leave **comments**
- Filter content by **Veg / Non-Veg** categories
- Register securely with **Email OTP Verification**


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
