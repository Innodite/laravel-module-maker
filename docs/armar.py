#!/usr/bin/env python3
"""Arma `manual.html` (en la RAÍZ del repositorio) a partir de `docs/fichas.json` y las fichas de
`docs/es/`.

La fuente única del manual son los `.md`. Este script solo los monta en una página con la interfaz
de la documentación de la casa; ⛔ no se edita `manual.html` a mano, se vuelve a ejecutar esto:

    python3 docs/armar.py

⚠️ El conversor de Markdown de aquí es DELIBERADAMENTE pequeño: cubre lo que las fichas usan
—encabezados, tablas, listas, bloques de código, citas, negrita, código en línea y enlaces— y nada
más. No es una biblioteca de propósito general, y si una ficha necesita algo que no está, se añade
aquí antes que escribirlo en HTML dentro del `.md`.

⭐ Una excepción declarada a propósito: un párrafo o una cita que empieza con ⛔/⚠️/⭐/✅ se reconoce
como una NOTA y se envuelve en su propia caja con color — no es sintaxis nueva, es leer mejor una
convención que las fichas ya usaban en texto plano.
"""

from __future__ import annotations

import html
import json
import re
import unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent
# manual.html sale en la RAÍZ del repositorio, no en docs/ — así el hosting lo sirve directo,
# sin ninguna subcarpeta. Las fuentes (fichas.json, es/*.md) sí se leen desde docs/.
SALIDA = RAIZ.parent

# Enlace entre fichas escrito como `archivo.md` — correcto para leerlo en GitHub, donde el
# navegador de repositorio SÍ resuelve `.md` relativos. `en_linea()` lo reescribe a `#/slug` para
# el sitio generado, que enruta por hash, no por archivo. Se llena una vez en main().
MAPA_ENLACES: dict[str, str] = {}


def slug_de(texto: str) -> str:
    """id de ancla para un encabezado: sin acentos, sin signos, en minúsculas."""
    plano = unicodedata.normalize('NFKD', texto).encode('ascii', 'ignore').decode('ascii')
    plano = re.sub(r'[^a-zA-Z0-9]+', '-', plano).strip('-').lower()
    return plano or 'seccion'


MARCA_NOTA = {'⛔': 'peligro', '❌': 'peligro', '⚠️': 'aviso', '⭐': 'clave', '✅': 'ok'}


def clase_de_nota(texto: str) -> str | None:
    """Si el texto empieza con una de las marcas de nota, la clase de caja que le toca."""
    inicio = texto.strip()
    for marca, clase in MARCA_NOTA.items():
        if inicio.startswith(marca):
            return clase
    return None


# ─────────────────────────────────────────────────────────────────────────────
# Markdown → HTML, lo justo
# ─────────────────────────────────────────────────────────────────────────────

def en_linea(texto: str) -> str:
    """Negrita, código en línea y enlaces. El escapado va primero, siempre."""
    salida = html.escape(texto, quote=False)
    salida = re.sub(r'`([^`]+)`', r'<code>\1</code>', salida)
    salida = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', salida)
    # El tachado marca lo retirado, y sin esto salían las virgulillas literales en la tabla.
    salida = re.sub(r'~~([^~]+)~~', r'<del>\1</del>', salida)

    def enlace(m: re.Match[str]) -> str:
        etiqueta, destino = m.group(1), m.group(2)
        if destino in MAPA_ENLACES:
            destino = f'#/{MAPA_ENLACES[destino]}'
        return f'<a href="{destino}">{etiqueta}</a>'

    salida = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', enlace, salida)
    return salida


def fila_de_tabla(linea: str) -> list[str]:
    # `\|` dentro de una celda (para listar valores tipo `a` \| `b`) no debe cortar la columna.
    marcador = '\x00'
    protegida = linea.strip().strip('|').replace('\\|', marcador)
    return [c.strip().replace(marcador, '|') for c in protegida.split('|')]


