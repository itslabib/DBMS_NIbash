# 🏢 Nibash — Smart Building Management Platform

**A centralized, real-time web platform for managing residential buildings, residents, security, billing, and community operations.**

---

## 📌 Problem Statements

- **Fragmented Data Management:** Managing resident, rental, and service records across separate tools leads to data inconsistency. A centralized digital hub is required to consolidate all operations.
- **Manual Communication Gaps:** Paper-based visitor approvals and maintenance requests cause delays and human error. Automating these workflows is essential for seamless interaction between owners and residents.
- **Limited Real-time Security Visibility:** The lack of instant tracking makes it difficult to monitor building entries. A secure platform with live CCTV feeds and biometric gate recognition addresses this gap.
- **No Integrated Billing:** Manual rent and utility collection lacks traceability. A digital billing module with SSLCommerz payment integration and DESCO electricity tracking is essential.

---

## 🎯 Objectives

- Centralize all building operations into a single digital platform with role-based access control.
- Automate guest handling, notices, service bookings, and billing workflows.
- Provide real-time CCTV surveillance with motion detection, face recognition, and alert generation.
- Integrate a biometric gate recognition system for secure, logged building entry.
- Enable online rent and utility payments with full transaction history.

---

## 🚀 Key Features

### 👑 Owner Portal
| Feature | Description |
| :--- | :--- |
| **Dashboard** | Live building stats, resident count, quick-action widgets |
| **Resident Management** | Add, edit, view, and remove residents with apartment assignments |
| **CCTV Surveillance** | Real-time camera wall, capture gallery, system alerts, motion detection |
| **Guest Entries** | Log, approve, track all visitor entries, and monitor overstays |
| **Community Hub** | Building-scoped announcements and posts |
| **Messaging** | Direct messaging and communication platform |
| **Billing** | Generate and manage invoices with SSLCommerz payment gateway |
| **Profile** | Owner account and building profile management |

### 👤 Resident Portal
| Feature | Description |
| :--- | :--- |
| **Dashboard** | Personal overview with notices and recent activity |
| **Guest Passes** | Create, edit, and manage guest biometric pass entries |
| **Billing** | View invoices, DESCO recharge history, monthly consumption |
| **Parking** | Parking slot overview, license plate registration, and booking status |
| **Chatbot Support** | AI Assistant powered by Gemini for immediate query resolution |
| **Messaging** | Peer-to-peer and management communication |
| **Profile** | Update personal details and face recognition enrollment |

### 🔐 Security Systems
| Feature | Description |
| :--- | :--- |
| **CCTV Module** | IP camera + mobile device broadcast, motion capture, alerts |
| **Biometric Gate** | Face recognition at building entry using Python + OpenCV |
| **ALPR Parking** | Automatic License Plate Recognition using EasyOCR |
| **Emergency Console** | Admin-accessible emergency log and override panel |

### 🛡️ Admin Portal
| Feature | Description |
| :--- | :--- |
| **Super Admin Dashboard** | Building-wide statistics and operational overview |
| **User Management** | Manage all registered users across the platform |

---

## 🛠 Technology Stack

| Category | Technologies |
| :--- | :--- |
| **Frontend** | HTML5, Tailwind CSS, Vanilla JavaScript, Lucide Icons |
| **Backend** | PHP (procedural, mysqli), Composer |
| **PDF & Email** | DOMPDF, HTML Templates, PHPMailer |
| **Database** | MySQL (`nibash` database) |
| **Python Services** | OpenCV, face-recognition, EasyOCR, FastAPI, mysql-connector-python |
| **AI Integration** | Gemini AI API (Chatbot) |
| **Payment Gateway** | SSLCommerz |
| **Server** | XAMPP (Apache + MySQL) |
| **3D / Animations** | Three.js, GSAP (landing page) |

---

## 📦 System Requirements & Dependencies

To run this project locally, ensure you have the following installed:

