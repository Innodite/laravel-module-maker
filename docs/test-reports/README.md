# Test Reports

Esta carpeta contiene los reportes de cobertura de código generados por el comando `innodite:test-module`.

## Estructura

Los reportes se organizan por módulo:

```
test-reports/
├── User/
│   ├── html/
│   │   └── index.html
│   └── clover.xml
├── Product/
│   ├── html/
│   │   └── index.html
│   └── clover.xml
└── ...
```

## Formatos Disponibles

- **HTML**: Reporte navegable con detalles visuales de cobertura
- **Clover**: Formato XML para integración con CI/CD
- **Text**: Salida en consola con porcentaje de cobertura

## Uso

```bash
# Generar reporte HTML y texto (por defecto)
php artisan innodite:test-module User --coverage

# Generar solo HTML
php artisan innodite:test-module User --coverage --format=html

# Generar todos los formatos
php artisan innodite:test-module User --coverage --format=html --format=text --format=clover
```

## Nota

Los reportes son generados localmente y pueden ser agregados a `.gitignore` si no deseas versionarlos:

```gitignore
# Ignorar reportes de test
docs/test-reports/*
!docs/test-reports/.gitkeep
!docs/test-reports/README.md
```
