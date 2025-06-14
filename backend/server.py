from flask import Flask, request, send_file
import subprocess
import pyautogui
from io import BytesIO
import sys
import os
import signal
import time

app = Flask(__name__)

UPLOAD_FOLDER = 'uploads'
ALLOWED_EXTENSIONS = {'kml'}

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

def allowed_file(filename):
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

@app.route('/', methods=['POST'])
def upload_file():
    if 'file' not in request.files:
        return 'No file part'
    file = request.files['file']
    print(file, sys.stderr)
    if file.filename == '':
        return 'No selected file'
    #elif file and allowed_file(file.filename):
    else:
        #filename = os.path.join(app.config['UPLOAD_FOLDER'], file.filename)
        filename = file.filename + ".kml";
        file.save(filename)
        # Open the uploaded file using the default application
        #process = subprocess.Popen(['google-earth-pro', filename])
        process = subprocess.Popen(['/opt/google/earth/pro/googleearth-bin', filename])
        pyautogui.sleep(1)  # Wait for 1 second before pressing Enter
        pyautogui.press("right")  # Simulate pressing the right arrow key
        pyautogui.press("enter")  # Simulate pressing the Enter key

        time.sleep(2)

        # Get the window ID of the Google Earth Pro window
        window_id_output = subprocess.check_output(["wmctrl", "-l", "-x"])
        google_earth_window_id = None
        for line in window_id_output.splitlines():
            if b"google earth pro" in line.lower():
                google_earth_window_id = line.split()[0].decode("utf-8")
                break

        # Bring the Google Earth Pro window to the foreground
        if google_earth_window_id:
            subprocess.run(["wmctrl", "-i", "-a", google_earth_window_id])
            subprocess.run(["wmctrl", "-i", "-r", google_earth_window_id, "-b", "add,maximized_vert,maximized_horz"])
            time.sleep(10)
        # Take a screenshot
        screenshot = pyautogui.screenshot()
        # Save the screenshot to a BytesIO buffer
        screenshot_buffer = BytesIO()
        screenshot.save(screenshot_buffer, format='PNG')
        screenshot_buffer.seek(0)
        # Close the subprocess
        os.kill(process.pid, signal.SIGTERM)
        # Return the screenshot buffer as a response
        return send_file(screenshot_buffer, mimetype='image/png')
    #else:
    #    print("something wrong", file=sys.stderr)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8080)