### 🖥️ Core Software
- **XAMPP** (Apache + MySQL) or any equivalent stack (PHP 8.0+)
- **Python 3.8+** (Required for CCTV motion detection and Biometric Gate features)
- **Composer** (For managing PHP dependencies like PHPMailer and DOMPDF)

### 🐍 Python Dependencies
Run the following command to install the required Python libraries:
```bash
pip install -r requirements.txt
```
Key packages include:
- `opencv-python` (Face & Motion detection)
- `face-recognition` (Biometric processing)
- `easyocr`, `fastapi`, `uvicorn` (ALPR Parking integration)
- `mysql-connector-python` (Database connection)
- `requests`, `numpy`, `Pillow`

### 🐘 PHP Dependencies
Run the following command to install the required PHP libraries:
```bash
composer install
```
Key packages include:
- `phpmailer/phpmailer` (For email notifications)
- `dompdf/dompdf` (For PDF invoice generation)

---

## 📁 Project Structure

```
Nibash/
├── index.php                   # Landing page (Three.js + GSAP animated)
├── contact.php                 # Contact page
├── team.php                    # Team overview page
├── login.php                   # Login & biometric face scan entry
├── login_process.php           # Session authentication handler
├── logout.php                  # Session destroy
├── forgot_password.php         # Password reset request
├── reset_password.php          # Password reset form
├── verify.php                  # Email verification
├── community_hub.php           # Building community posts
├── emergency_console.php       # Emergency admin console
├── gate_kiosk.php              # Self-service visitor kiosk terminal
├── guest_details.php           # Public guest pass details
├── notifications_history.php   # Notification history log
├── post_guest.php              # Guest pass QR post handler
├── report.txt                  # Technical documentation report
├── generate_index.py           # Utility: face descriptor index builder
├── cctv_processor.py           # 🔴 CCTV real-time motion processor (Python)
├── requirements.txt            # Python dependencies
│
├── chatbot/                    # 🤖 Gemini AI Chatbot integration
├── messages/                   # 💬 Internal messaging system
├── parking/                    # 🚗 ALPR Python module & endpoints
├── pdf_templates/              # 📄 HTML to PDF invoice templates
├── diagram/                    # 📈 ER and Schema diagrams
│
├── owner/                      # Owner portal pages
│   ├── dashboard.php
│   ├── register.php            # Building & owner registration
│   ├── residents.php           # Resident list
│   ├── add_resident.php
│   ├── edit_resident.php
│   ├── view_resident.php
│   ├── delete_resident.php
│   ├── billing.php             # Invoice builder + SSLCommerz
│   ├── guest_entries.php       # Visitor log
│   ├── manual_guest.php        # Manual visitor entry
│   ├── delete_guest_entry.php
│   ├── profile.php
│   ├── cctv_surveillance.php   # 🎥 CCTV dashboard
│   └── cctv_broadcast.php      # 📱 Mobile camera broadcast page
│
├── resident/                   # Resident portal pages
│   ├── dashboard.php
│   ├── billing.php             # Invoice + DESCO electricity view
│   ├── guest_passes.php
│   ├── edit_guest.php
│   ├── delete_guest.php
│   ├── parking.php
│   ├── profile.php
│   └── update_db_bookings.php
│
├── admin-portal/               # Super admin panel
│   └── index.php
│
├── api/                        # JSON REST API endpoints
│   ├── cctv.php                # Camera, captures, alerts CRUD
│   ├── cctv_relay.php          # Mobile broadcast frame receiver
│   ├── billing.php             # Invoice & payment API
│   ├── gate.php                # Biometric gate recognition API
│   ├── desco.php               # DESCO electricity API
│   ├── notifications.php       # Notifications API
│   └── users.php               # User management API
│
├── includes/
│   ├── db_config.php           # MySQL connection + BASE_URL constant
│   ├── owner_sidebar.php       # Owner navigation sidebar
│   └── resident_sidebar.php    # Resident navigation sidebar
│
├── payment_integration/        # SSLCommerz payment flow
│   ├── payment_init.php        # Initialize payment session
│   ├── success.php             # Payment success handler
│   ├── fail.php                # Payment failure handler
│   ├── cancel.php              # Payment cancel handler
│   └── ipn.php                 # Instant Payment Notification
│
├── assets/
│   ├── cctv/
│   │   ├── captures/           # Motion-detected JPEG captures
│   │   └── streams/            # Live mobile broadcast frames (per building/camera)
│   └── uploads/                # Profile photos, documents
│
├── css/                        # Global stylesheets
├── js/                         # Global scripts
├── essentials/                 # Shared UI partials
├── rentals/                    # Rental listing pages
├── scripts/                    # Utility scripts
├── nibash_sql.sql              # Full database schema + seed data
└── schema.json                 # Database schema reference (JSON)
```

