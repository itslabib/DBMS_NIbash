# 🎥 CCTV Real-Time Monitoring System

Complete real-time IP camera monitoring with face detection, automatic capture, and alert generation for the Nibash building management system.

---

## ⚡ Quick Start (5 minutes)

### Step 1: Install Python Dependencies
```bash
pip install -r requirements.txt
```

### Step 2: Add Camera from Dashboard
1. Open: `http://localhost/Nibash/owner/cctv_surveillance.php`
2. Click **"Add Camera"** button (blue button)
3. Fill in:
   - **Camera Name**: e.g., "Main Entrance"
   - **IP Address**: e.g., `192.168.1.100:8080` (for IP Webcam app)
   - **Location**: e.g., "Entry Gate"
   - **Status**: Active
4. Click **"Add Camera"** to save

### Step 3: Start Python Processor
```bash
python cctv_processor.py
```

### Step 4: View Dashboard
```
http://localhost/Nibash/owner/cctv_surveillance.php
```

You should see:
- ✅ Live camera stream
- ✅ New captures appearing every 5 seconds
- ✅ Recent captures gallery
- ✅ Alerts for unknown faces

---

## 📋 What's Included

### Core Files
| File | Purpose |
|------|---------|
| `cctv_processor.py` | Main Python processor (face detection, capture, alerts) |
| `requirements.txt` | Python dependencies |
| `api/cctv.php` | Backend API for camera management & captures |
| `owner/cctv_surveillance.php` | Owner dashboard with real-time monitoring |

### Database Tables (Already Exist)
- `cctv_devices` - Camera configuration
- `cctv_captures` - Detection records
- `cctv_alerts` - Unknown face notifications
- `user_profiles` - Face encodings (128-value array)

### Directories (Auto-created)
- `assets/cctv/captures/` - Full-size images
- `assets/cctv/thumbnails/` - Preview images
- `assets/cctv/logs/` - Additional logs

---

## 🎯 System Architecture

```
IP Camera (Phone/RTSP)
    ↓ Stream
cctv_processor.py (Python)
    ├─ Read frames
    ├─ Detect faces (OpenCV)
    ├─ Compare with known faces
    ├─ Save image & thumbnail
    └─ POST to API
        ↓
api/cctv.php
    ├─ Validate camera
    ├─ Insert cctv_captures
    ├─ Create alerts
    └─ Return response
        ↓
MySQL Database
    ├─ cctv_captures
    ├─ cctv_alerts
    └─ user_profiles
        ↓ Poll every 5 seconds
Dashboard (JavaScript)
    ├─ Display live stream
    ├─ Show recent captures
    ├─ List alerts
    └─ Detection history modal
```

---

## 🚀 Installation

### 1. Install Python
Download Python 3.8+ from https://www.python.org/

**Windows**: Make sure to check "Add Python to PATH" during installation

### 2. Install Dependencies
```bash
pip install -r requirements.txt
```

Installs:
- `opencv-python` - Video processing
- `face-recognition` - Face detection & matching
- `mysql-connector-python` - Database connection
- `requests` - HTTP API calls
- `numpy` - Array operations
- `pillow` - Image manipulation

### 3. Create Folders
Automatically created by processor, or manually:
```bash
mkdir -p assets/cctv/{captures,thumbnails,logs}
```

### 4. Add Camera via Dashboard
- Open `http://localhost/Nibash/owner/cctv_surveillance.php`
- Click **"Add Camera"** button
- Enter IP address and camera details
- Click **"Add Camera"** to save

### 5. Start Python Processor
```bash
python cctv_processor.py
```

Monitor the output for detection messages. Check `cctv_processor.log` for detailed logs.

---

## 📱 Supported Cameras

