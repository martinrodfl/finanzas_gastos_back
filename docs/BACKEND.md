# Backend de Finanzas Gastos

Fecha: 29 de marzo de 2026

## 1. Vision General

Este backend esta construido con Laravel 13 y actualmente resuelve una funcion principal:

- recibir un archivo Excel con movimientos financieros
- transformar y validar los datos por fila
- guardar los movimientos en base de datos

El sistema todavia esta en una etapa inicial (MVP), con una API reducida y un unico endpoint de negocio.

## 2. Stack Tecnologico

- PHP 8.3
- Laravel 13
- Maatwebsite Excel 3.1 (importacion de archivos Excel)
- Eloquent ORM
- SQLite por defecto (con soporte de configuracion para MySQL)

Referencia de dependencias: `composer.json`.

## 3. Arquitectura Funcional Actual

### 3.1 Flujo principal

1. Cliente envia `POST /api/movimientos/import` con un archivo (`xlsx` o `xls`).
2. El controlador valida que exista el archivo y su tipo.
3. Laravel Excel procesa cada fila con `MovimientosImport`.
4. Se normalizan fechas, texto y montos.
5. Se crea un registro en la tabla `movimientos` por fila.
6. El endpoint responde JSON de exito o error.

### 3.2 Endpoint disponible

- Metodo: `POST`
- Ruta: `/api/movimientos/import`
- Controlador: `MovimientoController@import`
- Request esperado: campo `file` con archivo Excel
- Respuesta exito: `{ "success": true, "message": "Movimientos importados correctamente" }`
- Respuesta error: `{ "success": false, "error": "..." }` con HTTP 500

Archivos fuente:

- `routes/api.php`
- `app/Http/Controllers/MovimientoController.php`

## 4. Base de Datos

## 4.1 Tablas de dominio

### `movimientos`

Tabla principal de transacciones importadas.

Campos clave:

- `id` (PK)
- `fecha` (date, nullable)
- `descripcion` (text, nullable)
- `documento` (string 50, nullable)
- `asunto` (text, nullable)
- `dependencia` (string 100, nullable)
- `debito` (decimal 12, nullable)
- `credito` (decimal 12, nullable)
- `created_at` (timestamp, nullable, default current)
- `categoria_manual` (string 100, nullable)

Observaciones:

- no existe `updated_at` en la tabla
- el modelo `Movimiento` tambien desactiva `UPDATED_AT`

### `categoria_reglas`

Tabla de referencia para mapear descripciones a categorias.

Campos:

- `id` (PK)
- `descripcion` (string, unique)
- `categoria` (string 100)

Observacion:

- la tabla existe, pero en el estado actual no hay relacion activa en modelos ni uso directo en el flujo de importacion

## 4.2 Tablas de infraestructura (Laravel)

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

Archivos fuente:

- `database/migrations/2026_03_29_230055_create_movimientos_table.php`
- `database/migrations/2026_03_29_230055_create_categoria_reglas_table.php`
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`

## 5. Logica de Importacion (Excel -> BD)

La clase `MovimientosImport` implementa:

- `ToModel`
- `WithHeadingRow`
- `WithValidation`

Comportamiento principal:

- toma columnas por nombre desde la cabecera del Excel
- limpia campos de texto con `trim`
- convierte fechas en formato Excel numerico a fecha real
- intenta parsear fechas de texto con Carbon
- convierte montos con formato local (por ejemplo `1.234,56` a `1234.56`)
- si un monto no es valido, retorna `0`

Validaciones por fila actuales:

- `fecha`: nullable
- `descripcion`: nullable|string
- `debito`: nullable
- `credito`: nullable

Archivo fuente:

- `app/Imports/MovimientosImport.php`

## 6. Modelos

### `Movimiento`

- tabla: `movimientos`
- `fillable`: fecha, descripcion, documento, asunto, dependencia, debito, credito, categoria_manual
- casts:
    - `fecha` => `date`
    - `debito` => `decimal:2`
    - `credito` => `decimal:2`
- sin relaciones definidas actualmente

### `User`

Modelo estandar de Laravel para autenticacion por sesion.

## 7. Seguridad y Autenticacion (Estado Actual)

- el endpoint de importacion no tiene middleware de autenticacion aplicado en `routes/api.php`
- la configuracion de auth por defecto usa guard `web` (driver `session`)

Implicacion practica:

- actualmente la API de importacion puede estar expuesta si no se protege a nivel de red o middleware
- para integracion robusta con frontend SPA suele definirse estrategia API (por ejemplo tokens)

Archivo fuente:

- `config/auth.php`

## 8. Alcance Actual y Limites

Lo que el backend hace hoy:

- importar movimientos desde Excel
- guardar transacciones en base de datos

Lo que aun no aparece implementado en este backend:

- CRUD completo de movimientos por API (listado, detalle, actualizacion, borrado)
- categorizacion automatica usando `categoria_reglas`
- auditoria de importaciones (quien importo, cuando, archivo origen)
- proteccion explicita del endpoint de importacion con auth en rutas API

## 9. Mapa Rapido de Archivos

- `routes/api.php`: endpoint API activo
- `app/Http/Controllers/MovimientoController.php`: punto de entrada de importacion
- `app/Imports/MovimientosImport.php`: transformacion/validacion de filas Excel
- `app/Models/Movimiento.php`: modelo principal de dominio
- `database/migrations/2026_03_29_230055_create_movimientos_table.php`: estructura de movimientos
- `database/migrations/2026_03_29_230055_create_categoria_reglas_table.php`: estructura de reglas
- `config/auth.php`: esquema de autenticacion actual

## 10. Resumen Ejecutivo

El backend esta operativo para una necesidad puntual: cargar datos financieros desde Excel y persistirlos. La base de datos ya tiene una tabla principal de movimientos y una tabla de reglas de categoria para evolucion futura. El siguiente salto natural es ampliar API de consulta/gestion, formalizar seguridad de endpoints y conectar la tabla de reglas a la logica de negocio.
