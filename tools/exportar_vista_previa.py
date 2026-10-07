#!/usr/bin/env python3
"""
Exporta una vista previa estática navegable de Kitsune Notes.

Recorre la aplicación PHP en ejecución, guarda el HTML generado de cada
página y reescribe los enlaces para que el conjunto funcione como sitio
estático. El recorrido de compra se conserva como paseo pregrabado: los
botones del flujo avanzan al siguiente paso capturado, y el resto de
formularios muestran un aviso en lugar de fallar en silencio.

Uso:  python3 tools/exportar_vista_previa.py [URL_BASE] [DESTINO]
"""

import re
import shutil
import sys
from pathlib import Path

import requests
from bs4 import BeautifulSoup

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080"
DESTINO = Path(sys.argv[2] if len(sys.argv) > 2 else "vista-previa")
RAIZ = Path(__file__).resolve().parent.parent

CATEGORIAS = ["cuadernos", "escritura", "washi-y-pegatinas", "organizacion"]
COLECCIONES = ["kitsune", "neko", "tokki", "gom"]
PRODUCTOS = [
    "cuaderno-neko-nyan-a5", "libreta-tokki-mochi-a6", "diario-kitsune-kawaii-b6",
    "boligrafos-gom-gom-038-pack3", "rotuladores-kitsune-pastel-set6", "pluma-neko-patita-f",
    "washi-tokki-fresa-set5", "washi-gom-picnic-set3", "pegatinas-kitsune-mood-120",
    "planner-neko-siesta-2026", "organizador-gom-casita", "archivador-tokki-nube-a4",
]

AVISO = (
    "Vista previa estática del prototipo · el recorrido de compra avanza sobre una cesta de "
    "ejemplo pregrabada. La versión real en PHP calcula precios, genera el pedido y registra "
    "los eventos en vivo."
)


# ---------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------

def token(html: str) -> str:
    m = re.search(r'name="_token" value="([^"]+)"', html)
    return m.group(1) if m else ""


def mapa_urls() -> dict:
    m = {
        "/": "index.html",
        "/catalogo": "catalogo.html",
        "/carrito": "carrito.html",
        "/checkout": "checkout.html",
        "/pago": "pago.html",
        "/pedidos": "pedidos.html",
        "/soporte": "soporte.html",
        "/colecciones": "colecciones.html",
        "/sobre-kitsune": "sobre-kitsune.html",
        "/aviso-academico": "aviso-academico.html",
        "/envios": "envios.html",
        "/admin/login": "admin-login.html",
        "/admin": "admin.html",
        "/admin/pedidos": "admin-pedidos.html",
        "/admin/eventos": "admin-eventos.html",
        "/admin/incidencias": "admin-incidencias.html",
        "/admin/correos": "admin-correos.html",
        "/admin/productos": "admin-productos.html",
        "/admin/productos/nuevo": "admin-producto-nuevo.html",
    }
    for c in CATEGORIAS:
        m[f"/categoria/{c}"] = f"categoria-{c}.html"
        m[f"/catalogo?categoria={c}"] = f"categoria-{c}.html"
    for c in COLECCIONES:
        m[f"/coleccion/{c}"] = f"coleccion-{c}.html"
        m[f"/catalogo?coleccion={c}"] = f"coleccion-{c}.html"
    for p in PRODUCTOS:
        m[f"/producto/{p}"] = f"producto-{p}.html"
    return m


MAPA = mapa_urls()

# Formularios: acción -> (destino estático | None para deshabilitar)
FLUJO = {
    "/carrito/anadir": "carrito.html",
    "/checkout": "pago.html",
    "/pago": "pedido.html",
    "/soporte": "soporte-enviado.html",
    "/admin/login": "admin.html",
    "/admin/logout": "admin-login.html",
    "/pedidos": "pedido.html",
    "/catalogo": "catalogo.html",   # buscador y orden (GET)
    "/admin/correos": "admin-correos.html",   # filtro del buzón (GET)
}


ORIGENES = ("http://127.0.0.1:8080", "http://localhost:8080", "http://localhost:8000",
            "http://127.0.0.1:8000")


def a_ruta_interna(href: str) -> str:
    """Los correos llevan URL absolutas de la aplicación: se reducen a su ruta."""
    for origen in ORIGENES + (BASE.rstrip("/"),):
        if href.startswith(origen):
            return href[len(origen):] or "/"
    return href


