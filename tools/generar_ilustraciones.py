#!/usr/bin/env python3
"""
Generador de las ilustraciones kawaii de Kitsune Notes.

Todas las imágenes de la tienda son vectores originales creados por este
script: el logotipo, las cuatro mascotas de las colecciones (Kitsune,
Neko, Tokki y Gom), la ilustración de portada y las 12 fichas de
producto. Así la entrega no incorpora fotografías ni personajes de
terceros.

Estilo común: contorno grueso color frambuesa, caritas con mofletes,
fondos pastel con topitos y destellos. Paleta «fresa y nata».

Uso:  python3 tools/generar_ilustraciones.py
"""

import math
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
PRODUCTOS_DIR = RAIZ / "public" / "assets" / "img" / "products"
MASCOTAS_DIR = RAIZ / "public" / "assets" / "img" / "mascotas"
MARCA_DIR = RAIZ / "public" / "assets" / "img" / "brand"

# --- Paleta fresa y nata --------------------------------------------------
FRAMBUESA = "#5A2340"   # contornos y texto
FRESA = "#FF5C8A"
CHICLE = "#FF9EC4"
ALGODON = "#FFD6E7"
NATA = "#FFF5F8"
CREMA = "#FFF8F0"
BLANCO = "#FFFFFF"
MOFLETE = "#FF7AA2"
LIMON = "#FFE680"
HOJA = "#7ED3A8"
ROJO_FRESA = "#FF5C7A"

# Colección -> (principal, claro, sombra)
COLECCIONES = {
    "kitsune": ("#FFA07A", "#FFE8DC", "#F2845A"),
    "neko":    ("#C9A7FF", "#F1E8FF", "#AE88F2"),
    "tokki":   ("#FF8FB8", "#FFE3EE", "#F2709E"),
    "gom":     ("#8EDDC4", "#E0F7EF", "#68C7A8"),
}

SW = 4  # grosor de contorno en los lienzos de 400 × 300


def n(v: float) -> str:
    """Número compacto para las coordenadas."""
    return f"{v:.1f}".rstrip("0").rstrip(".")


def trazo(sw: float = SW) -> str:
    return f'stroke="{FRAMBUESA}" stroke-width="{n(sw)}" stroke-linejoin="round" stroke-linecap="round"'


# -------------------------------------------------------------------------
# Piezas reutilizables
# -------------------------------------------------------------------------

def ojo(x: float, y: float, s: float) -> str:
    return (f'<ellipse cx="{n(x)}" cy="{n(y)}" rx="{n(4.4 * s)}" ry="{n(5.4 * s)}" fill="{FRAMBUESA}"/>'
            f'<circle cx="{n(x - 1.4 * s)}" cy="{n(y - 2 * s)}" r="{n(1.6 * s)}" fill="{BLANCO}"/>')


def ojo_cerrado(x: float, y: float, s: float, feliz: bool = True) -> str:
    """Ojo cerrado: ∩ si está contento, ‿ si duerme."""
    dy = -4 * s if feliz else 4 * s
    return (f'<path d="M{n(x - 5 * s)} {n(y)} Q{n(x)} {n(y + dy * 1.6)} {n(x + 5 * s)} {n(y)}" '
            f'fill="none" {trazo(2.6 * s)}/>')


def cara(cx: float, cy: float, s: float = 1.0, tipo: str = "feliz",
         separacion: float = 13, mofletes: bool = True) -> str:
    """Carita kawaii: ojos, mofletes y boca."""
    dx = separacion * s
    partes = []

    if tipo in ("feliz", "gato", "sorpresa", "oso"):
        partes += [ojo(cx - dx, cy, s), ojo(cx + dx, cy, s)]
    elif tipo == "guino":
        partes += [ojo(cx - dx, cy, s), ojo_cerrado(cx + dx, cy, s)]
    elif tipo == "contento":
        partes += [ojo_cerrado(cx - dx, cy, s), ojo_cerrado(cx + dx, cy, s)]
    elif tipo == "dormido":
        partes += [ojo_cerrado(cx - dx, cy, s, feliz=False), ojo_cerrado(cx + dx, cy, s, feliz=False)]

    if mofletes:
        for signo in (-1, 1):
            partes.append(f'<ellipse cx="{n(cx + signo * (dx + 7 * s))}" cy="{n(cy + 7 * s)}" '
                          f'rx="{n(5.6 * s)}" ry="{n(3.4 * s)}" fill="{MOFLETE}" opacity=".55"/>')

    by = cy + 7 * s
    if tipo in ("gato",):
        partes.append(f'<path d="M{n(cx - 5.5 * s)} {n(by)} Q{n(cx - 2.8 * s)} {n(by + 4.5 * s)} {n(cx)} {n(by)} '
                      f'Q{n(cx + 2.8 * s)} {n(by + 4.5 * s)} {n(cx + 5.5 * s)} {n(by)}" fill="none" {trazo(2.4 * s)}/>')
    elif tipo == "sorpresa":
        partes.append(f'<ellipse cx="{n(cx)}" cy="{n(by + 1.5 * s)}" rx="{n(2.6 * s)}" ry="{n(3.2 * s)}" fill="{FRAMBUESA}"/>')
    elif tipo == "dormido":
        partes.append(f'<ellipse cx="{n(cx)}" cy="{n(by + 1 * s)}" rx="{n(2 * s)}" ry="{n(1.6 * s)}" fill="{FRAMBUESA}"/>')
    elif tipo == "oso":
        pass  # la boca del oso va bajo el hocico, se dibuja aparte
    else:
        partes.append(f'<path d="M{n(cx - 4 * s)} {n(by)} Q{n(cx)} {n(by + 4.6 * s)} {n(cx + 4 * s)} {n(by)}" '
                      f'fill="none" {trazo(2.4 * s)}/>')

    return "".join(partes)


