import sys
import os

# Suppress PyTorch/Tensorflow warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
import warnings
warnings.filterwarnings('ignore')

try:
    import easyocr
except ImportError:
    print("ERROR: easyocr not installed")
    sys.exit(1)

if len(sys.argv) < 2:
    print("ERROR: No image path provided")
    sys.exit(1)

image_path = sys.argv[1]

if not os.path.exists(image_path):
    print("ERROR: Image not found")
    sys.exit(1)

try:
    # Initialize EasyOCR reader (loads model)
    reader = easyocr.Reader(['en'], gpu=True, verbose=False)
    
    # Read text from image. 
    # allowlist strictly forces alphanumeric to prevent weird symbols
    results = reader.readtext(image_path, allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789- ')
    
    # Combine detected text
    detected_text = [text for (bbox, text, prob) in results if prob > 0.1]
    final_text = " ".join(detected_text).strip()
    
    # Print the result to stdout for PHP to capture
    print(final_text)
except Exception as e:
    print(f"ERROR: {str(e)}")
