# Sistema de Gestión de Facturas

Sistema de facturación con integración Verifactu / AEAT.

## Instalación con Docker (recomendado)

Todo el sistema corre en 3 contenedores (web + base de datos + conversor de PDF).
No hace falta instalar XAMPP, PHP ni MySQL a mano.

### Requisitos
- **Docker Desktop** instalado y arrancado.
  Actívalo para que arranque con Windows: *Settings → General → "Start Docker
  Desktop when you sign in"*. Junto con `restart: unless-stopped`, los servicios
  se levantan solos al encender el PC (hay ~10-30 s hasta que todo responde).

### Puesta en marcha
```bash
# 1. Configurar el cliente (ver "Alta de cliente nuevo")
copy .env.example .env      # y editar el .env

# 2. Levantar todo
docker compose up -d --build
```
Abrir en el navegador: **http://localhost:8080/SistemaGestionFacturas/**
(usuario `admin`, contraseña `Admin1` — cámbiala tras la entrega).

La base de datos se inicializa **automáticamente la primera vez** con
`database-clean.sql` (catálogos esenciales, sin datos de prueba).

### Comandos útiles
```bash
docker compose ps           # estado de los servicios
docker compose logs -f web  # ver logs de Apache/PHP en vivo
docker compose down         # parar (los datos de la BD se conservan)
docker compose down -v      # parar Y BORRAR la BD (vuelve a inicializarse limpia)
```
> La BD vive en el volumen `db_data`. `database-clean.sql` solo se ejecuta cuando
> ese volumen está vacío; para reinicializarla, `docker compose down -v`.

---

## Alta de cliente nuevo

Para preparar la entrega a un cliente **solo se edita el `.env` y se copia su
certificado**. No se toca código. Pasos:

1. **Certificado digital**
   - Borrar el `.p12` de pruebas de `src/storage/certs/`.
   - Copiar el `.p12` del cliente en `src/storage/certs/`.

2. **Editar el `.env`** (copiado de `.env.example`):
   - `VERIFACTU_CERT_ARCHIVO` = nombre exacto del `.p12` del cliente.
   - `VERIFACTU_CERT_PASSWORD` = contraseña del certificado.
   - `VERIFACTU_ENTORNO=produccion` (para facturar de verdad ante la AEAT).
   - `EMPRESA_NIF` y `EMPRESA_RAZON_SOCIAL` → **deben coincidir con el titular
     del certificado**, o la AEAT rechazará TODAS las facturas.
   - Resto de datos de empresa que salen en la factura: `EMPRESA_TELEFONO`,
     `EMPRESA_EMAIL`, `EMPRESA_WEB`, `EMPRESA_DATOS_BANCARIOS`, dirección...
   - SMTP del cliente: `SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD`,
     `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`.
     *(En Gmail, `SMTP_PASSWORD` es una "contraseña de aplicación", no la normal.)*
   - Contraseñas de BD: `DB_PASSWORD` y `DB_ROOT_PASSWORD` (poner unas propias).

3. **Arrancar**: `docker compose up -d --build`.
   La BD se crea vacía de datos de prueba; `Verifactu_Registros` queda vacía para
   que la cadena de huellas de Verifactu empiece limpia en este cliente.

4. **Tras el primer arranque**: cambiar la contraseña del usuario `admin`.

> ⚠️ Verifactu encadena cada factura con la huella de la anterior. NUNCA mezcles
> registros de prueba con los del cliente: arrancarías la cadena corrupta.

## Cómo ver qué error ha dado (logs)

El sistema registra los errores de forma centralizada mediante la clase
[`Logger`](src/core/Logger.php). Se genera **un archivo de log por servicio**
dentro de `src/storage/logs/`. Cuando algo falla, ahí queda registrado con la
fecha y hora exacta, el nivel y el detalle del error.

### Dónde mirar según lo que falle

| Si falla...                                   | Mira el archivo                    |
| --------------------------------------------- | ---------------------------------- |
| Conexión o consultas a la base de datos       | `src/storage/logs/database.log`    |
| Envío a Hacienda / Verifactu                  | `src/storage/logs/verifactu.log`   |
| Firma digital del certificado                 | `src/storage/logs/firma_digital.log`|
| Envío de emails                               | `src/storage/logs/email.log`       |
| Generación de PDF / DOCX                       | `src/storage/logs/pdf.log`         |
| Generación del código QR                       | `src/storage/logs/qrcode.log`      |
| Numeración de documentos                       | `src/storage/logs/numeracion.log`  |
| Conversión de albaranes a factura              | `src/storage/logs/conversion.log`  |
| Cualquier error 500 de un endpoint de la API   | `src/storage/logs/http.log`        |

> El nombre del archivo es el del servicio (p. ej. `verifactu.log`,
> `email.log`...). Si no existe es que ese servicio todavía no ha registrado
> ningún error.

### Formato de cada línea

```
[2026-06-05 17:29:06] ERROR: Table 'verifactu.tabla_x' doesn't exist | {"sql":"SELECT ...","origen":"database.php:37"}
```

1. **Fecha y hora** exacta del fallo.
2. **Nivel**: `ERROR`, `WARNING` o `INFO`.
3. **Mensaje** del error.
4. **Contexto** en JSON: datos útiles (documento, SQL, destino...) y, en las
   excepciones, el `origen` con el archivo y la línea donde se produjo.

### Ver los logs

**Windows (PowerShell) — en tiempo real mientras usas la app:**

```powershell
Get-Content src\storage\logs\verifactu.log -Wait -Tail 20
```

**Ver las últimas líneas de un log concreto:**

```powershell
Get-Content src\storage\logs\verifactu.log -Tail 50
```

**Vaciar un log para empezar de cero:**

```powershell
Clear-Content src\storage\logs\verifactu.log
```

### Probar que el registro funciona

Para forzar un error y comprobar que se registra (consulta a una tabla
inexistente):

```powershell
php -r "require 'src/config/database.php'; try { Database::getInstance()->query('SELECT * FROM no_existe'); } catch (\Throwable $e) {} echo file_get_contents('src/storage/logs/database.log');"
```

### Notas

- Los archivos `.log` **no se versionan** en git (están en
  `src/storage/logs/.gitignore`): son datos locales de cada instalación.
- `verifactu_debug.log` es aparte: contiene el volcado completo del XML SOAP de
  petición y la respuesta de la AEAT (para depurar la comunicación), no errores.