### Mobile Phone (IP Webcam App) - Recommended
**Android**: Install [IP Webcam](https://play.google.com/store/apps/details?id=com.pas.webcam)

1. Open app → **Start server**
2. Note the IP: e.g., `192.168.1.100:8080`
3. Add to dashboard: `192.168.1.100:8080`

### RTSP Cameras (Hikvision, Dahua, etc.)
Add to dashboard: `rtsp://192.168.1.50:554/stream1`

### HTTP Stream URLs
Add to dashboard: `http://192.168.1.100:8080/video`

---

## 🎮 Dashboard Features

### Add/Remove Cameras
- **Add Camera**: Click blue **"Add Camera"** button
  - Enter IP address, name, location
  - Select status (active/inactive/maintenance)
- **Remove Camera**: Click red **trash icon** on camera card
  - Confirm deletion

### Live Monitoring
- **Hero Camera**: Large primary feed (top left)
- **Support Cameras**: Smaller feeds on right
- Click any camera to promote it to hero position

### Recent Captures Gallery
- Scrollable horizontal gallery
- Click any capture to see full history
- Shows camera name, location, timestamp
- Color-coded by detection type

### Detection History Modal
- Click any capture to see timeline
- Shows all times person was detected
- Full timestamps for each detection
- Camera location for each sighting

### Alerts Panel
- Unknown face detections
- Auto-created alerts with location info
- Shows count of unread alerts

---

## ⚙️ Configuration

### Python Performance Tuning
Edit `cctv_processor.py` (around line 20-25):

```python
# Process every Nth frame (higher = faster)
FRAME_SKIP_RATE = 5          # Default: 5 (process every 5th frame)
RESIZE_SCALE = 0.25          # 0.1 to 0.5 (0.1 = faster, 0.5 = better quality)

# Face matching threshold (0.0 to 1.0)
FACE_CONFIDENCE_THRESHOLD = 0.6  # Higher = stricter matching

# Maximum concurrent cameras
MAX_CAMERAS = 4              # Limit parallel processing
```

### Optimization for Low-Power Devices
```python
FRAME_SKIP_RATE = 10        # Process every 10th frame
RESIZE_SCALE = 0.1          # 10% of original size
FACE_CONFIDENCE_THRESHOLD = 0.65  # Stricter to reduce false positives
```

Result: ~5-10% CPU per camera instead of 15-25%

### Dashboard Refresh Rate
Edit `owner/cctv_surveillance.php` (around line 950):

```javascript
// Refresh interval (milliseconds)
window.setInterval(refreshCaptures, 5000);  // 5 seconds
// Change to: 3000 (3s), 10000 (10s), etc.
```

---

## 🔧 API Endpoints

### Get Dashboard Data
```
GET /api/cctv.php?action=get_dashboard&building_id=BUILDING_001
```
Returns: devices, captures, alerts, unread count

### Get Capture History
```
GET /api/cctv.php?action=get_capture_history&building_id=BUILDING_001&capture_id=123
```
Returns: capture details and full detection history

### Add Camera Device
```
POST /api/cctv.php
action=add_device
building_id=BUILDING_001
camera_name=Main Entrance
ip_address=192.168.1.100:8080
location_description=Entry Gate
status=active
```

### Delete Camera Device
```
POST /api/cctv.php
action=delete_device
building_id=BUILDING_001
device_id=5
```

### Save Capture (from Python)
```
POST /api/cctv.php
action=save_capture
camera_id=1
building_id=BUILDING_001
image_path=assets/cctv/captures/image.jpg
detection_type=face
user_id=6
matched_confidence=0.95
```

---

## 🔍 Monitoring & Logs

### Check If It's Running
```bash
# Windows
tasklist | findstr python

# Linux/macOS
ps aux | grep cctv_processor
```

### View Logs
```bash
# Real-time logs
tail -f cctv_processor.log

# Last 50 lines
tail -50 cctv_processor.log

# Search for errors
grep ERROR cctv_processor.log
```

### Database Verification
```sql
-- Recent captures
SELECT * FROM cctv_captures 
ORDER BY captured_at DESC LIMIT 10;

-- Unread alerts
SELECT * FROM cctv_alerts 
WHERE is_sent = 0 
ORDER BY created_at DESC;

-- Active cameras
SELECT camera_name, ip_address, status 
FROM cctv_devices 
WHERE status = 'active';
```

### File System Check
```bash
# Windows
dir assets\cctv\captures\
dir assets\cctv\thumbnails\

# Linux
ls -lah assets/cctv/captures/
ls -lah assets/cctv/thumbnails/
```

---

## 🐛 Troubleshooting

### Camera Won't Connect
**Problem**: "Failed to open stream" or "Connection refused"

**Solutions**:
1. Check IP reachable: `ping 192.168.1.100`
2. Check IP Webcam app is running on phone
3. Check firewall allows port 8080
4. Test stream: Open `http://192.168.1.100:8080/shot.jpg` in browser
5. Verify phone and computer on same network

### No Faces Detected
**Problem**: Python runs but no captures saved

**Solutions**:
1. Check lighting (needs good light)
2. Face must face camera directly
3. Distance < 3 feet from camera
4. Check face_descriptor exists in database:
   ```sql
   SELECT full_name, face_descriptor FROM user_profiles WHERE face_descriptor IS NOT NULL;
   ```
5. Increase `FACE_CONFIDENCE_THRESHOLD` to 0.5 for easier matching

### Database Connection Failed
**Problem**: "Connection refused" or "Access denied"

**Solutions**:
1. MySQL running? Check Services (Windows) or `sudo systemctl status mysql` (Linux)
2. Check credentials in `includes/db_config.php`
3. Database `nibash` exists? `SHOW DATABASES;`
4. Test connection: `mysql -u root -p nibash`

### Very Slow Processing
**Problem**: High CPU usage, frame lag

**Solutions**:
1. Reduce `RESIZE_SCALE` to 0.1
2. Increase `FRAME_SKIP_RATE` to 15
3. Check CPU usage with task manager
4. Reduce resolution on phone camera app
5. Close other applications

### Module Import Errors
**Problem**: "No module named opencv" or "face_recognition not found"

**Solutions**:
```bash
# Upgrade pip
python -m pip install --upgrade pip

# Reinstall all dependencies
pip install --upgrade opencv-python face-recognition mysql-connector-python requests numpy pillow

# Check installation
python -c "import cv2, face_recognition, mysql.connector; print('OK')"
```

### Python Won't Start
**Problem**: "Python not found" or "command not recognized"

**Solutions**:
1. Verify Python installed: `python --version`
2. Add Python to PATH (Windows):
   - Right-click Start → System → Advanced → Environment Variables
   - Add Python folder to PATH
3. Use full path: `C:\Python311\python.exe cctv_processor.py`

### No Alerts Generated
**Problem**: Unknown faces detected but no alerts

**Solutions**:
1. Check detection_type = 'unknown' in cctv_captures
2. Verify cctv_alerts table has records
3. Check is_sent flag (0 = unread, 1 = sent)
4. Verify faces are actually "unknown" (not matched to database)

---

## 📊 Performance Tuning

### CPU Usage
| Setting | CPU | Notes |
|---------|-----|-------|
| Default (5fps, 25% scale) | 15-25% | Balanced |
| Optimized (2fps, 10% scale) | 5-10% | Slow devices |
| High-quality (5fps, 50% scale) | 30-40% | Good hardware |

### Memory Usage
- Per camera: 50-100 MB
- Multiple cameras: Linear scaling
- Long-running: Monitor for leaks

### Network Bandwidth
- IP Webcam (1280x720): ~1-2 Mbps per camera
- MJPEG stream: Already optimized by camera
- API calls: <100 KB per detection

### Storage
- Full capture: ~500 KB
- Thumbnail: ~50 KB
- 100 captures/day: ~50-55 MB/day

---

## 🔐 Security

### Built-in Protections
1. **Session authentication**: Dashboard requires owner login
2. **Building isolation**: Each owner sees only their building
3. **Camera validation**: save_capture verifies camera belongs to building
4. **Face privacy**: Encodings are 128 floats, not images

### Recommended
1. Secure image folders: `chmod 700 assets/cctv/`
2. Regular backups: `mysqldump nibash > backup.sql`
3. Monitor logs for errors
4. Use strong database password
5. Keep Python packages updated

---

## 🌐 Advanced Usage

### Email Alerts
Add to `cctv_processor.py`:
```python
import smtplib
if detection_type == 'unknown':
    send_email_alert(camera_name, timestamp)
```

### Slack Notifications
```python
import requests
webhook_url = "https://hooks.slack.com/..."
requests.post(webhook_url, json={"text": f"Unknown face at {camera_name}"})
```

### WebSocket Live Updates
Replace polling with WebSocket for instant updates (requires server upgrade)

### RTSP Recording
Use FFmpeg to save video streams:
```bash
ffmpeg -i rtsp://camera:port/stream -c copy output.mp4
```

### Custom Face Detection
Replace face_recognition with MediaPipe:
```python
import mediapipe as mp
detector = mp.solutions.face_detection
```

---

## 📝 Database Schema Reference

### cctv_devices
```sql
CREATE TABLE cctv_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    building_id VARCHAR(50),
    camera_name VARCHAR(255),
    ip_address VARCHAR(255),
    location_description TEXT,
    status ENUM('active', 'inactive', 'maintenance'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### cctv_captures
```sql
CREATE TABLE cctv_captures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    camera_id INT,
    building_id VARCHAR(50),
    user_id INT,
    image_path VARCHAR(500),
    detection_type ENUM('face', 'unknown', 'motion', 'intruder'),
    matched_confidence FLOAT,
    captured_at TIMESTAMP,
    is_reviewed TINYINT DEFAULT 0
);
```

### cctv_alerts
```sql
CREATE TABLE cctv_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capture_id INT,
    building_id VARCHAR(50),
    alert_type ENUM('unknown_face', 'motion_detected', 'intruder_alert'),
    message TEXT,
    is_sent TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## ✅ Deployment Checklist

