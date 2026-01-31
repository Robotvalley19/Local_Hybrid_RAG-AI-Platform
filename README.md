# 🤖 Local Hybrid RAG AI Platform

Eine **vollständig lokale KI-Plattform mit Hybrid Retrieval-Augmented Generation (RAG)**,
integriert mit **Dokumentenverwaltung, Kalender, Benutzerlogin und System-Dashboard**.

💡 Optimiert für **Raspberry Pi, Home-Server und lokale Infrastrukturen**
🔒 **100 % lokal – keine Cloud, keine Telemetrie, keine Datenabflüsse**

---

## 🚀 Projektziel

Die **Local Hybrid RAG AI Platform** wurde entwickelt, um eine **private, souveräne KI-Umgebung** zu schaffen, die:

* lokale Dokumente (PDF, TXT, etc.) analysiert und versteht
* Wissen persistent speichert (Embeddings & Chat-Memory)
* moderne LLMs (Ollama, LLaMA3, Qwen3, Gemma3) lokal nutzt
* vollständig **offlinefähig** arbeitet
* modular und erweiterbar bleibt

**Zielgruppe:** Maker, Entwickler, Unternehmen und Privacy-Enthusiasten.

---

## ✨ Kernfeatures

### 🧠 KI & Hybrid RAG

* Lokales **Hybrid RAG-System** für präzise Antworten
* PDF-Parsing & Training von Dokumenten
* Embeddings für **Similarity Search / Cosine Similarity**
* Chat-Memory für **Kontext über mehrere Sessions**
* Unterstützung für Ollama LLMs
* Optionaler Web-Fallback über DuckDuckGo (deaktivierbar)

### 📁 Dokumentenportal

* Benutzer-Login mit Session-Management
* Dokumentenablage mit hierarchischer Ordnerstruktur
* Upload-Funktion für neue Dokumente
* Kalender mit Ereignissen
* Futuristisches **JARVIS-Style UI** (Orbitron, Hologramm-Effekte)

### 🖥️ System & Betrieb

* Lokaler **Flask API Server**
* Klare Trennung von Frontend & KI-Backend
* Start-Skripte für Linux / Raspberry Pi
* Erweiterbar für **Monitoring, Reverse Proxy & HTTPS**

---

## 🧩 Architektur

Die Plattform besteht aus **zwei klar getrennten Hauptkomponenten**:

1. **Web-Portal (PHP)**

   * UI, Benutzerverwaltung, Dokumente & Kalender
2. **KI-Backend (Python / Flask)**

   * RAG-System, Embeddings, LLM-Ansteuerung

➡️ **Detaillierte Architektur:** [ARCHITECTURE.md](ARCHITECTURE.md)

---

## 📂 Projektstruktur

```
Local_Hybrid_RAG-AI-Platform
│
├── Dokumentablage
│   ├── index.php
│   ├── login.php
│   ├── uploads/
│   ├── events/
│   └── static/
│       ├── css/
│       └── fonts/
│
├── Künstliche_Intelligenz
│   ├── app.py
│   ├── pdf_to_text.py
│   ├── requirements.txt
│   ├── start_app.sh
│   ├── templates/
│   └── static/
│
├── README.md
├── ARCHITECTURE.md
├── LICENSE
└── .gitignore
```

---

## ⚙️ Installation

### Voraussetzungen

* Linux / Raspberry Pi OS / Ubuntu
* Python 3.9+
* PHP 8.x
* Ollama LLM Framework
* MySQL / MariaDB (optional, empfohlen)

### KI-Backend installieren

```bash
cd Künstliche_Intelligenz
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### Ollama starten

```bash
ollama serve
ollama pull llama3
```

### Flask-App starten

```bash
bash start_app.sh
```

### PHP-Portal einbinden

* In Webserver (Apache / Nginx) integrieren
* DocumentRoot auf `Dokumentablage/` setzen

---

## 🔒 Sicherheit & Datenschutz

* **Keine Cloud-Dienste** – alles lokal
* **Keine externen APIs** notwendig
* Daten bleiben **100 % lokal**
* Upload-Verzeichnisse nicht versioniert

**Optional erweiterbar:**

* `.env` Konfiguration
* HTTPS & Reverse Proxy
* Stärkere Authentifizierung
* Benutzerrechteverwaltung

---

## 🛠️ Erweiterungsmöglichkeiten

* Vektor-Datenbanken (FAISS / Chroma) für bessere Suche
* Benutzerrollen & Rechteverwaltung
* Mehrsprachige KI
* GPU-Beschleunigung
* Docker / Docker Compose Deployment
* API-Authentifizierung (Token / JWT)

---

## 📜 Lizenz

Dieses Projekt verwendet eine **stark schützende Lizenz**,
die kommerzielle Nutzung, Weiterverkauf und proprietäre Integration untersagt.

➡️ Siehe: [LICENSE](LICENSE)

---

## 🧠 Philosophie

> **Deine Daten gehören dir.**
> Mit dieser Plattform wird gezeigt, dass moderne KI **lokal, sicher und unabhängig** betrieben werden kann – ohne Kontrolle durch Dritte.

---

## 👤 Autor

**Robotvalley19**
Private AI · Edge Computing · Local-First Systems
