# KU Join — Project Handoff

## ภาพรวมโปรเจกต์

เว็บไซต์ระบบลงทะเบียนเข้าร่วมงาน (อ้างอิงจาก office.eng.ku.ac.th/anniversary) ชื่อโปรเจกต์ **KU Join**

รูปแบบ: PHP Monolith แบบดั้งเดิม รันบน XAMPP (Apache + PHP) เชื่อมต่อ MySQL ผ่าน phpMyAdmin — ไม่ใช้ Next.js, ไม่ใช้ Redis เนื่องจาก traffic ของงานลงทะเบียนอีเวนต์ไม่สูงมาก

## Tech Stack

| ส่วน | ใช้อะไร |
|---|---|
| Server | XAMPP (Apache + PHP) |
| ภาษา | PHP (PDO สำหรับต่อ DB ป้องกัน SQL Injection) |
| Database | MySQL ผ่าน phpMyAdmin |
| Frontend | HTML + Bootstrap 5 (CDN) หรือ Tailwind CDN — ไม่ต้องมี build tool |
| Cache | ไม่ใช้ Redis — อาศัย PHP OPcache ที่มากับ XAMPP อยู่แล้ว |
| Auth (Admin) | PHP session (`session_start()`) + `password_hash()` / `password_verify()` |

## โครงสร้างไฟล์ (วางใน `htdocs/kujoin`)

```
kujoin/
├── config/
│   └── db.php              # PDO connection
├── includes/
│   ├── header.php
│   └── footer.php
├── index.php                 # หน้าลงทะเบียน
├── list.php                  # รายชื่อผู้เข้าร่วม
├── summary.php               # สรุปผล
├── register_submit.php       # process form (POST)
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── registrations.php     # ตาราง + แก้ไข/ลบ
│   ├── events.php            # จัดการงาน
│   ├── participant_types.php
│   ├── time_slots.php
│   └── export.php            # export CSV/Excel
└── assets/
    ├── css/
    └── js/
```

## Database Schema (Import ผ่าน phpMyAdmin)

```sql
-- งาน/อีเวนต์ (รองรับหลายงานในอนาคต)
CREATE TABLE events (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  event_date DATE NOT NULL,
  location VARCHAR(255),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ประเภทผู้เข้าร่วม (ผู้บริหาร มก. / คณบดี / ผู้บริหารและบุคลากรคณะฯ)
CREATE TABLE participant_types (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  requires_org_name BOOLEAN DEFAULT FALSE,     -- ต้องกรอกชื่อส่วนงานหรือไม่
  requires_status_field BOOLEAN DEFAULT FALSE, -- ต้องกรอกสถานภาพหรือไม่
  sort_order INT DEFAULT 0,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- ช่วงเวลากิจกรรม (เลือกได้หลายช่วง)
CREATE TABLE time_slots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_id INT NOT NULL,
  slot_time VARCHAR(20) NOT NULL,   -- '06.30'
  title VARCHAR(255) NOT NULL,      -- 'พิธีบวงสรวง'
  location VARCHAR(255),
  sort_order INT DEFAULT 0,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- ข้อมูลการลงทะเบียน
CREATE TABLE registrations (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_id INT NOT NULL,
  participant_type_id INT NOT NULL,
  org_name VARCHAR(255) NULL,             -- ชื่อส่วนงาน
  representative_name VARCHAR(255) NULL,  -- ชื่อผู้บริหาร/ผู้แทน
  position VARCHAR(255) NULL,             -- ตำแหน่ง
  companion_count INT DEFAULT 0,          -- จำนวนผู้ติดตาม
  phone VARCHAR(20),
  full_name VARCHAR(255) NOT NULL,        -- ชื่อ-สกุล
  status ENUM('ผู้บริหาร','อาจารย์อาวุโส_ผู้เกษียณ','อาจารย์','บุคลากร') NULL,
  department VARCHAR(255) NULL,           -- สังกัด
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (participant_type_id) REFERENCES participant_types(id),
  INDEX idx_event_created (event_id, created_at)
);

-- Many-to-many: 1 การลงทะเบียนเลือกได้หลายช่วงเวลา
CREATE TABLE registration_time_slots (
  registration_id INT NOT NULL,
  time_slot_id INT NOT NULL,
  PRIMARY KEY (registration_id, time_slot_id),
  FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE
);

-- บัญชีแอดมิน
CREATE TABLE admin_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','staff') DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## ตัวอย่างโค้ดเชื่อมต่อ DB (config/db.php)

```php
<?php
$host = 'localhost';
$db   = 'kujoin';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
```

## Auth ฝั่ง Admin

ทุกไฟล์ใน `/admin/*` ให้ include ไฟล์เช็ค session ด้านบนสุด:

```php
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
```

## ขั้นตอน Deploy

1. ติดตั้ง XAMPP → เปิด Apache + MySQL
2. เข้า phpMyAdmin → สร้าง database `kujoin` → Import ไฟล์ schema.sql
3. วางโปรเจกต์ทั้งหมดใน `C:\xampp\htdocs\kujoin`
4. เข้าเว็บผ่าน `http://localhost/kujoin`
5. (Production) ย้ายไป Apache+MySQL บน server ของคณะ หรือ hosting ที่รองรับ PHP/MySQL — โครงสร้างเดิมใช้ได้เลยแทบไม่ต้องแก้

## ขั้นต่อไป (Next Steps)

- [ ] เขียน `schema.sql` ฉบับสมบูรณ์ พร้อม seed ข้อมูลตัวอย่าง
- [ ] เขียน `index.php` หน้าฟอร์มลงทะเบียน
- [ ] เขียน `register_submit.php` (validate + insert)
- [ ] เขียน `list.php`, `summary.php`
- [ ] เขียนระบบ Admin (login, dashboard, CRUD, export)
