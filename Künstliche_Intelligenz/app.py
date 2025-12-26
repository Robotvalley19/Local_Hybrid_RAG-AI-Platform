from flask import Flask, render_template, request, jsonify
import psutil
import json
import os
import requests
from duckduckgo_search import DDGS
import subprocess
import mysql.connector
from sentence_transformers import SentenceTransformer
import numpy as np

# --- Ollama Pfad und Model-Verzeichnis ---
os.environ["PATH"] += os.pathsep + "/usr/local/bin"
os.environ["OLLAMA_MODELS"] = "/ollama"

app = Flask(__name__)

# --- Konfiguration ---
MODEL_NAME = "llama3"
CHAT_LOG_FILE = "chat_log.json"
CONTEXT_LENGTH = 5
API_URL = "http://localhost:11434"  # Lokaler Ollama-Server

# --- MySQL Verbindung ---
def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="Admin",
        password="eigens Passwort",
        database="Künstliche_Intelligenz",
        charset="utf8mb4"
    )

# --- Embeddings-Modell laden ---
embedding_model = SentenceTransformer('all-MiniLM-L6-v2')

def get_embedding(text):
    return embedding_model.encode(text, normalize_embeddings=True)

def cosine_similarity(a, b):
    return np.dot(a, b)

# --- RAG-Suche: Trainingsdaten + Chatverlauf ---
def search_db_rag(question, top_n=5):
    db = get_db_connection()
    cursor = db.cursor()
    
    cursor.execute("SELECT filename, content FROM Training")
    training_rows = cursor.fetchall()
    
    cursor.execute("SELECT id, user_message, bot_response FROM Chatverlauf ORDER BY created_at DESC")
    chat_rows = cursor.fetchall()
    
    cursor.close()
    db.close()
    
    if not training_rows and not chat_rows:
        return ""
    
    q_emb = get_embedding(question)
    similarities = []
    
    # Trainingsdaten vergleichen
    for filename, content in training_rows:
        text_emb = get_embedding(content[:1000])
        sim = cosine_similarity(q_emb, text_emb)
        similarities.append((sim, f"[Training:{filename}]", content[:1000]))
    
    # Chatverlauf vergleichen
    for cid, user_msg, bot_msg in chat_rows:
        combined = f"User: {user_msg}\nBot: {bot_msg}"
        text_emb = get_embedding(combined[:1000])
        sim = cosine_similarity(q_emb, text_emb)
        similarities.append((sim, f"[Chat:{cid}]", combined[:1000]))
    
    similarities.sort(reverse=True, key=lambda x: x[0])
    top_texts = similarities[:top_n]
    
    formatted = "\n\n".join(f"{label}\n{text}..." for _, label, text in top_texts)
    return formatted

# --- Chat-Log ---
def append_chat_log(user_message, bot_response, model=MODEL_NAME, mode="local"):
    # --- Lokale JSON ---
    chat_history = load_chat_log()
    chat_history.append({"user": user_message, "bot": bot_response})
    with open(CHAT_LOG_FILE, "w", encoding="utf-8") as f:
        json.dump(chat_history, f, ensure_ascii=False, indent=4)

    # --- Datenbank speichern ---
    try:
        db = get_db_connection()
        cursor = db.cursor()
        cursor.execute(
            "INSERT INTO Chatverlauf (session_id, user_message, bot_response, model, mode, json_log) VALUES (%s, %s, %s, %s, %s, %s)",
            ("no-session", user_message, bot_response, model, mode, json.dumps({"user": user_message, "bot": bot_response}, ensure_ascii=False))
        )
        db.commit()
        cursor.close()
        db.close()
    except Exception as e:
        print(f"Fehler beim Speichern des Chatverlaufs in DB: {e}")

def load_chat_log():
    if os.path.exists(CHAT_LOG_FILE):
        try:
            with open(CHAT_LOG_FILE, "r", encoding="utf-8") as f:
                return json.load(f)
        except json.JSONDecodeError:
            return []
    return []

# --- Systeminfo ---
def get_gpu_info():
    try:
        result = subprocess.run(
            ["nvidia-smi", "--query-gpu=index,name,utilization.gpu,memory.used,memory.total,temperature.gpu",
             "--format=csv,noheader,nounits"],
            capture_output=True, text=True, check=True
        )
        gpus = []
        for line in result.stdout.strip().split("\n"):
            index, name, util, mem_used, mem_total, temp = line.split(", ")
            gpus.append({
                "index": index,
                "name": name,
                "auslastung": util,
                "speicher_verwendet": mem_used,
                "speicher_gesamt": mem_total,
                "temperatur": temp
            })
        return gpus
    except Exception:
        return [{"info": "Keine dedizierte GPU erkannt oder nvidia-smi nicht verfügbar"}]

def get_cpu_temperature():
    try:
        with open("/sys/class/thermal/thermal_zone0/temp", "r") as f:
            temp_raw = int(f.read().strip())
        return [f"{temp_raw / 1000:.1f}°C"]
    except Exception:
        try:
            result = subprocess.run(["sensors"], capture_output=True, text=True, check=True)
            return [line.strip() for line in result.stdout.splitlines() if "Package id" in line or "Core" in line]
        except Exception:
            return ["Temperatur nicht verfügbar"]

# --- Ollama Verbindung ---
def test_api_connection():
    try:
        r = requests.get(f"{API_URL}/api/version", timeout=5)
        r.raise_for_status()
        return True, f"✅ Ollama API erreichbar (Version {r.json().get('version', '?')})"
    except Exception as e:
        return False, f"⚠️ Ollama API nicht erreichbar: {e}"

