"""Simulador de un modelo de lenguaje con API compatible con OpenAI (solo para pruebas locales).

Permite probar el flujo del agente de n8n de punta a punta sin claves reales ni coste:
responde de forma determinista llamando a las herramientas en orden
(consultar_pedido → consultar_historial_cliente → clasificar_incidencia) y termina con
la salida estructurada. NO es el agente: el agente real usa Groq (u otro proveedor).

Uso:  python3 llm_simulado.py 8300 [fichero_registro.jsonl]
En n8n: credencial «OpenAI» con Base URL http://localhost:8300/v1 y cualquier clave.
"""
import json, os, re, sys, time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PUERTO = int(sys.argv[1]) if len(sys.argv) > 1 else 8300
REGISTRO = sys.argv[2] if len(sys.argv) > 2 else None


def texto(m):
    c = m.get("content")
    if isinstance(c, list):
        return " ".join(p.get("text", "") for p in c if isinstance(p, dict))
    return c or ""


def decidir(cuerpo):
    mensajes = cuerpo.get("messages", [])
    herramientas = [t["function"]["name"] for t in cuerpo.get("tools", []) if t.get("type") == "function"]
    usuario = " ".join(texto(m) for m in mensajes if m.get("role") == "user")
    llamadas = [tc["function"]["name"] for m in mensajes if m.get("role") == "assistant" for tc in (m.get("tool_calls") or [])]
    resultados = [texto(m) for m in mensajes if m.get("role") == "tool"]
    ref = re.search(r"KN-\d{4}-\d{6}", usuario)
    inc = re.search(r"[Ii]ncidencia[^0-9]{0,12}(\d+)", usuario)
    cli = None
    for r in resultados:
        m = re.search(r'"cliente_id"\s*:\s*(\d+)', r)
        if m:
            cli = m.group(1)
    if not cli:
        m = re.search(r"[Cc]liente[^0-9]{0,12}(\d+)", usuario)
        cli = m.group(1) if m else None
    danado = re.search(r"roto|rota|dañad|golpe|doblad|broken|damaged", usuario, re.I)

    def llamar(nombre, argumentos):
        return {"tool_calls": [{"id": f"call_{nombre}_{int(time.time()*1000)}", "type": "function",
                                "function": {"name": nombre, "arguments": json.dumps(argumentos, ensure_ascii=False)}}]}

    def nombre_que_contiene(trozo):
        return next((h for h in herramientas if trozo in h), None)

    h = nombre_que_contiene("pedido")
    if h and h not in llamadas and ref:
        return llamar(h, {"referencia": ref.group(0)})
    h = nombre_que_contiene("historial")
    if h and h not in llamadas and cli:
        return llamar(h, {"cliente_id": int(cli)})
    h = nombre_que_contiene("clasificar")
    if h and h not in llamadas and inc:
        return llamar(h, {"incidencia_id": int(inc.group(1)), "categoria": "producto_danado" if danado else "consulta",
                          "prioridad": "media" if danado else "baja",
                          "motivo": "El cliente describe un producto dañado" if danado else "Consulta sin urgencia"})
    salida = {
        "tipo_caso": "producto_danado" if danado else "consulta",
        "datos_consultados": [f"pedido {ref.group(0)}" if ref else "sin pedido", f"historial del cliente {cli}" if cli else "sin cliente"],
        "accion_propuesta": "proponer_reposicion" if danado else "responder_cliente",
        "mensaje_cliente": ("Hola, sentimos mucho que tu pedido haya llegado dañado. Te enviamos una unidad nueva sin coste; "
                            "no hace falta que devuelvas la dañada. Te avisaremos por aquí cuando salga.") if danado else
                           "Hola, gracias por escribirnos. Hemos revisado tu pedido y te respondemos en breve con los detalles.",
        "justificacion": "Primera incidencia del cliente y producto de bajo importe: la reposición es la solución más rápida." if danado
                         else "Consulta informativa sin impacto económico.",
        "confianza": 0.82 if danado else 0.7,
        "prioridad": "media" if danado else "baja",
        "riesgos": ["Comprobar que queda stock antes de reponer"] if danado else [],
        "estado_final": "pendiente_revision",
    }
    h = next((x for x in herramientas if "format" in x or "final" in x), None)
    if h:
        # La herramienta de formato de n8n espera el objeto dentro de «output».
        esquema = next((t["function"].get("parameters", {}) for t in cuerpo.get("tools", []) if t["function"]["name"] == h), {})
        return llamar(h, {"output": salida} if "output" in esquema.get("properties", {}) else salida)
    return {"content": json.dumps(salida, ensure_ascii=False)}


class Manejador(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def _json(self, codigo, datos):
        b = json.dumps(datos).encode()
        self.send_response(codigo)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(b)))
        self.end_headers()
        self.wfile.write(b)

    def do_GET(self):
        self._json(200, {"object": "list", "data": [{"id": "simulado", "object": "model", "owned_by": "pruebas"}]})

    def do_POST(self):
        cuerpo = json.loads(self.rfile.read(int(self.headers.get("Content-Length", 0))) or b"{}")
        # Si existe el fichero «FALLO» junto al registro, se simula una clave rechazada (para probar la rama de error).
        if REGISTRO and os.path.exists(os.path.join(os.path.dirname(REGISTRO), "FALLO")):
            return self._json(401, {"error": {"message": "Invalid API Key (simulado)", "type": "invalid_request_error", "code": "invalid_api_key"}})
        if not self.path.rstrip("/").endswith("chat/completions"):
            return self._json(404, {"error": {"message": "solo chat/completions"}})
        msg = decidir(cuerpo)
        caracteres = len(json.dumps(cuerpo))
        if REGISTRO:
            with open(REGISTRO, "a") as f:
                f.write(json.dumps({"caracteres_peticion": caracteres, "respuesta": msg, "herramientas": [t["function"]["name"] for t in cuerpo.get("tools", [])],
                                    "mensajes": cuerpo.get("messages")}, ensure_ascii=False) + "\n")
        self._json(200, {"id": "chatcmpl-sim", "object": "chat.completion", "created": int(time.time()), "model": cuerpo.get("model", "simulado"),
                         "choices": [{"index": 0, "finish_reason": "tool_calls" if "tool_calls" in msg else "stop",
                                      "message": {"role": "assistant", "content": msg.get("content"), **({"tool_calls": msg["tool_calls"]} if "tool_calls" in msg else {})}}],
                         "usage": {"prompt_tokens": caracteres // 4, "completion_tokens": 60, "total_tokens": caracteres // 4 + 60}})


ThreadingHTTPServer(("127.0.0.1", PUERTO), Manejador).serve_forever()