def a_html(md: str) -> str:
    lineas = md.split('\n')
    fuera: list[str] = []
    usados_h2: set[str] = set()
    i = 0

    while i < len(lineas):
        linea = lineas[i]

        # Bloque de código
        if linea.startswith('```'):
            lenguaje = linea[3:].strip()
            cuerpo: list[str] = []
            i += 1
            while i < len(lineas) and not lineas[i].startswith('```'):
                cuerpo.append(lineas[i])
                i += 1
            i += 1
            clase = f' class="lang-{lenguaje}"' if lenguaje else ''
            etiqueta = f'<span class="doc-pre__lang">{html.escape(lenguaje)}</span>' if lenguaje else ''
            fuera.append(
                f'<div class="doc-pre">{etiqueta}<pre><code{clase}>'
                f'{html.escape(chr(10).join(cuerpo))}</code></pre></div>'
            )
            continue

        # Tabla: una cabecera, un separador de guiones y las filas
        if linea.strip().startswith('|') and i + 1 < len(lineas) and re.match(r'^\s*\|[\s:|-]+\|\s*$', lineas[i + 1]):
            cabecera = fila_de_tabla(linea)
            i += 2
            filas: list[list[str]] = []
            while i < len(lineas) and lineas[i].strip().startswith('|'):
                filas.append(fila_de_tabla(lineas[i]))
                i += 1
            th = ''.join(f'<th>{en_linea(c)}</th>' for c in cabecera)
            cuerpo_tabla = ''.join(
                '<tr>' + ''.join(f'<td>{en_linea(c)}</td>' for c in f) + '</tr>' for f in filas
            )
            # El envoltorio permite que una tabla ancha ruede sola, sin arrastrar la página.
            fuera.append(
                f'<div class="doc-tabla"><table><thead><tr>{th}</tr></thead>'
                f'<tbody>{cuerpo_tabla}</tbody></table></div>'
            )
            continue

        # Encabezados
        if linea.startswith('### '):
            fuera.append(f'<h3>{en_linea(linea[4:])}</h3>')
            i += 1
            continue
        if linea.startswith('## '):
            texto_h2 = linea[3:]
            base = slug_de(texto_h2)
            ident = base
            contador = 2
            while ident in usados_h2:
                ident = f'{base}-{contador}'
                contador += 1
            usados_h2.add(ident)
            fuera.append(f'<h2 id="{ident}">{en_linea(texto_h2)}</h2>')
            i += 1
            continue
        if linea.startswith('# '):
            fuera.append(f'<h2>{en_linea(linea[2:])}</h2>')
            i += 1
            continue

        # Cita
        if linea.startswith('> '):
            cuerpo = []
            while i < len(lineas) and lineas[i].startswith('>'):
                cuerpo.append(lineas[i].lstrip('>').strip())
                i += 1
            texto_cita = ' '.join(cuerpo)
            nota = clase_de_nota(texto_cita)
            clase_nota = f' doc-nota--{nota}' if nota else ''
            fuera.append(f'<blockquote class="doc-nota{clase_nota}">{en_linea(texto_cita)}</blockquote>')
            continue

        # Lista
        if re.match(r'^\s*[-*] ', linea):
            elementos = []
            while i < len(lineas) and re.match(r'^\s*[-*] ', lineas[i]):
                elementos.append(f'<li>{en_linea(re.sub(r"^\s*[-*] ", "", lineas[i]))}</li>')
                i += 1
            fuera.append('<ul class="doc-lista">' + ''.join(elementos) + '</ul>')
            continue

        # Separador
        if linea.strip() == '---':
            fuera.append('<hr>')
            i += 1
            continue

        # Párrafo: se junta hasta la línea en blanco, para respetar los saltos de los .md
        if linea.strip():
            parrafo = []
            while i < len(lineas) and lineas[i].strip() and not lineas[i].startswith(('#', '>', '|', '```')) \
                    and not re.match(r'^\s*[-*] ', lineas[i]) and lineas[i].strip() != '---':
                parrafo.append(lineas[i].strip())
                i += 1
            texto_p = ' '.join(parrafo)
            nota = clase_de_nota(texto_p)
            if nota:
                fuera.append(f'<div class="doc-nota doc-nota--{nota}"><p>{en_linea(texto_p)}</p></div>')
            else:
                fuera.append(f'<p>{en_linea(texto_p)}</p>')
            continue

        i += 1

    return '\n'.join(fuera)