def get_installed_models():
    try:
        result = subprocess.run(["ollama", "list"], capture_output=True, text=True, check=True)
        lines = result.stdout.strip().splitlines()
        models = [line.split()[0] for line in lines[1:]] if len(lines) > 1 else []
        return models
    except Exception:
        return ["Fehler: ollama list nicht ausführbar"]

def chat_api(messages, model):
    try:
        url = f"{API_URL}/api/chat"
        payload = {"model": model, "messages": messages}
        r = requests.post(url, json=payload, timeout=None, stream=True)
        if r.status_code == 200:
            response_text = ""
            for line in r.iter_lines():
                if line:
                    try:
                        data = json.loads(line.decode("utf-8"))
                        if "message" in data and "content" in data["message"]:
                            response_text += data["message"]["content"]
                    except Exception:
                        pass
            if response_text.strip():
                return response_text.strip()
        return "(⚠️ Keine Antwort vom Modell)"
    except Exception as e:
        return f"(Fehler bei der Verbindung zu Ollama: {e})"

def simple_summary(text, max_len=500):
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    first_lines = lines[:10]
    return " | ".join(first_lines)[:max_len]

# --- Frageverarbeitung inkl. Hybridmodus ---
def process_question(user_input, model, mode):
    chat_history = load_chat_log()
    recent_chats = chat_history[-CONTEXT_LENGTH:]
    summarized_chats = " | ".join([f"User: {c['user']} / Bot: {c['bot']}" for c in recent_chats])

    db_context = search_db_rag(user_input)
    rag_context = f"{db_context}\n" if db_context else ""

    web_text = ""
    if mode in ["online", "hybrid"] and not db_context:
        try:
            ddg = DDGS()
            results = ddg.text(user_input, max_results=2)
            if results:
                web_text = "\n".join([f"- {r['title']}: {r.get('body','')} ({r['href']})" for r in results])
        except Exception as e:
            web_text = f"Fehler bei Websuche: {e}"

    full_context = ""
    if rag_context:
        full_context += f"[DB & Chat RAG]\n{rag_context}\n"
    if web_text:
        full_context += f"[Web-Ergebnisse]\n{web_text}\n"
    if summarized_chats:
        full_context += f"[Kurze Zusammenfassung früherer Chats]\n{summarized_chats}\n"

    messages = [
        {"role": "system", "content": "Antworte auf Deutsch. Nutze alle Infos für die Antwort:\n" + full_context},
        {"role": "user", "content": user_input}
    ]

    response = chat_api(messages, model)
    if not response or "Keine Antwort" in response:
        response = simple_summary(user_input)

    append_chat_log(user_input, response, model, mode)
    return response

# --- Flask Routen ---
@app.route('/')
def index():
    cpu_percent = psutil.cpu_percent(interval=0.5)
    mem = psutil.virtual_memory()
    gpu_info = get_gpu_info()
    cpu_temps = get_cpu_temperature()
    models = get_installed_models()
    chat_history = load_chat_log()
    api_ok, api_msg = test_api_connection()
    return render_template(
        "index.html",
        model_name=MODEL_NAME,
        cpu_percent=cpu_percent,
        memory_percent=mem.percent,
        memory_total=round(mem.total / (1024 ** 3), 2),
        memory_used=round(mem.used / (1024 ** 3), 2),
        gpu_info=gpu_info,
        cpu_temps=cpu_temps,
        models=models,
        chat_history=chat_history,
        api_ok=api_ok,
        api_msg=api_msg
    )

@app.route('/ask', methods=['POST'])
def ask():
    data = request.json
    user_input = data.get("message")
    model = data.get("model", MODEL_NAME)
    mode = data.get("mode", "local")
    response = process_question(user_input, model, mode)
    return jsonify({"response": response})

@app.route('/status')
def status():
    cpu_percent = psutil.cpu_percent(interval=0.5)
    mem = psutil.virtual_memory()
    gpu_info = get_gpu_info()
    cpu_temps = get_cpu_temperature()
    models = get_installed_models()
    api_ok, api_msg = test_api_connection()
    return jsonify({
        "cpu_percent": cpu_percent,
        "memory_percent": mem.percent,
        "memory_total": round(mem.total / (1024 ** 3), 2),
        "memory_used": round(mem.used / (1024 ** 3), 2),
        "gpu_info": gpu_info,
        "cpu_temps": cpu_temps,
        "models": models,
        "api_ok": api_ok,
        "api_msg": api_msg
    })

@app.route('/clear_chat', methods=['POST'])
def clear_chat():
    try:
        with open(CHAT_LOG_FILE, "w", encoding="utf-8") as f:
            json.dump([], f, ensure_ascii=False, indent=4)
        db = get_db_connection()
        cursor = db.cursor()
        cursor.execute("DELETE FROM Chatverlauf")
        db.commit()
        cursor.close()
        db.close()
        return jsonify({"status": "ok", "message": "Chat gelöscht."})
    except Exception as e:
        return jsonify({"status": "error", "message": f"Fehler beim Löschen des Chats: {e}"})

# --- Start ---
if __name__ == '__main__':
    api_ok, api_msg = test_api_connection()
    print(api_msg)
    print("🚀 Flask-App läuft auf http://localhost:5010")
    app.run(host="0.0.0.0", port=5010, debug=True)
