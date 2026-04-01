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
  job.stub notification.stub console-command.stub exception.stub
  vue-index.stub vue-create.stub vue-edit.stub vue-show.stub
  provider.stub
)

CONTEXT_STUBS=(
  controller.stub service.stub service-interface.stub
  repository.stub repository-interface.stub
  model.stub migration.stub seeder.stub factory.stub
  request.stub request-store.stub request-update.stub
  route-web.stub route-api.stub route-tenant.stub
  test.stub test-unit.stub test-support.stub
  job.stub notification.stub console-command.stub exception.stub
  vue-index.stub vue-create.stub vue-edit.stub vue-show.stub
)

CONTEXTS=(Central Shared TenantShared TenantName)

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
echo "--- Carpetas contextuales ---"
for ctx in "${CONTEXTS[@]}"; do
  found=0
  missing_list=()
  for stub in "${CONTEXT_STUBS[@]}"; do
    if [ -f "$BASE/$ctx/$stub" ] && [ -s "$BASE/$ctx/$stub" ]; then
      found=$((found+1))
    else
      missing_list+=("$stub")
      ERRORS=$((ERRORS+1))
    fi
  done
  total=${#CONTEXT_STUBS[@]}
  if [ ${#missing_list[@]} -eq 0 ]; then
    echo "[OK]   $ctx/ — $found/$total stubs"
  else
    echo "[FAIL] $ctx/ — $found/$total stubs | Faltan: ${missing_list[*]}"
  fi
done

echo ""
echo "--- Resumen ---"
if [ $ERRORS -eq 0 ]; then
  echo "Todos los stubs presentes. 0 errores."
else
  echo "$ERRORS errores encontrados."
fi

exit $ERRORS
