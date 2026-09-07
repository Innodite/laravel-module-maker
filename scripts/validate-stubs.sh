#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

ERRORS=0
BASE="stubs/contextual"

BASE_STUBS=(
  controller.stub service.stub service-interface.stub
  repository.stub repository-interface.stub
  model.stub migration.stub seeder.stub factory.stub
  request.stub request-store.stub request-update.stub
  route-web.stub route-api.stub route-tenant.stub
  test.stub test-unit.stub test-support.stub
  vue-index.stub vue-show.stub
  provider.stub
)

echo "=== VALIDACIÓN DE STUBS — innodite/laravel-module-maker ==="
echo ""
echo "--- Stubs base ($BASE/) ---"
for stub in "${BASE_STUBS[@]}"; do
  if [ -f "$BASE/$stub" ] && [ -s "$BASE/$stub" ]; then
    echo "[OK]      $stub"
  else
    echo "[MISSING] $stub"
    ERRORS=$((ERRORS+1))
  fi
done

echo ""
echo "--- Copias por contexto (no deben existir) ---"
STALE=0
for ctx in Central Shared TenantShared TenantName; do
  if [ -d "$BASE/$ctx" ]; then
    echo "[FAIL] $BASE/$ctx/ existe — el paquete tiene UNA sola copia de cada stub."
    echo "       Una copia por contexto le gana por prioridad al stub base y lo deja sin efecto."
    echo "       Corrige con: git rm -r $BASE/$ctx"
    STALE=$((STALE+1))
    ERRORS=$((ERRORS+1))
  fi
done
[ $STALE -eq 0 ] && echo "[OK]      ninguna copia por contexto dentro del paquete"

echo ""
echo "--- Resumen ---"
if [ $ERRORS -eq 0 ]; then
  echo "Todos los stubs presentes, sin duplicados. 0 errores."
else
  echo "$ERRORS errores encontrados."
fi

exit $ERRORS
