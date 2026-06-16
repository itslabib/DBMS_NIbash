@echo off
echo =========================================
echo Nibash AI Parking Scanner Server
echo =========================================
echo.

:: Check for required packages
echo Checking Python dependencies...
python -c "import fastapi, uvicorn, pydantic" >nul 2>&1
if %errorlevel% neq 0 (
    echo Installing required packages - fastapi, uvicorn...
    pip install fastapi uvicorn pydantic python-multipart
    echo Packages installed successfully!
) else (
    echo Dependencies are already installed.
)

echo.
echo Starting FastAPI Server...
echo NOTE: Keep this window open while the parking system is running!
echo To stop the server, simply close this window.
echo.

:: Start the server
python ocr_server.py

pause