def a_html_pestanas(md: str) -> str:
    """Para una ficha de comando: cada `### Título` de primer nivel es una pestaña clicable, no
    una subsección apilada. No es sintaxis nueva del `.md` —sigue siendo un encabezado normal—,
    es una forma distinta de montarlo cuando la ficha lo pide (`"tabs": true` en fichas.json)."""
    partes = re.split(r'^### (.+)$', md, flags=re.MULTILINE)
    intro = a_html(partes[0]) if partes[0].strip() else ''

    pestanas = list(zip(partes[1::2], partes[2::2]))
    if not pestanas:
        return intro  # la ficha no declaró pestañas: se comporta como cualquier otra

    botones, paneles = [], []
    for idx, (titulo, cuerpo) in enumerate(pestanas):
        pid = f'tab-{slug_de(titulo)}'
        clase_boton = ' is-active' if idx == 0 else ''
        botones.append(
            f'<button class="doc-tab{clase_boton}" data-tab="{pid}" type="button">'
            f'{en_linea(titulo)}</button>'
        )
        paneles.append(
            f'<div class="doc-tab-panel" id="{pid}"{"" if idx == 0 else " hidden"}>'
            f'{a_html(cuerpo)}</div>'
        )

    return (
        f'{intro}<div class="doc-tabs">'
        f'<div class="doc-tabs__botones" role="tablist">{"".join(botones)}</div>'
        f'<div class="doc-tabs__paneles">{"".join(paneles)}</div>'
        f'</div>'
    )


# ─────────────────────────────────────────────────────────────────────────────
# El montaje
# ─────────────────────────────────────────────────────────────────────────────

def main() -> None:
    catalogo = json.loads((RAIZ / 'fichas.json').read_text(encoding='utf-8'))
    paginas = sorted(catalogo['pages'], key=lambda p: p['position'])

    MAPA_ENLACES.clear()
    MAPA_ENLACES.update({Path(p['file']).name: p['slug'] for p in paginas})

    indice = ['<a href="#/" class="doc-indice__seccion" data-ruta="">Portada</a>']
    articulos = []
    familia_anterior = None

    for idx, pagina in enumerate(paginas):
        md = (RAIZ / pagina['file']).read_text(encoding='utf-8')
        titulo = pagina['title']
        slug = pagina['slug']
        familia = pagina.get('family', 'El manual')
        if familia != familia_anterior:
            indice.append(f'<p class="doc-indice__familia">{html.escape(familia)}</p>')
            familia_anterior = familia
        # El número que abre el título es del índice, no del encabezado de la ficha.
        limpio = re.sub(r'^\d+\s*·\s*', '', titulo)
        indice.append(
            f'<a href="#/{slug}" class="doc-indice__pieza" data-ruta="{slug}">{html.escape(titulo)}</a>'
        )

        # Anterior/siguiente, por el orden del catálogo — no hace falta pedirle nada al lector.
        anterior = paginas[idx - 1] if idx > 0 else None
        siguiente = paginas[idx + 1] if idx < len(paginas) - 1 else None
        lado_izq = '<span></span>'
        if anterior:
            t = html.escape(re.sub(r'^\d+\s*·\s*', '', anterior['title']))
            lado_izq = f'<a class="doc-paginacion__anterior" href="#/{anterior["slug"]}">← {t}</a>'
        lado_der = '<span></span>'
        if siguiente:
            t = html.escape(re.sub(r'^\d+\s*·\s*', '', siguiente['title']))
            lado_der = f'<a class="doc-paginacion__siguiente" href="#/{siguiente["slug"]}">{t} →</a>'
        paginacion = f'<nav class="doc-paginacion">{lado_izq}{lado_der}</nav>'

        cuerpo = a_html_pestanas(md) if pagina.get('tabs') else a_html(md)
        articulos.append(
            f'<article class="doc-pagina" data-ruta="{slug}" hidden>'
            f'<header class="doc-cabecera"><p class="doc-migas">Manual · Laravel Module Maker</p>'
            f'<h1>{html.escape(limpio)}</h1></header>{cuerpo}{paginacion}</article>'
        )

    (SALIDA / 'manual.html').write_text(
        PLANTILLA.replace('{{INDICE}}', '\n'.join(indice)).replace('{{ARTICULOS}}', '\n'.join(articulos)),
        encoding='utf-8',
    )
    print(f'manual.html armado con {len(paginas)} fichas.')