def destello(x: float, y: float, r: float, color: str = BLANCO) -> str:
    """Estrellita de cuatro puntas."""
    return (f'<path d="M{n(x)} {n(y - r)} Q{n(x)} {n(y)} {n(x + r)} {n(y)} Q{n(x)} {n(y)} {n(x)} {n(y + r)} '
            f'Q{n(x)} {n(y)} {n(x - r)} {n(y)} Q{n(x)} {n(y)} {n(x)} {n(y - r)}Z" fill="{color}"/>')


def corazon(x: float, y: float, r: float, color: str = FRESA, contorno: bool = False) -> str:
    puntos = [(0, .9), (-1.2, 0), (-1.1, -.9), (-.5, -.9), (-.2, -.9), (0, -.65), (0, -.45),
              (0, -.65), (.2, -.9), (.5, -.9), (1.1, -.9), (1.2, 0), (0, .9)]
    p = [(x + a * r, y + b * r) for a, b in puntos]
    d = (f"M{n(p[0][0])} {n(p[0][1])} C{n(p[1][0])} {n(p[1][1])} {n(p[2][0])} {n(p[2][1])} {n(p[3][0])} {n(p[3][1])} "
         f"C{n(p[4][0])} {n(p[4][1])} {n(p[5][0])} {n(p[5][1])} {n(p[6][0])} {n(p[6][1])} "
         f"C{n(p[7][0])} {n(p[7][1])} {n(p[8][0])} {n(p[8][1])} {n(p[9][0])} {n(p[9][1])} "
         f"C{n(p[10][0])} {n(p[10][1])} {n(p[11][0])} {n(p[11][1])} {n(p[12][0])} {n(p[12][1])}Z")
    extra = trazo(max(1.6, r * .28)) if contorno else ""
    return f'<path d="{d}" fill="{color}" {extra}/>'


def fresita(x: float, y: float, r: float, contorno: bool = True) -> str:
    """Fresa pequeña con pepitas y hojas."""
    cuerpo = (f'<path d="M{n(x - r)} {n(y - r * .35)} Q{n(x - r)} {n(y + r * .7)} {n(x)} {n(y + r * 1.15)} '
              f'Q{n(x + r)} {n(y + r * .7)} {n(x + r)} {n(y - r * .35)} Q{n(x)} {n(y - r * .75)} {n(x - r)} {n(y - r * .35)}Z" '
              f'fill="{ROJO_FRESA}" {trazo(max(1.8, r * .22)) if contorno else ""}/>')
    pepitas = "".join(
        f'<ellipse cx="{n(x + dx * r)}" cy="{n(y + dy * r)}" rx="{n(r * .07)}" ry="{n(r * .11)}" fill="{CREMA}"/>'
        for dx, dy in ((-.4, .05), (0, .1), (.4, .05), (-.2, .45), (.2, .45), (0, .8)))
    hojas = (f'<path d="M{n(x - r * .7)} {n(y - r * .45)} Q{n(x - r * .3)} {n(y - r * .95)} {n(x)} {n(y - r * .5)} '
             f'Q{n(x + r * .3)} {n(y - r * .95)} {n(x + r * .7)} {n(y - r * .45)} Q{n(x)} {n(y - r * .2)} {n(x - r * .7)} {n(y - r * .45)}Z" '
             f'fill="{HOJA}" {trazo(max(1.6, r * .18)) if contorno else ""}/>')
    return cuerpo + pepitas + hojas


def fondo(clave: str, ancho: int = 400, alto: int = 300) -> str:
    """Fondo pastel con topitos, mancha suave y destellos."""
    principal, claro, _ = COLECCIONES[clave]
    return f"""
  <defs>
    <pattern id="topos" width="26" height="26" patternUnits="userSpaceOnUse">
      <circle cx="6" cy="6" r="2.6" fill="{BLANCO}" opacity=".75"/>
      <circle cx="19" cy="19" r="2.6" fill="{BLANCO}" opacity=".75"/>
    </pattern>
  </defs>
  <rect width="{ancho}" height="{alto}" fill="{claro}"/>
  <rect width="{ancho}" height="{alto}" fill="url(#topos)"/>
  <ellipse cx="{ancho / 2}" cy="{alto / 2 + 10}" rx="{ancho * .36}" ry="{alto * .38}" fill="{principal}" opacity=".22"/>
  {destello(ancho * .12, alto * .2, 11)}{destello(ancho * .88, alto * .18, 8)}{destello(ancho * .9, alto * .78, 12)}
  {destello(ancho * .1, alto * .82, 7)}{corazon(ancho * .83, alto * .42, 7, CHICLE)}{corazon(ancho * .16, alto * .55, 6, CHICLE)}"""


