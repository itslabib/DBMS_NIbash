#!/usr/bin/env python3
"""
CCTV Real-time Motion Detection Processor
- Connects to IP cameras
- Uses pure frame differencing (OpenCV) for fast, lightweight motion detection
- Runs continuously with threading for multiple cameras
"""

# pyrefly: ignore [missing-import]
import cv2
# pyrefly: ignore [missing-import]
import mysql.connector
# pyrefly: ignore [missing-import]
import requests
# pyrefly: ignore [missing-import]
import numpy as np
import json
import os
import threading
import time
import sys
from datetime import datetime
from pathlib import Path
import logging

try:
    import face_recognition  # type: ignore
    FACE_REC_AVAILABLE = True
except ImportError:
    FACE_REC_AVAILABLE = False
    print("WARNING: face_recognition module not found. Unknown face detection will be disabled.")

# Fix Windows console unicode issues
if sys.stdout.encoding != 'utf-8':
    sys.stdout.reconfigure(encoding='utf-8')  # type: ignore

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(str(Path(__file__).resolve().parent / 'cctv_processor.log')),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

# Constants
API_BASE_URL = "http://localhost/Nibash/api/cctv.php"
# cctv_processor.py lives in /cctv/ subfolder; project root is one level up
BASE_DIR = Path(__file__).resolve().parent.parent
CAPTURE_DIR = BASE_DIR / 'assets' / 'cctv' / 'captures'
os.makedirs(CAPTURE_DIR, exist_ok=True)

class VideoCaptureAsync:
    def __init__(self, src=0, name="Camera"):
        self.src = src
        self.cap = cv2.VideoCapture(self.src)
        self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        self.name = name
        self.grabbed, self.frame = False, None
        self.started = False
        self.read_lock = threading.Lock()
        self.last_frame_time = time.time()
        self.start_time = time.time()

    def start(self):
        if self.started:
            return None
        self.started = True
        self.start_time = time.time()
        self.thread = threading.Thread(target=self.update, args=(), daemon=True)
        self.thread.start()
        
        # Wait up to 5 seconds for the first frame
        wait_until = time.time() + 5.0
        while time.time() < wait_until:
            with self.read_lock:
                if self.grabbed:
                    break
            time.sleep(0.1)
            
        return self

    def update(self):
        while self.started:
            grabbed, frame = self.cap.read()
            with self.read_lock:
                self.grabbed = grabbed
                self.frame = frame  # type: ignore
                if grabbed:
                    self.last_frame_time = time.time()

    def read(self):
        with self.read_lock:
            # Only complain about timeouts if we actually successfully started
            if self.grabbed and time.time() - self.last_frame_time > 5.0:
                return False, None
            if not self.grabbed or self.frame is None:
                return False, None
            return self.grabbed, self.frame.copy()

    def stop(self):
        self.started = False
        if hasattr(self, 'thread') and self.thread.is_alive():
            self.thread.join(timeout=2.0)
            if self.thread.is_alive():
                logger.warning(f"Thread for {self.name} is hung. Skipping cap.release() to avoid deadlock.")
                return
        if self.cap:
            self.cap.release()