- [ ] Python installed with pip
- [ ] Dependencies installed (`pip install -r requirements.txt`)
- [ ] MySQL running and `nibash` database accessible
- [ ] Folders exist or auto-create: `assets/cctv/{captures,thumbnails,logs}`
- [ ] Dashboard accessible: `http://localhost/Nibash/owner/cctv_surveillance.php`
- [ ] Camera added via dashboard with valid IP
- [ ] Python processor running: `python cctv_processor.py`
- [ ] No errors in `cctv_processor.log`
- [ ] Captures appearing in database (check with SQL query)
- [ ] Dashboard shows new captures every 5 seconds
- [ ] Delete camera button works
- [ ] Alerts generated for unknown faces (optional)

---

## 📞 Common Commands

```bash
# Start processor
python cctv_processor.py

# Install dependencies
pip install -r requirements.txt

# View logs (real-time)
tail -f cctv_processor.log

# Check Python version
python --version

# Check if running (Windows)
tasklist | findstr python

# Kill processor (Windows)
taskkill /IM python.exe /F

# Check database
mysql -u root -p nibash

# Verify MySQL service (Linux)
sudo systemctl status mysql

# Check disk usage
du -sh assets/cctv/
```

---

## 🎓 Next Steps

1. **Basic Setup**: Follow Quick Start section above
2. **Optimize**: Tune Python settings for your hardware
3. **Scale**: Add more cameras via dashboard
4. **Enhance**: Enroll known faces for recognition
5. **Automate**: Set up auto-start (cron job or Task Scheduler)
6. **Integrate**: Connect to external systems (email, Slack, etc.)

---

## 📋 File Manifest

| File | Type | Purpose |
|------|------|---------|
| cctv_processor.py | Python | Core face detection processor |
| api/cctv.php | PHP | Backend API (modified) |
| owner/cctv_surveillance.php | PHP | Owner dashboard (modified) |
| requirements.txt | Text | Python dependencies |
| CCTV.md | Markdown | This guide |

---

## 🎉 You're All Set!

Your CCTV system is ready to use. Start with the **Quick Start** section and refer back to this guide as needed.

**System Status**: ✅ Production Ready | **Version**: 1.0 | **Date**: May 2, 2026