def lienzo(contenido: str, clave: str, titulo: str) -> str:
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="400" height="300" role="img">'
            f'<title>{titulo}</title>{fondo(clave)}\n{contenido}\n</svg>\n')


# -------------------------------------------------------------------------
# Mascotas (cabezas), centradas en (cx, cy) con radio aproximado 55·s
# -------------------------------------------------------------------------

def zorro(cx: float, cy: float, s: float = 1.0, expresion: str = "contento") -> str:
    principal, _, sombra = COLECCIONES["kitsune"]
    sw = SW * s
    orejas = ""
    for sg in (-1, 1):
        orejas += (f'<path d="M{n(cx + sg * 46 * s)} {n(cy - 16 * s)} L{n(cx + sg * 52 * s)} {n(cy - 66 * s)} '
                   f'L{n(cx + sg * 12 * s)} {n(cy - 42 * s)}Z" fill="{principal}" {trazo(sw)}/>'
                   f'<path d="M{n(cx + sg * 42 * s)} {n(cy - 26 * s)} L{n(cx + sg * 46 * s)} {n(cy - 54 * s)} '
                   f'L{n(cx + sg * 24 * s)} {n(cy - 40 * s)}Z" fill="{CHICLE}"/>')
    cabeza_d = (f"M{n(cx - 60 * s)} {n(cy - 4 * s)} C{n(cx - 60 * s)} {n(cy - 46 * s)} {n(cx + 60 * s)} {n(cy - 46 * s)} "
                f"{n(cx + 60 * s)} {n(cy - 4 * s)} C{n(cx + 60 * s)} {n(cy + 30 * s)} {n(cx + 30 * s)} {n(cy + 48 * s)} "
                f"{n(cx)} {n(cy + 48 * s)} C{n(cx - 30 * s)} {n(cy + 48 * s)} {n(cx - 60 * s)} {n(cy + 30 * s)} "
                f"{n(cx - 60 * s)} {n(cy - 4 * s)}Z")
    mascara = (f'<path d="M{n(cx - 52 * s)} {n(cy + 8 * s)} Q{n(cx - 28 * s)} {n(cy - 4 * s)} {n(cx)} {n(cy + 14 * s)} '
               f'Q{n(cx + 28 * s)} {n(cy - 4 * s)} {n(cx + 52 * s)} {n(cy + 8 * s)} Q{n(cx + 44 * s)} {n(cy + 44 * s)} '
               f'{n(cx)} {n(cy + 47 * s)} Q{n(cx - 44 * s)} {n(cy + 44 * s)} {n(cx - 52 * s)} {n(cy + 8 * s)}Z" fill="{CREMA}"/>')
    return (orejas
            + f'<path d="{cabeza_d}" fill="{principal}"/>'
            + mascara
            + f'<path d="{cabeza_d}" fill="none" {trazo(sw)}/>'
            + corazon(cx, cy - 24 * s, 6 * s, FRESA)
            + cara(cx, cy + 2 * s, s * 1.15, expresion, separacion=18)
            + f'<ellipse cx="{n(cx)}" cy="{n(cy + 14 * s)}" rx="{n(4.2 * s)}" ry="{n(3 * s)}" fill="{FRAMBUESA}"/>')


def gato(cx: float, cy: float, s: float = 1.0, expresion: str = "gato") -> str:
    principal, _, sombra = COLECCIONES["neko"]
    sw = SW * s
    partes = ""
    for sg in (-1, 1):
        partes += (f'<path d="M{n(cx + sg * 48 * s)} {n(cy - 12 * s)} L{n(cx + sg * 42 * s)} {n(cy - 64 * s)} '
                   f'L{n(cx + sg * 8 * s)} {n(cy - 40 * s)}Z" fill="{principal}" {trazo(sw)}/>'
                   f'<path d="M{n(cx + sg * 40 * s)} {n(cy - 24 * s)} L{n(cx + sg * 38 * s)} {n(cy - 52 * s)} '
                   f'L{n(cx + sg * 20 * s)} {n(cy - 38 * s)}Z" fill="{CHICLE}"/>')
    partes += f'<ellipse cx="{n(cx)}" cy="{n(cy)}" rx="{n(58 * s)}" ry="{n(47 * s)}" fill="{principal}" {trazo(sw)}/>'
    # luna creciente en la frente
    partes += (f'<path d="M{n(cx - 4 * s)} {n(cy - 36 * s)} A{n(9 * s)} {n(9 * s)} 0 1 0 {n(cx + 6 * s)} {n(cy - 20 * s)} '
               f'A{n(7 * s)} {n(7 * s)} 0 1 1 {n(cx - 4 * s)} {n(cy - 36 * s)}Z" fill="{LIMON}"/>')
    for sg in (-1, 1):
        partes += (f'<path d="M{n(cx + sg * 42 * s)} {n(cy + 10 * s)} L{n(cx + sg * 64 * s)} {n(cy + 5 * s)}" {trazo(2.6 * s)}/>'
                   f'<path d="M{n(cx + sg * 42 * s)} {n(cy + 17 * s)} L{n(cx + sg * 63 * s)} {n(cy + 20 * s)}" {trazo(2.6 * s)}/>')
    partes += cara(cx, cy + 4 * s, s * 1.15, expresion, separacion=18)
    partes += f'<path d="M{n(cx - 3 * s)} {n(cy + 9 * s)} L{n(cx + 3 * s)} {n(cy + 9 * s)} L{n(cx)} {n(cy + 12 * s)}Z" fill="{FRESA}"/>'
    return partes


