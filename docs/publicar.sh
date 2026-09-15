#!/usr/bin/env bash
# Arma una carpeta lista para subir al servidor por panel: regenera `index.html` desde las
# fichas (`armar.py`) y lo copia a `docs/publicar/`, más un .zip al lado para paneles que
# prefieren subir un solo archivo.
#
# El sitio es un único archivo autocontenido (CSS y JS inline, sin build, sin dependencias
# propias — solo Google Fonts por CDN), así que no hay nada más que empaquetar.
#
#   docs/publicar.sh
#
# Deja: docs/publicar/index.html  y  docs/publicar.zip

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DESTINO="$RAIZ/publicar"

echo "Regenerando index.html desde las fichas…"
python3 "$RAIZ/armar.py"

rm -rf "$DESTINO"
mkdir -p "$DESTINO"
cp "$RAIZ/index.html" "$DESTINO/index.html"

ZIP="$RAIZ/publicar.zip"
rm -f "$ZIP"
python3 -c "
import zipfile
with zipfile.ZipFile('$ZIP', 'w', zipfile.ZIP_DEFLATED) as z:
    z.write('$DESTINO/index.html', 'index.html')
"

echo
echo "Listo:"
echo "  $DESTINO/index.html   ← subir este archivo tal cual a la raíz del subdominio"
echo "  $ZIP                  ← el mismo, en .zip, para paneles que solo aceptan un archivo"
