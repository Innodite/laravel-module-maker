#!/usr/bin/env bash
# Regenera `manual.html` desde las fichas (`armar.py`) y arma un .zip para quien prefiera subir
# un solo archivo al panel del hosting.
#
# ⭐ `index.html` (portada gerencial), `manual.html` y `assets/` viven en la RAÍZ del repositorio
# — no en `docs/` — a propósito: es lo que hace que, al apuntar el hosting del subdominio
# directamente a este repositorio, el sitio ya esté en su lugar sin mover nada. Si el panel es de
# subida manual, se suben esos tres elementos sueltos desde la raíz del repo, tal cual están.
#
# El sitio es autocontenido (CSS y JS inline, sin build, sin dependencias propias — solo
# Google Fonts por CDN); lo único que no va inline son las imágenes de Nodite en la portada.
#
# ⚠️ Tras correr esto, comitear `manual.html` junto con el cambio que lo motivó — si no, la
# copia en el repo queda desactualizada respecto a las fichas.
#
#   docs/publicar.sh
#
# Deja: manual.html regenerado en la raíz  y  publicar.zip (raíz, no versionado)

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Regenerando manual.html desde las fichas…"
python3 "$RAIZ/docs/armar.py"

ZIP="$RAIZ/publicar.zip"
rm -f "$ZIP"
python3 -c "
import pathlib, zipfile
raiz = pathlib.Path('$RAIZ')
with zipfile.ZipFile('$ZIP', 'w', zipfile.ZIP_DEFLATED) as z:
    z.write(raiz / 'index.html', 'index.html')
    z.write(raiz / 'manual.html', 'manual.html')
    for archivo in sorted((raiz / 'assets').rglob('*')):
        if archivo.is_file():
            z.write(archivo, archivo.relative_to(raiz))
"

echo
echo "Listo:"
echo "  $RAIZ/index.html, $RAIZ/manual.html, $RAIZ/assets/   ← ya están en la raíz del repo, se suben tal cual"
echo "  $ZIP                                                  ← lo mismo, en .zip, para paneles que solo aceptan un archivo"