def destino_estatico(href: str) -> str | None:
    """Traduce una URL de la aplicación a su fichero estático (conserva el #fragmento)."""
    destino = _destino_sin_fragmento(href)

    if destino not in (None, *INERTES) and "#" in href:
        destino += "#" + href.split("#", 1)[1]

    return destino


# Destinos que no existen en la copia estática, con el aviso que se muestra al pulsarlos.
INERTES = {
    "__aviso__": "El API de eventos (JSON/CSV) responde solo con el servidor PHP en marcha.",
    "__aviso_idioma__": (
        "El cambio de idioma (ES/EN) y de moneda necesita el servidor PHP del prototipo: "
        "la vista previa estática solo incluye la versión en español."
    ),
}


def _destino_sin_fragmento(href: str) -> str | None:
    if not href or href.startswith(("#", "mailto:", "http://", "https://")):
        return None

    limpio = href.split("#")[0]

    # Botones ES/EN de la cabecera: enlazan a la misma página con «?idioma=xx».
    if re.search(r"[?&]idioma=", limpio):
        return "__aviso_idioma__"

    if limpio in MAPA:
        return MAPA[limpio]

    ruta, _, consulta = limpio.partition("?")

    # La factura y los correos van antes que las reglas genéricas de pedido.
    if ruta.startswith("/pedido/") and ruta.endswith("/factura"):
        return "factura.html"
    if ruta.startswith("/admin/pedidos/") and ruta.endswith("/factura"):
        return "admin-factura.html"
    if ruta.startswith("/admin/correos/") and ruta.endswith("/descargar"):
        return "__aviso__"
    if ruta.startswith("/admin/correos/"):
        return "admin-correo.html"
    if ruta.startswith("/pedido/"):
        return "pedido.html"
    if ruta.startswith("/admin/pedidos/"):
        return "admin-pedido.html"
    if ruta.startswith("/admin/productos/") and ruta.endswith("/editar"):
        return "admin-producto.html"
    if ruta.startswith("/api/"):
        return "__aviso__"
    if ruta == "/catalogo":
        for clave in ("categoria", "coleccion"):
            m = re.search(rf"{clave}=([a-z0-9-]+)", consulta)
            if m and f"/catalogo?{clave}={m.group(1)}" in MAPA:
                return MAPA[f"/catalogo?{clave}={m.group(1)}"]
        return "catalogo.html"
    if ruta in MAPA:
        return MAPA[ruta]

    return "__aviso__"


BANNER = """
<div class="vp-aviso">
  <strong>Vista previa estática</strong>
  <span>{texto}</span>
</div>
"""

ESTILO = """
<style>
.vp-aviso{background:#D6336C;color:#fff;font:700 .82rem/1.45 system-ui,sans-serif;
  padding:.55rem 1rem;text-align:center;display:flex;gap:.5rem;justify-content:center;
  align-items:center;flex-wrap:wrap}
.vp-aviso strong{background:rgba(255,255,255,.22);border-radius:99px;padding:.1rem .7rem}
.vp-toast{position:fixed;left:50%;bottom:1.4rem;transform:translateX(-50%) translateY(140%);
  background:#5A2340;color:#FFE3EE;padding:.8rem 1.3rem;border-radius:16px;max-width:min(92vw,460px);border:3px solid #FF9EC4;
  font:500 .88rem/1.5 system-ui,sans-serif;box-shadow:0 14px 34px -14px rgba(0,0,0,.6);
  transition:transform .28s ease;z-index:9999;text-align:center}
.vp-toast.visible{transform:translateX(-50%) translateY(0)}
.vp-inerte{opacity:.62;cursor:not-allowed}
</style>
"""

SCRIPT = """
<script>
(function(){
  var toast=document.createElement('div');
  toast.className='vp-toast';
  toast.setAttribute('role','status');
  document.addEventListener('DOMContentLoaded',function(){document.body.appendChild(toast);});
  var t;
  function avisar(msg){
    toast.textContent=msg;
    toast.classList.add('visible');
    clearTimeout(t);
    t=setTimeout(function(){toast.classList.remove('visible');},4200);
  }
  document.addEventListener('click',function(e){
    var el=e.target.closest('[data-vp-inerte]');
    if(!el)return;
    e.preventDefault();
    avisar(el.getAttribute('data-vp-inerte'));
  });
  document.addEventListener('submit',function(e){e.preventDefault();
    avisar('Esta acción necesita el servidor PHP del prototipo.');});
}());
</script>
"""