def conejo(cx: float, cy: float, s: float = 1.0, expresion: str = "feliz") -> str:
    principal, _, sombra = COLECCIONES["tokki"]
    sw = SW * s
    partes = (
        # oreja erguida
        f'<ellipse cx="{n(cx - 20 * s)}" cy="{n(cy - 66 * s)}" rx="{n(14 * s)}" ry="{n(38 * s)}" '
        f'transform="rotate(-8 {n(cx - 20 * s)} {n(cy - 66 * s)})" fill="{principal}" {trazo(sw)}/>'
        f'<ellipse cx="{n(cx - 20 * s)}" cy="{n(cy - 64 * s)}" rx="{n(6.5 * s)}" ry="{n(26 * s)}" '
        f'transform="rotate(-8 {n(cx - 20 * s)} {n(cy - 64 * s)})" fill="{ALGODON}"/>'
        # oreja caída
        f'<ellipse cx="{n(cx + 44 * s)}" cy="{n(cy - 48 * s)}" rx="{n(13 * s)}" ry="{n(35 * s)}" '
        f'transform="rotate(58 {n(cx + 44 * s)} {n(cy - 48 * s)})" fill="{principal}" {trazo(sw)}/>'
        f'<ellipse cx="{n(cx + 46 * s)}" cy="{n(cy - 49 * s)}" rx="{n(6 * s)}" ry="{n(24 * s)}" '
        f'transform="rotate(58 {n(cx + 46 * s)} {n(cy - 49 * s)})" fill="{ALGODON}"/>'
        f'<ellipse cx="{n(cx)}" cy="{n(cy)}" rx="{n(56 * s)}" ry="{n(46 * s)}" fill="{principal}" {trazo(sw)}/>'
    )
    partes += fresita(cx + 20 * s, cy - 38 * s, 9 * s)
    partes += cara(cx, cy + 2 * s, s * 1.15, expresion, separacion=18)
    partes += (f'<path d="M{n(cx - 3 * s)} {n(cy + 9 * s)} Q{n(cx)} {n(cy + 12 * s)} {n(cx + 3 * s)} {n(cy + 9 * s)}" '
               f'fill="none" {trazo(2.2 * s)}/>')
    return partes


def oso(cx: float, cy: float, s: float = 1.0, expresion: str = "oso") -> str:
    principal, claro, sombra = COLECCIONES["gom"]
    sw = SW * s
    partes = ""
    for sg in (-1, 1):
        partes += (f'<circle cx="{n(cx + sg * 42 * s)}" cy="{n(cy - 36 * s)}" r="{n(19 * s)}" fill="{principal}" {trazo(sw)}/>'
                   f'<circle cx="{n(cx + sg * 42 * s)}" cy="{n(cy - 36 * s)}" r="{n(9 * s)}" fill="{CHICLE}"/>')
    partes += f'<ellipse cx="{n(cx)}" cy="{n(cy)}" rx="{n(58 * s)}" ry="{n(48 * s)}" fill="{principal}" {trazo(sw)}/>'
    partes += f'<ellipse cx="{n(cx)}" cy="{n(cy + 18 * s)}" rx="{n(22 * s)}" ry="{n(15 * s)}" fill="{CREMA}" {trazo(2.6 * s)}/>'
    partes += corazon(cx, cy + 12 * s, 5.2 * s, FRESA)
    partes += (f'<path d="M{n(cx)} {n(cy + 17 * s)} L{n(cx)} {n(cy + 22 * s)} M{n(cx - 5 * s)} {n(cy + 24 * s)} '
               f'Q{n(cx)} {n(cy + 28 * s)} {n(cx + 5 * s)} {n(cy + 24 * s)}" fill="none" {trazo(2.2 * s)}/>')
    partes += cara(cx, cy - 4 * s, s * 1.15, expresion, separacion=20)
    return partes


MASCOTAS = {"kitsune": zorro, "neko": gato, "tokki": conejo, "gom": oso}


# -------------------------------------------------------------------------
# Productos
# -------------------------------------------------------------------------

def cuaderno_neko() -> str:
    p, claro, sombra = COLECCIONES["neko"]
    partes = [
        # orejitas troqueladas
        f'<path d="M150 70 L162 34 L186 62Z" fill="{p}" {trazo()}/>',
        f'<path d="M250 70 L238 34 L214 62Z" fill="{p}" {trazo()}/>',
        f'<path d="M158 64 L164 44 L178 60Z" fill="{CHICLE}"/>',
        f'<path d="M242 64 L236 44 L222 60Z" fill="{CHICLE}"/>',
        # páginas y tapa
        f'<rect x="130" y="66" width="150" height="192" rx="16" fill="{BLANCO}" {trazo()}/>',
        f'<rect x="122" y="60" width="150" height="192" rx="16" fill="{p}" {trazo()}/>',
        f'<rect x="122" y="60" width="26" height="192" rx="12" fill="{sombra}" {trazo()}/>',
        destello(236, 92, 9, LIMON), destello(170, 222, 7, BLANCO), destello(248, 216, 5, BLANCO),
        f'<path d="M178 92 A10 10 0 1 0 190 108 A8 8 0 1 1 178 92Z" fill="{LIMON}"/>',
        cara(206, 158, 1.7, "gato", separacion=14),
    ]
    for sg in (-1, 1):
        partes.append(f'<path d="M{206 + sg * 36} {172} L{206 + sg * 56} {168}" {trazo(3)}/>')
        partes.append(f'<path d="M{206 + sg * 36} {180} L{206 + sg * 55} {184}" {trazo(3)}/>')
    return "".join(partes)


