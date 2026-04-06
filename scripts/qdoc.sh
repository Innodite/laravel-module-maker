#!/usr/bin/env bash
# qdoc.sh — Consulta una sección @TAG en skills/module-maker.md
#
# Uso:
#   ./scripts/qdoc.sh {TAG}
#   ./scripts/qdoc.sh {TAG} {SUBTAG}
#
# Tags disponibles:
#   ESTRUCTURA    — Árbol de archivos críticos
#   CONTEXTOS     — Estructura de contexts.json
#   MANIFESTS     — Naming de manifests (post-R03)
#   CONEXION      — Flujo de resolución de conexión
#   GUARD         — Guard Rail R03
#   API_CONTEXTRESOLVER — API de ContextResolver
#   API_TARGETSERVICE   — API de MigrationTargetService
#   EXCEPTIONS    — Excepciones del paquete
#   TESTCASE      — TestCase base
#   BUGS          — Bugs resueltos
#   ESTADO        — Estado actual y actividades
#
# Ejemplos:
#   ./scripts/qdoc.sh MANIFESTS
#   ./scripts/qdoc.sh GUARD
#   ./scripts/qdoc.sh ESTADO

TAG="${1:-ESTADO}"
DOC="$(dirname "$0")/../skills/module-maker.md"

if [[ ! -f "$DOC" ]]; then
    echo "❌ No se encontró skills/module-maker.md"
    exit 1
fi

result=$(awk "/^## @${TAG}[[:space:]]*—/{found=1; print; next} found && /^## @/{exit} found{print}" "$DOC")

if [[ -z "$result" ]]; then
    echo "⚠️  Sección @${TAG} no encontrada en skills/module-maker.md"
    echo ""
    echo "Tags disponibles:"
    grep "^## @" "$DOC" | sed 's/^## @/  - /' | sed 's/ —.*//'
    exit 1
fi

echo "=== @${TAG} ==="
echo "$result"