def reescribir(html: str, titulo_pagina: str) -> str:
    sopa = BeautifulSoup(html, "html.parser")

    # --- Enlaces --------------------------------------------------
    for a in sopa.find_all("a", href=True):
        destino = destino_estatico(a["href"])

        if destino is None:
            if a["href"].startswith("http"):
                a["target"] = "_blank"
                a["rel"] = "noopener"
            continue

        if destino in INERTES:
            a["href"] = "#"
            a["data-vp-inerte"] = INERTES[destino]
            a["class"] = (a.get("class") or []) + ["vp-inerte"]
        else:
            a["href"] = destino

    # --- Correos: el HTML del mensaje viaja dentro de un iframe srcdoc ----
    for marco in sopa.find_all("iframe", attrs={"srcdoc": True}):
        correo = BeautifulSoup(marco["srcdoc"], "html.parser")
        for a in correo.find_all("a", href=True):
            interna = a_ruta_interna(a["href"])
            if interna == a["href"]:
                continue
            destino = destino_estatico(interna)
            a["href"] = "#" if destino is None or destino in INERTES else destino
        marco["srcdoc"] = str(correo)
        # La copia estática no contiene scripts ni datos introducidos por nadie,
        # y el correo no ejecuta JavaScript. Con ``allow-same-origin`` el enlace
        # de la factura también abre desde un fichero local (file://); la
        # aplicación PHP real conserva el iframe con el sandbox más estricto.
        marco["sandbox"] = "allow-popups allow-popups-to-escape-sandbox allow-same-origin"

    # La versión en texto plano del correo lleva las URL del servidor desde el que
    # se exportó (p. ej. http://127.0.0.1:8080): se sustituyen por la dirección
    # local que documenta el README, que es la que verá el equipo al ejecutar la app.
    for bloque in sopa.find_all("pre"):
        texto = bloque.get_text()
        if BASE in texto:
            bloque.string = texto.replace(BASE, "http://localhost:8000")

    # --- Recursos estáticos ---------------------------------------
    for etiqueta, attr in (("img", "src"), ("link", "href"), ("script", "src")):
        for el in sopa.find_all(etiqueta):
            valor = el.get(attr)
            if valor and valor.startswith("/assets/"):
                # Sin la huella «?v=…» de CSS y JS: en la copia estática no hace falta.
                el[attr] = valor.lstrip("/").split("?")[0]

    for el in sopa.find_all(attrs={"data-vista": True}):
        if el["data-vista"].startswith("/assets/"):
            el["data-vista"] = el["data-vista"].lstrip("/")

    # --- Formularios ----------------------------------------------
    for form in sopa.find_all("form"):
        accion = (form.get("action") or "").split("?")[0]

        if accion.endswith("/cookies/entendido"):
            # Aviso de cookies: en la copia estática no hay servidor, así que el botón
            # deja de enviar el formulario y kitsune.js se limita a cerrar el aviso.
            form.name = "div"
            del form["action"], form["method"]
            for campo in form.find_all("input", {"type": "hidden"}):
                campo.decompose()
            for boton in form.find_all("button"):
                boton["type"] = "button"
            continue

        destino = FLUJO.get(accion)
        botones = form.find_all("button")

        if destino:
            # El botón principal avanza al paso capturado del recorrido.
            for boton in botones:
                enlace = sopa.new_tag("a", href=destino)
                enlace["class"] = boton.get("class") or []
                # El texto solo para lectores de pantalla no debe pasar a ser visible al
                # convertir el botón en enlace: se descarta y se normalizan los espacios.
                for oculto in boton.select(".solo-lectores"):
                    oculto.decompose()
                enlace.string = " ".join(boton.get_text(" ", strip=True).split()) or "Continuar"
                boton.replace_with(enlace)
            form.name = "div"
            del form["action"], form["method"]
            for campo in form.find_all("input", {"type": "hidden"}):
                campo.decompose()
        else:
            mensaje = {
                "/carrito/actualizar": "En la versión real esto recalcula el carrito en el servidor.",
                "/carrito/eliminar": "En la versión real esto elimina la línea y registra el evento cart.item_removed.",
                "/carrito/cupon": "El cupón KITSUNE10 ya está aplicado en esta cesta de ejemplo.",
            }.get(accion, "Esta acción necesita el servidor PHP del prototipo.")

            if accion.endswith("/estado"):
                mensaje = "El cambio de estado y el evento order.status_changed requieren el servidor PHP."
            elif accion.endswith("/factura"):
                mensaje = ("En la versión real esto emite la factura (si no existe), la deja en el buzón "
                           "de pruebas (y, si el envío real está activado, la envía por SMTP a una dirección "
                           "autorizada) y registra invoice.issued y email.sent.")
            elif accion.startswith("/admin/productos"):
                mensaje = ("En la versión real esto guarda el producto en la base de datos "
                           "y registra el evento product.* correspondiente.")

            for boton in botones:
                boton["data-vp-inerte"] = mensaje
                boton["type"] = "button"
                boton["class"] = (boton.get("class") or []) + ["vp-inerte"]

            for campo in form.find_all(["input", "select", "textarea"]):
                if campo.get("type") != "hidden":
                    campo["data-vp-inerte"] = mensaje

    # --- Aviso, estilos y script ----------------------------------
    if sopa.body:
        aviso = BeautifulSoup(BANNER.format(texto=AVISO), "html.parser")
        sopa.body.insert(0, aviso)
        sopa.body.append(BeautifulSoup(SCRIPT, "html.parser"))

    if sopa.head:
        sopa.head.append(BeautifulSoup(ESTILO, "html.parser"))
        # Sin indexación: es una copia de demostración.
        meta = sopa.new_tag("meta", attrs={"name": "robots", "content": "noindex, nofollow"})
        sopa.head.append(meta)

    return str(sopa)