def libreta_tokki() -> str:
    p, claro, sombra = COLECCIONES["tokki"]
    return "".join([
        f'<ellipse cx="174" cy="72" rx="15" ry="38" transform="rotate(-10 174 72)" fill="{p}" {trazo()}/>',
        f'<ellipse cx="174" cy="74" rx="7" ry="26" transform="rotate(-10 174 74)" fill="{ALGODON}"/>',
        f'<ellipse cx="246" cy="86" rx="14" ry="36" transform="rotate(60 246 86)" fill="{p}" {trazo()}/>',
        f'<ellipse cx="248" cy="86" rx="6.5" ry="24" transform="rotate(60 248 86)" fill="{ALGODON}"/>',
        f'<rect x="132" y="96" width="140" height="162" rx="34" fill="{BLANCO}" {trazo()}/>',
        f'<rect x="126" y="90" width="140" height="162" rx="34" fill="{p}" {trazo()}/>',
        f'<path d="M142 104 Q196 94 250 104" fill="none" stroke="{BLANCO}" stroke-width="5" stroke-linecap="round" opacity=".6"/>',
        cara(196, 168, 1.8, "feliz", separacion=15),
        f'<path d="M193 180 Q196 184 199 180" fill="none" {trazo(3)}/>',
        fresita(152, 226, 13), fresita(240, 124, 11), fresita(242, 228, 9),
    ])


def diario_kitsune() -> str:
    p, claro, sombra = COLECCIONES["kitsune"]
    return "".join([
        f'<rect x="196" y="244" width="10" height="34" rx="3" fill="{FRESA}" {trazo(3)}/>',
        f'<rect x="212" y="244" width="10" height="26" rx="3" fill="{CREMA}" {trazo(3)}/>',
        f'<rect x="126" y="42" width="164" height="210" rx="18" fill="{BLANCO}" {trazo()}/>',
        f'<rect x="116" y="36" width="164" height="210" rx="18" fill="{p}" {trazo()}/>',
        f'<rect x="116" y="36" width="22" height="210" rx="11" fill="{sombra}" {trazo()}/>',
        f'<rect x="256" y="36" width="10" height="210" fill="{FRESA}" {trazo(3)}/>',
        zorro(198, 150, .82, "contento"),
        corazon(160, 214, 7, CREMA), corazon(236, 70, 6, CREMA),
    ])


def boligrafos_gom() -> str:
    colores = ["#8EDDC4", "#C99A7A", "#FF8FB8"]
    partes = []
    for i, color in enumerate(colores):
        x = 136 + i * 64
        partes += [
            f'<path d="M{x} 118 L{x + 24} 118 L{x + 24} 234 L{x + 12} 262 L{x} 234Z" fill="{color}" {trazo()}/>',
            f'<path d="M{x + 5} 238 L{x + 19} 238 L{x + 12} 256Z" fill="{CREMA}"/>',
            f'<rect x="{x + 4}" y="136" width="5" height="44" rx="2.5" fill="{BLANCO}" opacity=".7"/>',
        ]
        # osito sentado en el capuchón
        partes.append(oso(x + 12, 96, .42))
    return "".join(partes)


def rotuladores_kitsune() -> str:
    colores = ["#FFA07A", "#FF7AA2", "#C9A7FF", "#8EDDC4", "#FFE680", "#9FD8FF"]
    caras = ["contento", "feliz", "guino", "dormido", "sorpresa", "feliz"]
    partes = []
    for i, (color, tipo) in enumerate(zip(colores, caras)):
        x = 92 + i * 38
        partes += [
            f'<rect x="{x}" y="112" width="30" height="120" rx="10" fill="{BLANCO}" {trazo()}/>',
            f'<rect x="{x}" y="70" width="30" height="62" rx="12" fill="{color}" {trazo()}/>',
            f'<path d="M{x + 6} 232 L{x + 24} 232 L{x + 15} 262Z" fill="{color}" {trazo(3)}/>',
            f'<rect x="{x + 4}" y="190" width="22" height="10" rx="5" fill="{color}" opacity=".7"/>',
            cara(x + 15, 98, .72, tipo, separacion=9),
        ]
    return "".join(partes)