---

## 🎥 CCTV Surveillance System

The CCTV module supports **two camera modes**:

### Mode 1 — External IP Camera (IP Webcam App)
Connect any Android phone running the [IP Webcam](https://play.google.com/store/apps/details?id=com.pas.webcam) app. The dashboard polls `/shot.jpg` at 4 FPS via JavaScript for a zero-lag feed (no MJPEG buffering).

### Mode 2 — Built-in Mobile Browser Broadcast *(New)*
No app required. Open `owner/cctv_broadcast.php` on any mobile browser, select a registered camera, and start broadcasting. Frames are sent via HTTP POST to `api/cctv_relay.php` and stored as JPEGs in `assets/cctv/streams/`.

### Python Motion Processor (`cctv_processor.py`)
Runs in the background, reads camera streams, applies OpenCV background subtraction, and saves motion captures via the API.

| Detection Type | Triggers Alert? |
| :--- | :--- |
| `motion` | ✅ Yes (`motion_detected`) |
| `unknown` | ✅ Yes (`unknown_face`) |
| `face` | ❌ No (known resident) |

#### Quick Start
```bash
# 1. Install Python dependencies
pip install -r requirements.txt

# 2. Start the processor
python cctv_processor.py
```

#### Python Dependencies
```
opencv-python==4.8.1.78
face-recognition==1.3.5
mysql-connector-python==8.2.0
requests==2.31.0
numpy==1.24.3
Pillow==10.0.0
```

---

## 💳 Payment Integration (SSLCommerz)

Online rent and service payments are handled via SSLCommerz:

1. **Init** → `payment_integration/payment_init.php` creates a session with SSLCommerz
2. **Gateway** → User is redirected to SSLCommerz payment page
3. **Return** → On completion, redirected to `success.php`, `fail.php`, or `cancel.php`
4. **IPN** → `ipn.php` handles server-to-server payment confirmation

---

## 🔐 Biometric Gate System

The `api/gate.php` endpoint handles face recognition at the building entry gate:
- Loaded face descriptors from enrolled residents in `user_profiles`
- Compares incoming webcam frame against known faces
- Logs entry events to the database
- Flags unknown visitors as suspicious activity

---

## ⚙️ Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL) — Windows recommended
- PHP 8.0+
- Python 3.8+
- MySQL database named `nibash`

### 1. Clone / Place Files
```
C:\xampp\htdocs\Nibash\
```

### 2. Import Database
```bash
mysql -u root -p nibash < nibash_sql.sql
```

### 3. Configure Database
Edit `includes/db_config.php`:
```php
$host = 'localhost';
$user = 'root';
$password = '';       // your MySQL password
$dbname = 'nibash';
define('BASE_URL', 'http://localhost/Nibash/');
```

### 4. Install Python Dependencies
```bash
pip install -r requirements.txt
```

### 5. Start XAMPP Services
- Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 6. Access the App
| Role | URL |
| :--- | :--- |
| Landing Page | `http://localhost/Nibash/` |
| Owner Login | `http://localhost/Nibash/login.php` |
| CCTV Dashboard | `http://localhost/Nibash/owner/cctv_surveillance.php` |
| Mobile Broadcast | `http://localhost/Nibash/owner/cctv_broadcast.php` |
| Admin Portal | `http://localhost/Nibash/admin-portal/` |

### 7. Start CCTV Processor (optional)
```bash
python cctv_processor.py
```

---

## 🗄️ Key Database Tables

| Table | Purpose |
| :--- | :--- |
| `users` | Authentication accounts |
| `user_profiles` | Full name, face descriptor, profile photo |
| `apartments` | Apartment and building records |
| `apartment_assignments` | Links residents/owners to apartments |
| `cctv_devices` | Registered cameras (IP or builtin) |
| `cctv_captures` | All motion/face detection events |
| `cctv_alerts` | System alerts for unknown faces and motion |
| `guest_passes` | Guest entry QR passes |
| `invoices` | Billing records |
| `payments` | Payment transaction history |
| `notifications` | In-app notification records |
| `community_posts` | Community Hub announcements |
| `parking_slots` | Parking booking records |

---

## 🔧 API Reference

### CCTV API (`api/cctv.php`)
| Action | Method | Description |
| :--- | :--- | :--- |
| `get_dashboard` | GET | Devices, captures, alerts for a building |
| `get_capture_history` | GET | Detection history for a specific capture |
| `save_capture` | POST | Save motion/face detection (from Python) |
| `add_device` | POST | Register new camera |
| `delete_device` | POST | Remove camera and its captures |
| `delete_capture` | POST | Delete a single capture + image file |
| `delete_all_captures` | POST | Wipe all captures for a building |

### Gate API (`api/gate.php`)
Handles real-time face recognition for building entry events.

### Billing API (`api/billing.php`)
Manages invoice generation, line items, and payment status updates.

### DESCO API (`api/desco.php`)
Provides electricity recharge history and monthly consumption data for residents.

### Guest & Parking APIs
- `api/check_overstay.php`: Monitors and flags guests who have overstayed their allowed time.
- `api/parking_actions.php`: Manages parking slot assignments and license plates.
- `api/api_scan.php`: API endpoint for barcode/QR code scanning operations.

---

## 📊 CCTV Dashboard Features

| Feature | Description |
| :--- | :--- |
| **Live Camera Wall** | Hero + secondary feeds, click to swap primary |
| **JPEG Polling** | 4 FPS lag-free polling via JavaScript (replaces MJPEG) |
| **Mobile Broadcast** | Stream directly from phone browser, no app needed |
| **Recent Captures** | Horizontal scrollable gallery of last 12 captures |
| **View All Captures** | Popup grid of last 40 captures (80% screen modal) |
| **Delete All** | Bulk delete all captures + image files + alerts |
| **System Alerts** | Live feed of motion and unknown-face alerts |
| **Detection History** | Per-capture modal showing full detection timeline |
| **Add Camera** | Supports IP camera or built-in mobile broadcast mode |

---

## ⚠️ Risk & Dependencies

- **Risk:** Inconsistent data migration from manual/legacy records can reduce trust in early outputs. Validate all imports carefully.
- **Risk:** CCTV processor requires stable local network. Wi-Fi drops will cause stream reconnections (handled automatically with retry logic).
- **Dependencies:** Stable XAMPP hosting, MySQL, Python 3.8+, and local network connectivity are required for full functionality.
- **Dependencies:** SSLCommerz test/live credentials must be configured in `payment_integration/config.php` for payment features.

---

## 📝 Notes

- The `cctv_processor.log` file captures all motion events, errors, and reconnection attempts — useful for debugging.
- All captured images are stored under `assets/cctv/captures/` and physically deleted when captures are removed via the dashboard.
- Mobile broadcast frames are stored under `assets/cctv/streams/{building_id}/cam_{camera_id}.jpg` (overwritten each frame).
- The `face_hash` field in `cctv_captures` prevents duplicate capture records for the same face within a 24-hour window.

---

*Nibash — Smart Building Management Platform | Version 2.0 | Last Updated: May 2026*
"# DBMS_NIbash" 
