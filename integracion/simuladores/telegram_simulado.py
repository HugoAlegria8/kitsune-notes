"""Simulador de la API de bots de Telegram (solo para pruebas locales, sin bot real).

Responde a sendMessage, getMe y setWebhook como lo haría api.telegram.org y guarda
cada mensaje enviado en un fichero para poder comprobarlo.

Uso:  python3 telegram_simulado.py 8400 mensajes.jsonl
En n8n: KITSUNE_TELEGRAM_API=http://localhost:8400
"""
import json, sys, time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

PUERTO = int(sys.argv[1]) if len(sys.argv) > 1 else 8400
REGISTRO = sys.argv[2] if len(sys.argv) > 2 else "mensajes.jsonl"
contador = [100]


class Manejador(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def _responder(self, datos, codigo=200):
        b = json.dumps(datos).encode()
        self.send_response(codigo)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def _atender(self, datos):
        metodo = urlparse(self.path).path.rsplit("/", 1)[-1]
        if metodo == "getMe":
            return self._responder({"ok": True, "result": {"id": 1, "is_bot": True, "username": "KitsuneNotesDemoBot"}})
        if metodo == "sendMessage":
            if not datos.get("chat_id") or not datos.get("text"):
                return self._responder({"ok": False, "error_code": 400, "description": "Bad Request: message text is empty"}, 400)
            if str(datos.get("chat_id")) == "999":
                return self._responder({"ok": False, "error_code": 403, "description": "Forbidden: bot was blocked by the user"}, 403)
            contador[0] += 1
            with open(REGISTRO, "a") as f:
                f.write(json.dumps({"metodo": metodo, **datos, "hora": time.time()}, ensure_ascii=False) + "\n")
            return self._responder({"ok": True, "result": {"message_id": contador[0], "chat": {"id": datos["chat_id"]}, "date": int(time.time()), "text": datos["text"]}})
        return self._responder({"ok": True, "result": True})

    def do_GET(self):
        self._atender({k: v[0] for k, v in parse_qs(urlparse(self.path).query).items()})

    def do_POST(self):
        crudo = self.rfile.read(int(self.headers.get("Content-Length", 0)) or 0)
        try:
            datos = json.loads(crudo or b"{}")
        except ValueError:
            datos = {k: v[0] for k, v in parse_qs(crudo.decode()).items()}
        self._atender(datos)


ThreadingHTTPServer(("127.0.0.1", PUERTO), Manejador).serve_forever()