def pluma_neko() -> str:
    p, claro, sombra = COLECCIONES["neko"]
    brillos = "".join(
        f'<circle cx="{x}" cy="{y}" r="2" fill="{BLANCO}" opacity=".85"/>'
        for x, y in ((190, 110), (212, 150), (196, 176), (220, 196), (204, 214), (186, 146)))
    return "".join([
        '<g transform="rotate(-24 200 150)">',
        f'<path d="M178 232 L222 232 L200 286Z" fill="{LIMON}" {trazo()}/>',
        f'<path d="M200 252 L200 280" {trazo(3)}/><circle cx="200" cy="250" r="4" fill="{FRAMBUESA}"/>',
        f'<rect x="170" y="126" width="60" height="112" rx="26" fill="{p}" {trazo()}/>',
        f'<rect x="166" y="22" width="68" height="112" rx="30" fill="{sombra}" {trazo()}/>',
        f'<rect x="166" y="118" width="68" height="12" fill="{LIMON}" {trazo(3)}/>',
        brillos,
        cara(200, 176, 1.2, "gato", separacion=12),
        # clip con forma de patita
        f'<rect x="236" y="34" width="12" height="72" rx="6" fill="{CHICLE}" {trazo(3)}/>',
        f'<circle cx="242" cy="112" r="15" fill="{CHICLE}" {trazo(3)}/>',
        f'<ellipse cx="242" cy="116" rx="6.5" ry="5" fill="{FRESA}"/>',
        f'<circle cx="235" cy="106" r="2.8" fill="{FRESA}"/><circle cx="242" cy="103" r="2.8" fill="{FRESA}"/>'
        f'<circle cx="249" cy="106" r="2.8" fill="{FRESA}"/>',
        '</g>',
    ])


def rollo(cx: float, cy: float, r: float, color: str, motivo: str, cara_tipo: str | None = None) -> str:
    """Rollo de washi tape visto de frente, con estampado y carita opcional."""
    partes = [f'<circle cx="{n(cx)}" cy="{n(cy)}" r="{n(r)}" fill="{color}" {trazo()}/>']

    def en_anillo(paso: int, desde: int = 0):
        for ang in range(desde, desde + 360, paso):
            yield (cx + math.cos(math.radians(ang)) * r * .72,
                   cy + math.sin(math.radians(ang)) * r * .72)

    if motivo == "fresas":
        partes += [fresita(x, y - 1, r * .13, contorno=False) for x, y in en_anillo(60)]
    elif motivo == "corazones":
        partes += [corazon(x, y, r * .12, BLANCO) for x, y in en_anillo(72, 30)]
    elif motivo == "topos":
        partes += [f'<circle cx="{n(x)}" cy="{n(y)}" r="{n(r * .07)}" fill="{BLANCO}" opacity=".9"/>'
                   for x, y in en_anillo(45)]
    elif motivo == "vichy":
        clip = f"c{int(cx)}{int(cy)}"
        partes.insert(0, f'<clipPath id="{clip}"><circle cx="{n(cx)}" cy="{n(cy)}" r="{n(r)}"/></clipPath>')
        for k in range(-3, 4):
            partes.append(f'<rect x="{n(cx + k * r * .28 - r * .07)}" y="{n(cy - r)}" width="{n(r * .14)}" '
                          f'height="{n(2 * r)}" fill="{BLANCO}" opacity=".35" clip-path="url(#{clip})"/>')
            partes.append(f'<rect x="{n(cx - r)}" y="{n(cy + k * r * .28 - r * .07)}" width="{n(2 * r)}" '
                          f'height="{n(r * .14)}" fill="{BLANCO}" opacity=".25" clip-path="url(#{clip})"/>')

    partes.append(f'<circle cx="{n(cx)}" cy="{n(cy)}" r="{n(r * .46)}" fill="{CREMA}" {trazo(3)}/>')
    if cara_tipo:
        partes.append(cara(cx, cy - r * .05, r * .018, cara_tipo, separacion=10))
    return "".join(partes)


def washi_tokki() -> str:
    return "".join([
        f'<path d="M258 200 Q300 212 336 196 L344 222 Q304 238 262 226Z" fill="{CHICLE}" {trazo()}/>',
        fresita(300, 214, 7, contorno=False),
        rollo(134, 104, 46, "#FF8FB8", "fresas"),
        rollo(240, 92, 40, "#FFB3CF", "corazones"),
        rollo(196, 196, 58, "#FF9EC4", "topos", cara_tipo="feliz"),
        rollo(300, 176, 34, "#FFD6E7", "fresas"),
        rollo(98, 206, 34, "#F2709E", "corazones"),
    ])


def washi_gom() -> str:
    return "".join([
        rollo(128, 110, 48, "#8EDDC4", "vichy"),
        rollo(272, 110, 48, "#FFF1C9", "topos"),
        rollo(200, 196, 60, "#68C7A8", "vichy", cara_tipo="contento"),
        corazon(200, 58, 10, FRESA, contorno=True),
    ])


def pegatinas_kitsune() -> str:
    p, claro, sombra = COLECCIONES["kitsune"]
    partes = [
        f'<rect x="98" y="48" width="212" height="212" rx="22" fill="{sombra}" opacity=".35"/>',
        f'<rect x="90" y="40" width="212" height="212" rx="22" fill="{BLANCO}" {trazo()}/>',
        f'<rect x="102" y="52" width="188" height="188" rx="14" fill="none" stroke="{CHICLE}" stroke-width="2.5" stroke-dasharray="7 6"/>',
    ]
    caras = [("contento", 140, 96), ("guino", 196, 96), ("sorpresa", 252, 96),
             ("dormido", 140, 196), ("feliz", 252, 196)]
    for tipo, x, y in caras:
        partes.append(zorro(x, y, .36, tipo))
    partes += [corazon(196, 150, 16, FRESA, contorno=True), destello(196, 198, 16, LIMON),
               destello(160, 148, 7, CHICLE), destello(232, 150, 7, CHICLE)]
    return "".join(partes)


