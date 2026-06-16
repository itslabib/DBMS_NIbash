import os
import base64
import easyocr
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import warnings

# Suppress warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
warnings.filterwarnings('ignore')

app = FastAPI()

# Global EasyOCR Reader instance
# This is loaded once when the server starts!
print("Loading EasyOCR Model... Please wait.")
try:
    reader = easyocr.Reader(['en'], gpu=True, verbose=False)
    print("EasyOCR Model loaded successfully!")
except Exception as e:
    print(f"Failed to load EasyOCR model with GPU, falling back to CPU: {e}")
    reader = easyocr.Reader(['en'], gpu=False, verbose=False)
    print("EasyOCR Model loaded on CPU successfully!")

class ScanRequest(BaseModel):
    image: str

@app.post("/scan")
async def scan_plate(request: ScanRequest):
    try:
        base64_string = request.image
        # Remove data URI scheme if present
        if base64_string.startswith("data:image"):
            base64_string = base64_string.split(",")[1]
            
        image_data = base64.b64decode(base64_string)
        
        # Save temp file for OCR reader
        # (EasyOCR can read bytes directly, but saving a temp file is completely fine and safe)
        temp_path = "temp_scan_fastapi.jpg"
        with open(temp_path, "wb") as f:
            f.write(image_data)
            
        # Run OCR
        results = reader.readtext(temp_path, allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789- ')
        
        # Cleanup
        if os.path.exists(temp_path):
            os.remove(temp_path)
            
        # Parse results
        detected_text = [text for (bbox, text, prob) in results if prob > 0.1]
        final_text = " ".join(detected_text).strip()
        
        return {"status": "success", "text": final_text}
        
    except Exception as e:
        return {"status": "error", "message": str(e)}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)
