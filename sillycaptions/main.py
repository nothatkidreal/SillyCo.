import os
import queue
import sounddevice as sd
import vosk
import sys
import json

# this is all it is brochacho

# Replace with your Vosk model path
MODEL_PATH = "vosk-model-en-us-0.22"
OUTPUT_FILE = "C:/Users/micah/Downloads/speech_output.txt"

# Create output file if it doesn't exist
if not os.path.exists(OUTPUT_FILE):
    open(OUTPUT_FILE, 'w').close()

# Load model
model = vosk.Model(MODEL_PATH)
q = queue.Queue()

def callback(indata, frames, time, status):
    if status:
        print(status, file=sys.stderr)
    q.put(bytes(indata))

# Start streaming
with sd.RawInputStream(samplerate=16000, blocksize=8000, dtype='int16',
                       channels=1, callback=callback):
    rec = vosk.KaldiRecognizer(model, 16000)
    print("🎙️ Listening... Press Ctrl+C to stop.")
    try:
        while True:
            data = q.get()
            if rec.AcceptWaveform(data):
                result = json.loads(rec.Result())
                text = result.get("text", "")
                if text:
                    print("Recognized:", text)
                    with open(OUTPUT_FILE, "a") as f:
                        f.write(text + "\n")
    except KeyboardInterrupt:
        print("\n🛑 Stopped.")