def planner_neko() -> str:
    p, claro, sombra = COLECCIONES["neko"]
    partes = [
        f'<rect x="104" y="92" width="200" height="164" rx="18" fill="{BLANCO}" {trazo()}/>',
        f'<rect x="96" y="86" width="200" height="164" rx="18" fill="{p}" {trazo()}/>',
        f'<text x="196" y="226" font-family="Verdana, sans-serif" font-size="30" font-weight="bold" '
        f'fill="{BLANCO}" stroke="{FRAMBUESA}" stroke-width="2" text-anchor="middle">2026</text>',
    ]
    for i in range(8):
        x = 116 + i * 23
        partes.append(f'<rect x="{x}" y="74" width="9" height="26" rx="4.5" fill="{CREMA}" {trazo(3)}/>')
    partes += [
        destello(128, 200, 8, LIMON), destello(268, 206, 6, BLANCO),
        # gatito durmiendo encima
        f'<path d="M150 90 Q148 50 196 48 Q246 50 244 90Z" fill="{CREMA}" {trazo()}/>',
        f'<path d="M156 58 L160 32 L178 50Z" fill="{CREMA}" {trazo()}/>',
        f'<path d="M234 58 L230 32 L212 50Z" fill="{CREMA}" {trazo()}/>',
        f'<path d="M240 80 Q272 84 266 58" fill="none" {trazo(8)}/><path d="M240 80 Q272 84 266 58" fill="none" stroke="{CREMA}" stroke-width="4" stroke-linecap="round"/>',
        cara(196, 68, 1.1, "dormido", separacion=13),
        f'<text x="262" y="40" font-family="Verdana, sans-serif" font-size="18" font-weight="bold" fill="{FRAMBUESA}">z</text>',
        f'<text x="276" y="26" font-family="Verdana, sans-serif" font-size="13" font-weight="bold" fill="{FRAMBUESA}">z</text>',
    ]
    return "".join(partes)


def organizador_gom() -> str:
    p, claro, sombra = COLECCIONES["gom"]
    return "".join([
        # bolis asomando por detrás
        f'<rect x="128" y="64" width="16" height="90" rx="8" fill="{FRESA}" {trazo(3)}/>',
        f'<rect x="150" y="50" width="16" height="104" rx="8" fill="{LIMON}" {trazo(3)}/>',
        f'<rect x="258" y="70" width="16" height="84" rx="8" fill="#C9A7FF" {trazo(3)}/>',
        # tejado con orejitas
        f'<circle cx="156" cy="96" r="16" fill="{sombra}" {trazo()}/><circle cx="244" cy="96" r="16" fill="{sombra}" {trazo()}/>',
        f'<circle cx="156" cy="96" r="7" fill="{CHICLE}"/><circle cx="244" cy="96" r="7" fill="{CHICLE}"/>',
        f'<path d="M110 150 L200 84 L290 150Z" fill="{sombra}" {trazo()}/>',
        # casita
        f'<rect x="120" y="146" width="160" height="112" rx="12" fill="{p}" {trazo()}/>',
        f'<circle cx="200" cy="184" r="28" fill="{CREMA}" {trazo()}/>',
        oso(200, 188, .36),
        f'<rect x="138" y="222" width="124" height="28" rx="8" fill="{claro}" {trazo(3)}/>',
        corazon(200, 236, 6, FRESA),
        f'<rect x="134" y="160" width="22" height="30" rx="6" fill="{claro}" {trazo(3)}/>',
        f'<rect x="244" y="160" width="22" height="30" rx="6" fill="{claro}" {trazo(3)}/>',
    ])


def archivador_tokki() -> str:
    p, claro, sombra = COLECCIONES["tokki"]
    fuelles = "".join(
        f'<path d="M{104 + i * 32} 104 L{120 + i * 32} 78 L{136 + i * 32} 104Z" fill="{ALGODON}" {trazo(3)}/>'
        for i in range(6))
    nube = ("M150 196 Q140 170 166 166 Q172 144 198 150 Q214 134 234 152 Q260 150 256 176 "
            "Q274 190 256 206 L160 206 Q140 206 150 196Z")
    return "".join([
        fuelles,
        f'<path d="M96 104 L304 104 L304 244 Q304 258 290 258 L110 258 Q96 258 96 244Z" fill="{p}" {trazo()}/>',
        f'<path d="{nube}" fill="{BLANCO}" {trazo()}/>',
        # conejito dormido sobre la nube
        f'<ellipse cx="192" cy="136" rx="7" ry="20" transform="rotate(-20 192 136)" fill="{CREMA}" {trazo(3)}/>',
        f'<ellipse cx="212" cy="138" rx="7" ry="18" transform="rotate(35 212 138)" fill="{CREMA}" {trazo(3)}/>',
        f'<ellipse cx="202" cy="166" rx="30" ry="22" fill="{CREMA}" {trazo()}/>',
        cara(202, 166, .95, "dormido", separacion=11),
        f'<path d="M300 150 Q326 150 326 176" fill="none" {trazo(3)}/>',
        f'<circle cx="326" cy="186" r="11" fill="{FRESA}" {trazo(3)}/>',
        destello(124, 226, 8, BLANCO), destello(280, 228, 6, LIMON),
    ])