PLANTILLA = r"""<!DOCTYPE html>
<html lang="es" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laravel Module Maker — Innodite</title>
<meta name="description" content="Generador de módulos para Laravel: capas, seeders, migraciones, permisos, vistas y pruebas, con una sola estructura.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%2300b4e0'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/*
 * La misma interfaz que la documentación de la casa: índice a un lado, ficha al otro, tres
 * niveles y la ruta en el `hash`. Los tokens son los suyos — el cian de la marca, los grises
 * teñidos de cian (nunca neutros) y la esquina de origen recta en cada pieza.
 */
:root {
  --in-cian: #00b4e0;
  --in-cian-claro: #7dd4f0;

  --app-fondo: #0d1117;
  --app-fondo-2: #131a22;
  --app-borde: #23303d;
  --app-borde-vivo: #2f4152;
  --app-tinta: #e8eef4;
  --app-tinta-2: #a3b4c4;
  --app-tinta-3: #6b8296;

  --app-marca: var(--in-cian);
  --app-marca-suave: rgba(0, 180, 224, .12);

  --app-radio: 14px;
  --app-radio-sm: 8px;
  --app-radio-xs: 4px;

  --app-fuente: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --app-mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;
  color-scheme: dark;
}

:root[data-theme='light'] {
  --app-fondo: #ffffff;
  --app-fondo-2: #f5f8fa;
  --app-borde: #e2e8ee;
  --app-borde-vivo: #cbd6e0;
  --app-tinta: #101820;
  --app-tinta-2: #46586a;
  --app-tinta-3: #74899c;
  --app-marca-suave: rgba(0, 180, 224, .1);
  color-scheme: light;
}

/* El claro que decide la máquina. La guarda hace que elegir oscuro a mano gane sobre el sistema. */
@media (prefers-color-scheme: light) {
  :root:not([data-theme='dark']) {
    --app-fondo: #ffffff;
    --app-fondo-2: #f5f8fa;
    --app-borde: #e2e8ee;
    --app-borde-vivo: #cbd6e0;
    --app-tinta: #101820;
    --app-tinta-2: #46586a;
    --app-tinta-3: #74899c;
    --app-marca-suave: rgba(0, 180, 224, .1);
    color-scheme: light;
  }
}

* { box-sizing: border-box; }
html {
  font-family: var(--app-fuente);
  /* Cualquier salto a un ancla deja sitio para la barra fija, que mide 63px. */
  scroll-padding-block-start: 80px;
}
body {
  margin: 0; background: var(--app-fondo); color: var(--app-tinta);
  font-size: 14.5px; line-height: 1.6; -webkit-font-smoothing: antialiased;
}

.doc-barra {
  position: sticky; top: 0; z-index: 10;
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 14px 28px; background: var(--app-fondo);
  border-block-end: 1px solid var(--app-borde);
}
.doc-marca { display: flex; align-items: baseline; gap: 10px; font-weight: 650; letter-spacing: -.01em; }
.doc-marca b { color: var(--app-marca); }
.doc-version {
  font-family: var(--app-mono); font-size: 11px; padding: 2px 8px;
  background: var(--app-marca-suave); color: var(--app-marca);
  border-radius: 0 var(--app-radio-xs) var(--app-radio-xs) var(--app-radio-xs);
}
.doc-acciones { display: flex; align-items: center; gap: 8px; }
.doc-boton {
  font: inherit; font-size: 12.5px; cursor: pointer;
  padding: 6px 12px; color: var(--app-tinta-2); text-decoration: none;
  background: var(--app-fondo-2); border: 1px solid var(--app-borde);
  border-radius: 0 var(--app-radio-sm) var(--app-radio-sm) var(--app-radio-sm);
  transition: color .12s ease, border-color .12s ease;
}
.doc-boton:hover { color: var(--app-tinta); border-color: var(--app-borde-vivo); }

.doc {
  display: grid; grid-template-columns: 264px minmax(0, 1fr) 200px; gap: 28px;
  align-items: start; max-inline-size: 1320px; margin: 0 auto; padding: 28px;
}
.doc__indice {
  position: sticky; inset-block-start: 76px;
  max-block-size: calc(100vh - 100px); overflow-y: auto; padding-inline-end: 4px;
}
.doc__contenido { min-inline-size: 0; }
.doc__esquema {
  position: sticky; inset-block-start: 76px;
  max-block-size: calc(100vh - 100px); overflow-y: auto;
  display: grid; gap: 2px; align-content: start;
}
.doc__esquema[hidden] { display: none; }
.doc-esquema__titulo {
  margin: 0 0 6px; font-size: 11px; font-weight: 600; letter-spacing: .06em;
  text-transform: uppercase; color: var(--app-tinta-3);
}
.doc__esquema a {
  padding: 5px 10px; font-size: 12.5px; line-height: 1.5; color: var(--app-tinta-2);
  text-decoration: none; border-inline-start: 2px solid transparent;
}
.doc__esquema a:hover { color: var(--app-tinta); border-inline-start-color: var(--app-borde-vivo); }
@media (max-width: 1180px) {
  .doc { grid-template-columns: 264px minmax(0, 1fr); }
  .doc__esquema { display: none; }
}
@media (max-width: 900px) {
  .doc { grid-template-columns: 1fr; padding: 20px; }
  .doc__indice { position: static; max-block-size: none; }
}

.doc-buscar {
  inline-size: 100%; margin-block-end: 10px; padding: 7px 10px;
  font: inherit; font-size: 12.5px; color: var(--app-tinta);
  background: var(--app-fondo-2); border: 1px solid var(--app-borde);
  border-radius: 0 var(--app-radio-sm) var(--app-radio-sm) var(--app-radio-sm);
}
.doc-buscar:focus { outline: none; border-color: var(--app-marca); }
.doc-buscar::placeholder { color: var(--app-tinta-3); }

.doc-indice { display: grid; gap: 6px; }
.doc-indice__seccion, .doc-indice__pieza {
  display: block; text-decoration: none; color: var(--app-tinta-2);
  border-radius: 0 var(--app-radio-sm) var(--app-radio-sm) var(--app-radio-sm);
  transition: background-color .12s ease, color .12s ease;
}
.doc-indice__seccion { padding: 9px 12px; font-size: 14px; }
.doc-indice__pieza { padding: 6px 12px; font-size: 13px; }
.doc-indice__seccion:hover, .doc-indice__pieza:hover { background: var(--app-fondo-2); color: var(--app-tinta); }
.doc-indice__seccion.is-active { background: var(--app-marca-suave); color: var(--app-marca); font-weight: 600; }
.doc-indice__pieza.is-active { color: var(--app-marca); font-weight: 600; }
.doc-indice__familia {
  margin: 18px 0 4px; padding-inline: 12px;
  font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
  color: var(--app-tinta-3);
}

.doc-pagina { max-inline-size: 860px; }
.doc-pagina[hidden] { display: none !important; }
.doc-cabecera { margin-block-end: 32px; padding-block-end: 20px; border-block-end: 1px solid var(--app-borde); }
.doc-migas { margin: 0 0 6px; font-size: 11.5px; color: var(--app-tinta-3); text-transform: uppercase; letter-spacing: .05em; }
.doc-cabecera h1 { margin: 0; font-size: 30px; font-weight: 650; color: var(--app-tinta); letter-spacing: -.01em; }
.doc-resumen { margin: 10px 0 0; font-size: 16px; line-height: 1.6; color: var(--app-tinta-2); }

/* La esquina de origen recta y el filo de marca: el rasgo de la casa, también en la documentación. */
.doc-pagina h2 {
  margin: 32px 0 12px; font-size: 18px; font-weight: 600; color: var(--app-tinta);
  padding-inline-start: 10px; border-inline-start: 2px solid var(--app-marca);
}
.doc-pagina h3 { margin: 22px 0 6px; font-size: 14.5px; font-weight: 600; color: var(--app-tinta); }
.doc-pagina p { margin: 0 0 12px; color: var(--app-tinta); }
.doc-pagina a { color: var(--app-marca); }
.doc-pagina hr { margin: 28px 0; border: 0; border-block-start: 1px solid var(--app-borde); }

.doc-lista { margin: 0 0 12px; padding-inline-start: 20px; }
.doc-lista li { margin-block-end: 6px; line-height: 1.6; }
.doc-lista li::marker { content: '·  '; color: var(--app-tinta-3); }

/* Nota: cita o párrafo que empieza con ⛔/⚠️/⭐/✅. Mismo molde, un solo color que cambia. */
.doc-nota {
  margin: 0 0 16px; padding: 14px 18px;
  background: var(--app-fondo-2); color: var(--app-tinta-2);
  border-inline-start: 3px solid var(--nota-color, var(--in-cian-claro));
  border-radius: 0 var(--app-radio) var(--app-radio) var(--app-radio);
}
.doc-nota p { margin: 0; }
.doc-nota--peligro { --nota-color: #ef5a5a; background: rgba(239, 90, 90, .07); }
.doc-nota--aviso   { --nota-color: #e0a030; background: rgba(224, 160, 48, .07); }
.doc-nota--clave   { --nota-color: var(--in-cian); background: var(--app-marca-suave); }
.doc-nota--ok      { --nota-color: #3fb872; background: rgba(63, 184, 114, .07); }

code {
  font-family: var(--app-mono); font-size: .88em;
  padding: 1px 5px; background: var(--app-fondo-2); color: var(--in-cian-claro);
  border-radius: var(--app-radio-xs);
}
.doc-pre { position: relative; margin: 0 0 16px; }
.doc-pre__lang {
  position: absolute; inset-block-start: 10px; inset-inline-end: 14px;
  font-family: var(--app-mono); font-size: 10.5px; color: var(--app-tinta-3);
  text-transform: uppercase; letter-spacing: .06em; pointer-events: none;
}
pre {
  margin: 0; padding: 16px 18px; overflow-x: auto;
  background: var(--app-fondo-2); border: 1px solid var(--app-borde);
  border-radius: 0 var(--app-radio) var(--app-radio) var(--app-radio);
}
pre code { padding: 0; background: none; color: var(--app-tinta); font-size: 12.5px; line-height: 1.65; }

/* Una tabla ancha rueda sola: la página nunca se desplaza en horizontal. */
.doc-tabla { margin: 0 0 16px; overflow-x: auto; }
table { inline-size: 100%; border-collapse: collapse; font-size: 13.5px; }
th, td { padding: 9px 12px; text-align: start; border-block-end: 1px solid var(--app-borde); vertical-align: top; }
th { font-weight: 600; color: var(--app-tinta-3); font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; }
td { color: var(--app-tinta-2); }
td strong { color: var(--app-tinta); }
del { color: var(--app-tinta-3); text-decoration-color: var(--app-tinta-3); }
del code { color: var(--app-tinta-3); }

.doc-portada__intro { font-size: 16px; color: var(--app-tinta-2); max-inline-size: 720px; }
.doc-cartas { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-block-start: 24px; }
.doc-carta {
  display: block; padding: 16px 18px; text-decoration: none;
  background: var(--app-fondo-2); border: 1px solid var(--app-borde);
  border-inline-start: 2px solid var(--in-cian-claro);
  border-radius: 0 var(--app-radio) var(--app-radio) var(--app-radio);
  transition: border-color .12s ease;
}
.doc-carta:hover { border-color: var(--app-borde-vivo); border-inline-start-color: var(--app-marca); }
.doc-carta h3 { margin: 0 0 4px; font-size: 14.5px; font-weight: 600; color: var(--app-tinta); }
.doc-carta p { margin: 0; font-size: 13px; color: var(--app-tinta-2); }

.doc-paginacion {
  display: flex; justify-content: space-between; gap: 16px;
  margin-block-start: 40px; padding-block-start: 20px;
  border-block-start: 1px solid var(--app-borde);
}
.doc-paginacion a {
  flex: 1; max-inline-size: 48%; padding: 12px 16px; text-decoration: none;
  color: var(--app-tinta-2); font-size: 13px; line-height: 1.4;
  background: var(--app-fondo-2); border: 1px solid var(--app-borde);
  border-radius: 0 var(--app-radio) var(--app-radio) var(--app-radio);
  transition: color .12s ease, border-color .12s ease;
}
.doc-paginacion a:hover { color: var(--app-tinta); border-color: var(--app-borde-vivo); }
.doc-paginacion__siguiente { text-align: end; margin-inline-start: auto; }

/* Pestañas de una ficha de comando: Qué hace / Parámetros / Qué genera / Ejemplos... */
.doc-tabs { margin: 8px 0 16px; }
.doc-tabs__botones {
  display: flex; flex-wrap: wrap; gap: 4px;
  border-block-end: 1px solid var(--app-borde); margin-block-end: 20px;
}
.doc-tab {
  font: inherit; font-size: 13px; cursor: pointer; padding: 9px 14px;
  color: var(--app-tinta-2); background: none; border: none;
  border-block-end: 2px solid transparent; margin-block-end: -1px;
  transition: color .12s ease, border-color .12s ease;
}
.doc-tab:hover { color: var(--app-tinta); }
.doc-tab.is-active { color: var(--app-marca); border-block-end-color: var(--app-marca); font-weight: 600; }
.doc-tab-panel[hidden] { display: none; }
.doc-tab-panel > *:last-child { margin-block-end: 0; }

footer.doc-pie {
  max-inline-size: 1320px; margin: 0 auto; padding: 32px 28px;
  border-block-start: 1px solid var(--app-borde);
  color: var(--app-tinta-3); font-size: 12.5px;
}
</style>
</head>
<body>

<header class="doc-barra">
  <div class="doc-marca">Innodite <b>Module Maker</b> <span class="doc-version">v5.1</span></div>
  <div class="doc-acciones">
    <a class="doc-boton" href="index.html">Para gerencia</a>
    <a class="doc-boton" href="https://github.com/Innodite/laravel-module-maker">Repositorio</a>
    <button class="doc-boton" id="tema" type="button">Tema</button>
  </div>
</header>

<div class="doc">
  <aside class="doc__indice">
    <input type="search" id="doc-buscar" class="doc-buscar" placeholder="Buscar… ( / )" autocomplete="off">
    <nav class="doc-indice">
{{INDICE}}
    </nav>
  </aside>

  <div class="doc__contenido">
    <article class="doc-pagina" data-ruta="" hidden>
      <header class="doc-cabecera">
        <p class="doc-migas">Manual · Laravel Module Maker</p>
        <h1>El generador del patrón</h1>
        <p class="doc-resumen">Escribe el módulo entero —modelo, repositorio, servicio, controlador,
          validación, migración, seeders, rutas con el permiso de cada una, pantallas y pruebas— con
          un solo comando, y con la misma forma en una aplicación única y en multiinquilino.</p>
      </header>

      <p class="doc-portada__intro">Un módulo agrupa varias subfuncionalidades y cada una es
        autocontenida: <code>Modules/&lt;Módulo&gt;/&lt;SubFuncionalidad&gt;/&lt;Capa&gt;/&lt;Contexto&gt;/</code>.
        La capa va dentro de la subfuncionalidad y el contexto es la hoja, así que leer, mover o
        borrar una funcionalidad es leer, mover o borrar una carpeta.</p>

      <h2>Por dónde empezar</h2>
      <div class="doc-cartas">
        <a class="doc-carta" href="#/instalacion"><h3>Instalación</h3><p>Composer y el primer comando.</p></a>
        <a class="doc-carta" href="#/modificaciones-manuales"><h3>Modificaciones manuales</h3><p>Lo que el generador no escribe por su cuenta.</p></a>
        <a class="doc-carta" href="#/comando-doctor"><h3>innodite:doctor</h3><p>Empieza por aquí para diagnosticar el proyecto.</p></a>
        <a class="doc-carta" href="#/comando-make-module"><h3>innodite:make-module</h3><p>El generador principal, comando por comando.</p></a>
      </div>

      <h2 id="instalacion-en-dos-lineas">Instalación, en dos líneas</h2>
      <div class="doc-pre"><span class="doc-pre__lang">bash</span><pre><code>composer require innodite/laravel-module-maker:^5.1
php artisan innodite:module-setup</code></pre></div>
      <p>Y cuando algo no salga como esperabas, <code>php artisan innodite:doctor</code>: diagnostica
        en cascada y cada fallo trae la línea que lo arregla.</p>
    </article>

{{ARTICULOS}}
  </div>

  <aside class="doc__esquema" id="doc-esquema" hidden></aside>
</div>

<footer class="doc-pie">
  Innodite · Laravel Module Maker v5.1 — el manual se genera desde <code>docs/es/</code>, que es su
  fuente única.
</footer>

<script>
(() => {
  /* ⚠️ El navegador RESTAURA la posición de scroll al recargar, y lo hace después de este script:
     medido, volvía a dejarla en 91px y la barra fija tapaba el título de la ficha. Se desactiva la
     restauración, que aquí no aporta nada — cada ficha se lee desde arriba. */
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  const paginas = [...document.querySelectorAll('.doc-pagina')];
  const enlaces = [...document.querySelectorAll('[data-ruta]')].filter(e => e.tagName === 'A');

  /* La tabla de contenido de la ficha activa: se arma leyendo el DOM, no en Python, porque solo
     hace falta la de la página que se ve — las otras ~30 nunca se construyen. */
  function pintarEsquema(activa) {
    const esquema = document.getElementById('doc-esquema');
    if (!esquema) return;
    const articulo = paginas.find(p => p.dataset.ruta === activa);
    const encabezados = articulo ? [...articulo.querySelectorAll('h2[id]')] : [];
    if (!encabezados.length) { esquema.hidden = true; esquema.innerHTML = ''; return; }
    esquema.hidden = false;
    esquema.innerHTML = '<p class="doc-esquema__titulo">En esta página</p>' +
      encabezados.map(h => `<a href="#/${activa}/${h.id}">${h.textContent}</a>`).join('');
  }

  /* Enruta por `hash`: el contenido ya está en el navegador y no hay datos que traer, así que
     cambiar de ficha no debe costar una petición. Formato: #/ficha o #/ficha/seccion. */
  function pintar() {
    const resto = (location.hash.replace(/^#\/?/, '') || '');
    const barra = resto.indexOf('/');
    const ruta = barra === -1 ? resto : resto.slice(0, barra);
    const ancla = barra === -1 ? '' : resto.slice(barra + 1);
    const hay = paginas.some(p => p.dataset.ruta === ruta);
    const activa = hay ? ruta : '';

    paginas.forEach(p => { p.hidden = p.dataset.ruta !== activa; });
    enlaces.forEach(a => a.classList.toggle('is-active', a.dataset.ruta === activa));
    pintarEsquema(activa);

    if (ancla) {
      const el = document.getElementById(ancla);
      if (el) { el.scrollIntoView(); return; }
    }

    /* Arriba del todo, no `scrollIntoView`: eso deja el contenido pegado al borde del viewport y
       la barra fija le tapa el título. Medido en el navegador — el h1 salía cortado. */
    scrollTo({ top: 0 });
  }

  addEventListener('hashchange', pintar);
  pintar();

  /* Buscador del índice: filtra por texto, sin pedir nada al servidor — todo ya está en el DOM.
     `/` lo enfoca desde cualquier parte de la página, como en la documentación de la casa. */
  const buscador = document.getElementById('doc-buscar');
  const piezasIndice = [...document.querySelectorAll('.doc-indice__pieza')];
  buscador.addEventListener('input', () => {
    const q = buscador.value.trim().toLowerCase();
    piezasIndice.forEach(a => {
      a.style.display = !q || a.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
  addEventListener('keydown', (e) => {
    if (e.key === '/' && document.activeElement !== buscador) {
      e.preventDefault();
      buscador.focus();
    }
  });

  /* Pestañas de una ficha de comando: un solo listener delegado por grupo, sin estado en el hash
     — cambiar de pestaña no es navegar a otro sitio, así que no toca la URL. */
  document.querySelectorAll('.doc-tabs').forEach(grupo => {
    grupo.addEventListener('click', (e) => {
      const boton = e.target.closest('.doc-tab');
      if (!boton) return;
      const activo = boton.dataset.tab;
      grupo.querySelectorAll('.doc-tab').forEach(b => b.classList.toggle('is-active', b === boton));
      grupo.querySelectorAll('.doc-tab-panel').forEach(p => { p.hidden = p.id !== activo; });
    });
  });

  /* El tema se recuerda por visitante. Puede fallar —ventana privada, cookies bloqueadas—, así que
     va envuelto: sin valor guardado manda el sistema, que es el estado por defecto. */
  const raiz = document.documentElement;
  try {
    const guardado = localStorage.getItem('tema');
    if (guardado) raiz.dataset.theme = guardado;
  } catch (e) { /* sin memoria: manda el sistema */ }

  document.getElementById('tema').addEventListener('click', () => {
    const oscuroAhora = raiz.dataset.theme
      ? raiz.dataset.theme === 'dark'
      : !matchMedia('(prefers-color-scheme: light)').matches;
    const siguiente = oscuroAhora ? 'light' : 'dark';
    raiz.dataset.theme = siguiente;
    try { localStorage.setItem('tema', siguiente); } catch (e) { /* no se recuerda, y no pasa nada */ }
  });
})();
</script>

</body>
</html>
"""


if __name__ == '__main__':
    main()