class CCTVMotionProcessor:
    def __init__(self):
        self.db = None
        self.camera_threads = {}
        self.running = True
        self.last_motion_time = {} # { camera_id: timestamp }
        self.motion_cooldown = 5.0 # Seconds between captures

        self.known_face_encodings = []
        self.known_face_names = []
        self.known_face_types = []
        self.known_face_ids = []

        logger.info("Initializing Motion Detection Processor...")

    def connect_db(self):
        try:
            # Note: We assume includes/db_config.php settings
            self.db = mysql.connector.connect(
                host="localhost",
                user="root",
                password="",
                database="nibash"
            )
            return True
        except Exception as e:
            logger.error(f"Database connection failed: {e}")
            return False

    def load_known_faces(self):
        if not FACE_REC_AVAILABLE:
            logger.warning("face_recognition not installed, skipping known faces load.")
            return

        logger.info("Loading known faces from database...")
        self.known_face_encodings = []
        self.known_face_names = []
        self.known_face_types = []
        self.known_face_ids = []
        
        if not self.connect_db():
            return
            
        try:
            cursor = self.db.cursor(dictionary=True)  # type: ignore
            
            # Load from guests table
            cursor.execute("SELECT id, full_name, face_descriptor FROM guests WHERE face_descriptor IS NOT NULL")
            guests = cursor.fetchall()
            for guest in guests:
                try:
                    descriptor = json.loads(guest['face_descriptor'])
                    self.known_face_encodings.append(np.array(descriptor))
                    self.known_face_names.append(guest['full_name'])
                    self.known_face_types.append('guest')
                    self.known_face_ids.append(guest['id'])
                except Exception as e:
                    logger.warning(f"Error loading guest face {guest['id']}: {e}")
                    
            # Load from user_profiles table using profile_image
            cursor.execute("SELECT user_id, full_name, profile_image FROM user_profiles WHERE profile_image IS NOT NULL AND profile_image != ''")
            users = cursor.fetchall()
            for user in users:
                try:
                    # Construct absolute path to image
                    img_path = BASE_DIR / user['profile_image'].lstrip('/')
                    if img_path.exists():
                        image = face_recognition.load_image_file(str(img_path))
                        encodings = face_recognition.face_encodings(image)
                        if encodings:
                            self.known_face_encodings.append(encodings[0])
                            self.known_face_names.append(user['full_name'])
                            self.known_face_types.append('user')
                            self.known_face_ids.append(user['user_id'])
                        else:
                            logger.warning(f"No face found in profile image for user {user['user_id']}")
                except Exception as e:
                    logger.warning(f"Error loading user face {user['user_id']}: {e}")
                    
            cursor.close()
            logger.info(f"Loaded {len(self.known_face_encodings)} known faces.")
        except Exception as e:
            logger.error(f"Error fetching known faces: {e}")
        finally:
            if self.db and self.db.is_connected():
                self.db.close()

    def get_active_cameras(self):
        if not self.connect_db():
            return []
        try:
            cursor = self.db.cursor(dictionary=True)  # type: ignore
            cursor.execute("SELECT id, building_id, camera_name, ip_address FROM cctv_devices WHERE status = 'active'")
            cameras = cursor.fetchall()
            cursor.close()
            return cameras
        except Exception as e:
            logger.error(f"Error fetching cameras: {e}")
            return []
        finally:
            if self.db and self.db.is_connected():
                self.db.close()

    def save_capture(self, camera_id, building_id, frame, detection_type='motion', user_id=None):
        """Sends the captured frame to the PHP API to register in the database"""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S_%f')
        filename = f"cam_{camera_id}_{timestamp}.jpg"
        
        building_dir = CAPTURE_DIR / str(building_id)
        os.makedirs(building_dir, exist_ok=True)
        
        filepath = building_dir / filename
        rel_path = f"/assets/cctv/captures/{building_id}/{filename}"
        
        # Save image physically
        cv2.imwrite(str(filepath), frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
        
        # Send payload to API
        payload = {
            'action': 'save_capture',
            'camera_id': camera_id,
            'building_id': building_id,
            'image_path': rel_path,
            'thumbnail_path': rel_path,
            'detection_type': detection_type,
            'matched_confidence': 100.0 if detection_type == 'face' else None,
            'user_id': user_id,
            'apt_id': None,
            'face_hash': None
        }
        
        try:
            response = requests.post(
                API_BASE_URL, 
                json=payload,
                headers={'Content-Type': 'application/json'},
                timeout=5
            )
            if response.status_code == 200:
                return True
        except Exception as e:
            logger.error(f"API Error saving capture: {e}")
            
        return False

    def process_camera_stream(self, camera_info):
        camera_id = camera_info['id']
        camera_name = camera_info['camera_name']
        stream_url = camera_info['ip_address']
        building_id = camera_info['building_id']
        stop_event = camera_info.get('_stop_event')
        
        is_builtin = stream_url == 'builtin'
        
        if not is_builtin:
            # Format stream URL for IP Webcam
            if not stream_url.startswith('http'):
                stream_url = f"http://{stream_url}"
            if stream_url.count('/') == 2: # e.g. http://192.168.0.102:8080
                stream_url = f"{stream_url}/video"
                
            logger.info(f"Starting: {camera_name} (ID: {camera_id}) | Stream: {stream_url}")
        else:
            logger.info(f"Starting: {camera_name} (ID: {camera_id}) | Built-in Mobile Camera Relay")
        
        cap = None
        background_subtractor = cv2.createBackgroundSubtractorMOG2(history=500, varThreshold=50, detectShadows=False)
        reconnect_delay = 5
        
        while self.running and (not stop_event or not stop_event.is_set()):
            try:
                frame = None
                if is_builtin:
                    # Read from local temp stream file
                    file_path = BASE_DIR / 'assets' / 'cctv' / 'streams' / str(building_id) / f"cam_{camera_id}.jpg"
                    if file_path.exists():
                        frame = cv2.imread(str(file_path))
                    
                    if frame is None:
                        time.sleep(0.5)
                        continue
                    else:
                        time.sleep(0.1) # 10 FPS rate limit
                else:
                    if cap is None:
                        cap = VideoCaptureAsync(stream_url, camera_name).start()
                        if not cap or not cap.cap.isOpened():
                            logger.warning(f"Could not connect to {camera_name}. Retrying in {reconnect_delay}s...")
                            time.sleep(reconnect_delay)
                            cap = None
                            continue
                            
                    grabbed, frame = cap.read()
                    
                    if not grabbed or frame is None:
                        logger.warning(f"Lost stream for {camera_name}. Reconnecting...")
                        cap.stop()
                        cap = None
                        time.sleep(reconnect_delay)
                        continue

                # Resize frame for faster processing
                small_frame = cv2.resize(frame, (0, 0), fx=0.5, fy=0.5)
                gray = cv2.cvtColor(small_frame, cv2.COLOR_BGR2GRAY)
                gray = cv2.GaussianBlur(gray, (21, 21), 0)
                
                # Apply background subtraction
                fgmask = background_subtractor.apply(gray)
                
                # Threshold to isolate motion
                _, thresh = cv2.threshold(fgmask, 25, 255, cv2.THRESH_BINARY)
                
                # Find motion contours
                contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
                
                motion_detected = False
                for c in contours:
                    if cv2.contourArea(c) > 800: # Threshold for motion size
                        motion_detected = True
                        break
                        
                if motion_detected:
                    now_ts = time.time()
                    last_time = self.last_motion_time.get(camera_id, 0)
                    
                    if now_ts - last_time >= self.motion_cooldown:
                        self.last_motion_time[camera_id] = now_ts
                        
                        if FACE_REC_AVAILABLE and len(self.known_face_encodings) > 0:
                            logger.info(f"Motion detected on {camera_name}! Analyzing face...")
                            rgb_frame = cv2.cvtColor(small_frame, cv2.COLOR_BGR2RGB)
                            face_locations = face_recognition.face_locations(rgb_frame)
                            face_encodings = face_recognition.face_encodings(rgb_frame, face_locations)
                            
                            if not face_encodings:
                                logger.info("No face detected. Saving as motion.")
                                self.save_capture(camera_id, building_id, frame, detection_type='motion')
                            else:
                                for face_encoding in face_encodings:
                                    matches = face_recognition.compare_faces(self.known_face_encodings, face_encoding, tolerance=0.55)
                                    det_type = "unknown"
                                    matched_user_id = None
                                    
                                    face_distances = face_recognition.face_distance(self.known_face_encodings, face_encoding)
                                    if len(face_distances) > 0:
                                        best_match_index = np.argmin(face_distances)
                                        if matches[best_match_index]:
                                            det_type = "face"
                                            if self.known_face_types[best_match_index] == 'user':
                                                matched_user_id = self.known_face_ids[best_match_index]
                                            logger.info(f"Matched face: {self.known_face_names[best_match_index]}")
                                    
                                    if det_type == "unknown":
                                        logger.info("Unknown face detected!")
                                        
                                    self.save_capture(camera_id, building_id, frame, detection_type=det_type, user_id=matched_user_id)
                                    break # just process first face for now
                        else:
                            logger.info(f"Motion detected on {camera_name}! Saving capture...")
                            self.save_capture(camera_id, building_id, frame)
                
                # Small sleep to yield CPU
                time.sleep(0.05)
                
            except Exception as e:
                logger.error(f"Stream error on {camera_name}: {e}")
                if cap:
                    cap.stop()
                    cap = None
                time.sleep(reconnect_delay)

        if cap:
            cap.stop()

    def start(self):
        self.camera_threads = {}
        logger.info("Starting CCTV Processor. Monitoring for active cameras...")
        self.load_known_faces()
        
        try:
            while self.running:
                active_cameras = self.get_active_cameras()
                active_ids = {cam['id']: cam for cam in active_cameras}
                
                # Start new cameras
                for cam_id, cam_info in active_ids.items():
                    if cam_id not in self.camera_threads:
                        cam_info['_stop_event'] = threading.Event()
                        t = threading.Thread(target=self.process_camera_stream, args=(cam_info,), daemon=True)
                        self.camera_threads[cam_id] = (t, cam_info['_stop_event'])
                        t.start()
                
                # Stop removed/inactive cameras
                current_ids = list(self.camera_threads.keys())
                for cam_id in current_ids:
                    if cam_id not in active_ids:
                        logger.info(f"Camera ID {cam_id} no longer active. Stopping thread...")
                        t, stop_event = self.camera_threads[cam_id]
                        stop_event.set()
                        t.join(timeout=2.0)
                        del self.camera_threads[cam_id]
                        
                time.sleep(5)
        except KeyboardInterrupt:
            logger.info("Stopping CCTV Processor...")
            self.running = False
            for cam_id, (t, stop_event) in self.camera_threads.items():
                stop_event.set()
                t.join(timeout=2.0)
            logger.info("Shutdown complete.")

if __name__ == "__main__":
    CCTVMotionProcessor().start()