PRODUCTOS = {
    "cuaderno-neko-nyan-a5":          ("neko",    cuaderno_neko,       "Cuaderno Neko Nyan A5"),
    "libreta-tokki-mochi-a6":         ("tokki",   libreta_tokki,       "Libreta Tokki Mochi A6"),
    "diario-kitsune-kawaii-b6":       ("kitsune", diario_kitsune,      "Diario Kitsune Kawaii B6"),
    "boligrafos-gom-gom-038-pack3":   ("gom",     boligrafos_gom,      "Bolígrafos gel Gom Gom"),
    "rotuladores-kitsune-pastel-set6": ("kitsune", rotuladores_kitsune, "Rotuladores Kitsune Pastel"),
    "pluma-neko-patita-f":            ("neko",    pluma_neko,          "Pluma Neko Patita"),
    "washi-tokki-fresa-set5":         ("tokki",   washi_tokki,         "Washi tape Tokki Fresa"),
    "washi-gom-picnic-set3":          ("gom",     washi_gom,           "Washi tape Gom Picnic"),
    "pegatinas-kitsune-mood-120":     ("kitsune", pegatinas_kitsune,   "Pegatinas Kitsune Mood"),
    "planner-neko-siesta-2026":       ("neko",    planner_neko,        "Planner Neko Siesta 2026"),
    "organizador-gom-casita":         ("gom",     organizador_gom,     "Organizador Gom Casita"),
    "archivador-tokki-nube-a4":       ("tokki",   archivador_tokki,    "Archivador Tokki Nube A4"),
}


# -------------------------------------------------------------------------
# Marca, mascotas sueltas y portada
# -------------------------------------------------------------------------

def mascota_suelta(clave: str) -> str:
    """Retrato de la mascota: tarjetas de colección e imagen genérica de producto."""
    principal, claro, _ = COLECCIONES[clave]
    dibujo = MASCOTAS[clave](200, 168, 1.35)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="400" height="300" role="img">'
            f'<title>Mascota {clave}</title>{fondo(clave)}\n{dibujo}\n</svg>\n')


def logo() -> str:
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 140 132" width="140" height="132" role="img">'
            f'<title>Kitsune Notes</title>{zorro(70, 74, .9, "contento")}</svg>\n')


def portada() -> str:
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 380" width="520" height="380" role="img">
  <title>Kitsune, Neko, Tokki y Gom</title>
  <defs>
    <pattern id="topos" width="26" height="26" patternUnits="userSpaceOnUse">
      <circle cx="6" cy="6" r="2.6" fill="{CHICLE}" opacity=".35"/>
      <circle cx="19" cy="19" r="2.6" fill="{CHICLE}" opacity=".35"/>
    </pattern>
  </defs>
  <rect width="520" height="380" rx="36" fill="{BLANCO}"/>
  <rect width="520" height="380" rx="36" fill="url(#topos)"/>
  <circle cx="140" cy="130" r="92" fill="{COLECCIONES['kitsune'][1]}"/>
  <circle cx="380" cy="120" r="84" fill="{COLECCIONES['neko'][1]}"/>
  <circle cx="150" cy="282" r="82" fill="{COLECCIONES['gom'][1]}"/>
  <circle cx="378" cy="278" r="88" fill="{COLECCIONES['tokki'][1]}"/>
  {zorro(140, 138, 1.05, "contento")}
  {gato(380, 128, .95, "gato")}
  {oso(152, 290, .92)}
  {conejo(378, 292, .95, "feliz")}
  {corazon(262, 196, 20, FRESA, contorno=True)}
  {destello(262, 68, 16, LIMON)}{destello(470, 214, 12, CHICLE)}{destello(40, 212, 12, CHICLE)}
  {destello(264, 330, 11, LIMON)}{corazon(478, 44, 9, CHICLE)}{corazon(46, 46, 8, CHICLE)}
</svg>
"""


def main() -> None:
    for carpeta in (PRODUCTOS_DIR, MASCOTAS_DIR, MARCA_DIR):
        carpeta.mkdir(parents=True, exist_ok=True)

    for viejo in PRODUCTOS_DIR.glob("*.svg"):
        viejo.unlink()

    for slug, (clave, dibujo, titulo) in PRODUCTOS.items():
        (PRODUCTOS_DIR / f"{slug}.svg").write_text(lienzo(dibujo(), clave, titulo), encoding="utf-8")
        print(f"· productos/{slug}.svg")

    for clave in COLECCIONES:
        (MASCOTAS_DIR / f"{clave}.svg").write_text(mascota_suelta(clave), encoding="utf-8")
        print(f"· mascotas/{clave}.svg")

    (MARCA_DIR / "kitsune.svg").write_text(logo(), encoding="utf-8")
    (MARCA_DIR / "portada.svg").write_text(portada(), encoding="utf-8")
    viejo = MARCA_DIR / "escritorio.svg"
    if viejo.exists():
        viejo.unlink()
    print("· brand/kitsune.svg, brand/portada.svg")


if __name__ == "__main__":
    main()
