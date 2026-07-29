<div align="center">

# ⚡ TalentAI Recruit Backend

### AI-Powered Career Platform REST API

Production-ready Laravel REST API powering the TalentAI Recruit platform. It provides secure authentication, AI-powered resume analysis, cover letter generation, interview preparation, profile management, and file storage.

<p>

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-Authentication-red?style=for-the-badge)
![REST API](https://img.shields.io/badge/REST-API-success?style=for-the-badge)

</p>

---

**Developed by Raply Fediansyah**

</div>

---

# 📖 Overview

TalentAI Recruit Backend is a RESTful API built with Laravel that powers the TalentAI Recruit platform.

The backend handles:

- User Authentication
- Resume Management
- AI Resume Analysis
- AI Cover Letter Generation
- AI Interview Generator
- User Profile Management
- Avatar Upload
- Password Management
- Secure API Authentication

The API is consumed by the React + TypeScript frontend.

---

# ✨ Features

## Authentication

- User Registration
- User Login
- Logout
- Laravel Sanctum Authentication
- Token Based Authentication
- Protected API Routes

---

## Resume

- Upload Resume
- Resume History
- Resume Detail
- Delete Resume
- ATS Score Analysis
- Resume Structure Analysis
- Skill Detection
- Resume Suggestions

---

## AI Cover Letter

- Generate Cover Letter
- AI Prompt Processing
- Personalized Output

---

## AI Interview

- Generate Interview Questions
- AI Interview Preparation
- Dynamic Responses

---

## User Profile

- Get Current User Profile
- Update Profile
- Upload Avatar
- Delete Avatar
- Change Password

---

## Storage

- Resume File Upload
- Avatar Upload
- Public Storage Support
- File Validation

---

# 🏗 Tech Stack

## Backend

- Laravel 12
- PHP 8.3
- Laravel Sanctum
- MySQL
- REST API
- Eloquent ORM
- Storage API
- Validation
- Middleware

---

# 📂 Project Structure

```text
app
├── Http
│   ├── Controllers
│   ├── Middleware
│   └── Requests
│
├── Models
├── Services
├── Traits
└── Providers

database
├── migrations
├── factories
└── seeders

routes
└── api.php

storage

config

public
```

---

# 🚀 Installation

## Clone Repository

```bash
git clone https://github.com/raply075/talentai-recruit-backend.git
```

```bash
cd talentai-recruit-backend
```

---

## Install Dependencies

```bash
composer install
```

---

## Install Node Packages (optional)

```bash
npm install
```

---

## Environment

Copy environment file

```bash
cp .env.example .env
```

Generate application key

```bash
php artisan key:generate
```

---

## Configure Database

Update your `.env`

```env
APP_NAME=TalentAI
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=career_ai
DB_USERNAME=root
DB_PASSWORD=
```

---

## Run Migration

```bash
php artisan migrate
```

(Optional)

```bash
php artisan db:seed
```

---

## Storage Link

```bash
php artisan storage:link
```

---

## Run Development Server

```bash
php artisan serve
```

Server

```
http://localhost:8000
```

---

# 🔑 Main API Endpoints

## Authentication

| Method | Endpoint |
|---------|----------|
| POST | /api/register |
| POST | /api/login |
| POST | /api/logout |

---

## Resume

| Method | Endpoint |
|---------|----------|
| GET | /api/resumes |
| POST | /api/resumes |
| GET | /api/resumes/{id} |
| DELETE | /api/resumes/{id} |

---

## Cover Letter

| Method | Endpoint |
|---------|----------|
| POST | /api/cover-letter |

---

## Interview

| Method | Endpoint |
|---------|----------|
| POST | /api/interview |

---

## Profile

| Method | Endpoint |
|---------|----------|
| GET | /api/profile |
| PUT | /api/profile |
| POST | /api/profile/avatar |
| DELETE | /api/profile/avatar |
| PUT | /api/profile/password |

---

# 🔒 Security

- Laravel Sanctum Authentication
- Request Validation
- Protected Routes
- Password Hashing
- File Upload Validation
- Secure Storage
- CSRF Protection (where applicable)

---

# 📦 API Response Format

Successful response

```json
{
  "success": true,
  "message": "Success",
  "data": {}
}
```

Error response

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

---

# 🎯 Highlights

- Clean Architecture
- RESTful API
- Production Ready
- AI Integration
- Laravel Best Practices
- Secure Authentication
- Profile Management
- Resume Analysis
- Avatar Upload
- File Storage
- Scalable Structure

---

# 📄 License

This project was developed for educational purposes and portfolio showcase.

---

<div align="center">

# 👨‍💻 Developer

## Raply Fediansyah

Full Stack Web Developer

GitHub

https://github.com/raply075

---

⭐ If you find this project useful, consider giving it a star.

**Built with Laravel ❤️ by Raply Fediansyah**

</div>
