# Sistema de Gestión de Facturas

Sistema de facturación con integración Verifactu / AEAT.

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
