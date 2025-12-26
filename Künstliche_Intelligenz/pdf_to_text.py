import os
import mysql.connector
import PyPDF2

# === MySQL Verbindung ===
def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="Admin",             # <--- ggf. anpassen
        password="eigenes Passwort",    # <--- ggf. anpassen
        database="Künstliche_Intelligenz",
        charset="utf8mb4"
    )

# === PDF Text extrahieren ===
def extract_text_from_pdf(filepath):
    text = ""
    try:
        with open(filepath, "rb") as f:
            reader = PyPDF2.PdfReader(f)
            for page in reader.pages:
                text += page.extract_text() or ""
                text += "\n"
    except Exception as e:
        print(f"Fehler beim Lesen der PDF {filepath}: {e}")
    return text.strip()

# === PDF in Datenbank speichern, nur wenn nicht vorhanden ===
def insert_pdf_into_db(filename, content):
    db = get_db_connection()
    cursor = db.cursor()

    # Prüfen, ob die Datei schon existiert
    cursor.execute("SELECT COUNT(*) FROM Training WHERE filename = %s", (filename,))
    if cursor.fetchone()[0] > 0:
        print(f"⚠️ Datei '{filename}' existiert bereits, wird übersprungen.")
        cursor.close()
        db.close()
        return

    # Datei einfügen
    sql = "INSERT INTO Training (filename, content) VALUES (%s, %s)"
    cursor.execute(sql, (filename, content))
    db.commit()

    cursor.close()
    db.close()
    print(f"✅ Datei '{filename}' in Datenbank gespeichert.")

# === PDFs im Ordner suchen und importieren ===
def import_all_pdfs(base_path):
    print(f"Starte PDF-Import aus: {base_path}")

    for root, dirs, files in os.walk(base_path):
        for file in files:
            if file.lower().endswith(".pdf"):
                filepath = os.path.join(root, file)
                print(f"\n📄 Verarbeite: {filepath}")

                text = extract_text_from_pdf(filepath)

                if not text.strip():
                    print("⚠️ Kein extrahierbarer Text gefunden, wird übersprungen.")
                    continue

                insert_pdf_into_db(file, text)

    print("\n🎉 PDF-Import abgeschlossen!")

# === START ===
if __name__ == "__main__":
    pdf_directory = "/uploads"  # Pfad anpassen
    import_all_pdfs(pdf_directory)
