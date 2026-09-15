#!/usr/bin/env bash
# Arma una carpeta lista para subir al servidor por panel: regenera `index.html` desde las
# fichas (`armar.py`) y lo copia a `docs/publicar/` junto con el landing gerencial y sus
# imágenes, más un .zip al lado para paneles que prefieren subir un solo archivo.
#
# El sitio es autocontenido (CSS y JS inline, sin build, sin dependencias propias — solo
# Google Fonts por CDN); lo único que no va inline son las imágenes de Nodite en el landing.
#
#   docs/publicar.sh
#
# Deja: docs/publicar/ (index.html, landing-gerencial.html, assets/)  y  docs/publicar.zip

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESTINO="$RAIZ/publicar"

echo "Regenerando index.html desde las fichas…"
python3 "$RAIZ/armar.py"

rm -rf "$DESTINO"
mkdir -p "$DESTINO"
cp "$RAIZ/index.html" "$DESTINO/index.html"
cp "$RAIZ/landing-gerencial.html" "$DESTINO/landing-gerencial.html"
cp -r "$RAIZ/assets" "$DESTINO/assets"

ZIP="$RAIZ/publicar.zip"
rm -f "$ZIP"
python3 -c "
import pathlib, zipfile
destino = pathlib.Path('$DESTINO')
with zipfile.ZipFile('$ZIP', 'w', zipfile.ZIP_DEFLATED) as z:
    for archivo in sorted(destino.rglob('*')):
        if archivo.is_file():
            z.write(archivo, archivo.relative_to(destino))
"

echo
echo "Listo:"
echo "  $DESTINO/   ← subir el CONTENIDO de esta carpeta tal cual a la raíz del subdominio"
echo "  $ZIP        ← lo mismo, en .zip, para paneles que solo aceptan un archivo"