def guardar(nombre: str, html: str) -> None:
    DESTINO.mkdir(parents=True, exist_ok=True)
    (DESTINO / nombre).write_text(reescribir(html, nombre), encoding="utf-8")
    print(f"  · {nombre}")


# ---------------------------------------------------------------------
# Recorrido
# ---------------------------------------------------------------------

def main() -> None:
    if DESTINO.exists():
        shutil.rmtree(DESTINO)
    DESTINO.mkdir(parents=True)

    tienda = requests.Session()

    print("Páginas públicas:")
    for ruta, fichero in [
        ("/", "index.html"),
        ("/catalogo", "catalogo.html"),
        ("/sobre-kitsune", "sobre-kitsune.html"),
        ("/aviso-academico", "aviso-academico.html"),
        ("/envios", "envios.html"),
        ("/pedidos", "pedidos.html"),
        ("/soporte", "soporte.html"),
        ("/colecciones", "colecciones.html"),
    ]:
        guardar(fichero, tienda.get(BASE + ruta).text)

    for c in CATEGORIAS:
        guardar(f"categoria-{c}.html", tienda.get(f"{BASE}/categoria/{c}").text)
    for c in COLECCIONES:
        guardar(f"coleccion-{c}.html", tienda.get(f"{BASE}/coleccion/{c}").text)
    for p in PRODUCTOS:
        guardar(f"producto-{p}.html", tienda.get(f"{BASE}/producto/{p}").text)

    # --- Recorrido de compra con una cesta de ejemplo --------------
    print("Recorrido de compra:")
    ficha = tienda.get(f"{BASE}/producto/diario-kitsune-kawaii-b6").text
    tienda.post(f"{BASE}/carrito/anadir",
                data={"_token": token(ficha), "producto_id": 3, "cantidad": 1})

    ficha = tienda.get(f"{BASE}/producto/rotuladores-kitsune-pastel-set6").text
    tienda.post(f"{BASE}/carrito/anadir",
                data={"_token": token(ficha), "producto_id": 5, "cantidad": 1})

    ficha = tienda.get(f"{BASE}/producto/washi-tokki-fresa-set5").text
    tienda.post(f"{BASE}/carrito/anadir",
                data={"_token": token(ficha), "producto_id": 7, "cantidad": 2})

    carrito = tienda.get(f"{BASE}/carrito").text
    tienda.post(f"{BASE}/carrito/cupon", data={"_token": token(carrito), "cupon": "KITSUNE10"})

    carrito = tienda.get(f"{BASE}/carrito").text
    guardar("carrito.html", carrito)

    checkout = tienda.get(f"{BASE}/checkout").text
    guardar("checkout.html", checkout)

    tienda.post(f"{BASE}/checkout", data={
        "_token": token(checkout),
        "nombre": "Ana Demo Ruiz", "email": "ana.demo@kitsunenotes.test",
        "telefono": "+34 600 000 001", "direccion": "Calle de Prueba 12, 3.º B",
        "codigo_postal": "30001", "ciudad": "Murcia", "provincia": "Murcia",
        "metodo_envio": "estandar", "notas": "Dejar en portería si no hay nadie.",
        "envoltorio": "1", "condiciones": "1",
    })

    pago = tienda.get(f"{BASE}/pago").text
    guardar("pago.html", pago)

    respuesta = tienda.post(f"{BASE}/pago", data={
        "_token": token(pago), "titular": "Ana Demo Ruiz",
        "numero_tarjeta": "4242 4242 4242 4242", "caducidad": "12/28", "cvv": "123",
    }, allow_redirects=True)

    referencia = re.search(r"KN-\d{4}-\d{6}", respuesta.text)
    referencia = referencia.group(0) if referencia else "KN-2026-000004"
    guardar("pedido.html", respuesta.text)
    print(f"  Pedido de ejemplo: {referencia}")

    # --- Factura del cliente (sesión que hizo la compra) -----------
    factura = tienda.get(f"{BASE}/pedido/{referencia}/factura")
    guardar("factura.html", factura.text)

    # --- Postventa -------------------------------------------------
    soporte = tienda.get(f"{BASE}/soporte?pedido={referencia}").text
    enviado = tienda.post(f"{BASE}/soporte", data={
        "_token": token(soporte), "nombre": "Ana Demo Ruiz",
        "email": "ana.demo@kitsunenotes.test", "tipo": "incidencia_envio",
        "pedido": referencia, "asunto": "Falta un artículo en la caja",
        "mensaje": "He recibido el diario y los rotuladores, pero el washi tape de Tokki no venía "
                   "en el paquete. La caja llegaba abierta por un lateral.",
    }).text
    guardar("soporte-enviado.html", enviado)

    # --- Back-office -----------------------------------------------
    print("Back-office:")
    admin = requests.Session()
    login = admin.get(f"{BASE}/admin/login").text
    guardar("admin-login.html", login)
    admin.post(f"{BASE}/admin/login", data={
        "_token": token(login), "email": "admin@kitsunenotes.test",
        "password": "kitsune-demo-2026",
    })

    # Correo de confirmación del pedido de ejemplo: se localiza en el buzón.
    buzon = admin.get(f"{BASE}/admin/correos").text
    sopa_buzon = BeautifulSoup(buzon, "html.parser")
    id_correo = None
    for enlace in sopa_buzon.find_all("a", href=True):
        coincide = re.search(r"/admin/correos/(\d+)$", enlace["href"])
        if coincide and referencia in enlace.get_text() and "confirmado" in enlace.get_text():
            id_correo = coincide.group(1)
            break
    if id_correo is None:
        raise SystemExit("No se encontró en el buzón el correo de confirmación del pedido de ejemplo.")
    print(f"  Correo de confirmación: #{id_correo}")
    guardar("admin-correo.html", admin.get(f"{BASE}/admin/correos/{id_correo}").text)
    guardar("admin-factura.html", admin.get(f"{BASE}/admin/pedidos/{referencia}/factura").text)

    for ruta, fichero in [
        ("/admin", "admin.html"),
        ("/admin/pedidos", "admin-pedidos.html"),
        (f"/admin/pedidos/{referencia}", "admin-pedido.html"),
        ("/admin/correos", "admin-correos.html"),
        ("/admin/eventos", "admin-eventos.html"),
        ("/admin/incidencias", "admin-incidencias.html"),
        ("/admin/productos", "admin-productos.html"),
        ("/admin/productos/nuevo", "admin-producto-nuevo.html"),
        ("/admin/productos/3/editar", "admin-producto.html"),
    ]:
        guardar(fichero, admin.get(BASE + ruta).text)

    # --- Recursos estáticos ----------------------------------------
    shutil.copytree(RAIZ / "public" / "assets", DESTINO / "assets")
    print(f"\n{len(list(DESTINO.glob('*.html')))} páginas exportadas en {DESTINO}")


if __name__ == "__main__":
    main()
