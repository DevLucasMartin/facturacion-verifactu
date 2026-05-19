# Plan de Pruebas — Sistema Gestión Facturas (Solventia Tecnología S.L.)

**Versión:** 1.0  
**Fecha:** 2026-05-04  
**Proyecto:** Sistema de Gestión de Facturas con integración Verifactu/AEAT  
**Tecnologías:** PHP 8.1+, MySQL 5.7+, Bootstrap 5, JavaScript (Vanilla + jQuery)

---

## 1. Objetivo del Plan de Pruebas

Verificar que el sistema de gestión de facturas cumple los requisitos funcionales, de calidad y de conformidad legal exigidos para la emisión electrónica de facturas en España según la normativa Verifactu (AEAT), garantizando la corrección de los cálculos fiscales, la integridad de los documentos generados y la robustez de las integraciones externas.

---

## 2. Alcance

### En alcance:
- Módulo de Facturas (creación, edición, visualización, PDF, email)
- Módulo de Albaranes (creación, edición, facturación)
- Módulo de Clientes (CRUD, validación NIF/CIF)
- Módulo de Artículos (catálogo, tarifas múltiples)
- Módulo de Configuración (tipos IVA, canales, formas de pago)
- Integración Verifactu/AEAT (generación XML, firma digital, envío)
- Generación de documentos (PDF, Excel, QR)
- Envío de emails (SMTP)
- API REST interna (endpoints JSON)
- Cálculos fiscales (IVA, Recargo de Equivalencia, descuentos)

### Fuera de alcance:
- Infraestructura de red/hosting
- Seguridad de red perimetral
- Rendimiento bajo carga masiva (>10.000 usuarios simultáneos)

---

## 3. Tipos de Prueba

### 3.1 Pruebas Unitarias
Verifican funciones y métodos aislados.
- **Herramienta:** PHPUnit (PHP), Jest (JavaScript)
- **Archivos objetivo:** `src/services/`, `src/core/Validator.php`, `src/core/Response.php`, `assets/js/calculos.js`

### 3.2 Pruebas de Integración
Verifican la interacción entre componentes (controlador → servicio → modelo → BD).
- **Herramienta:** PHPUnit con base de datos de prueba MySQL
- **Archivos objetivo:** `src/controllers/`, `src/models/`, `src/api/`

### 3.3 Pruebas Funcionales (End-to-End)
Verifican flujos completos desde la interfaz de usuario.
- **Herramienta:** Ejecución manual / Postman para APIs
- **Ámbito:** Todos los módulos visibles en la UI

### 3.4 Pruebas de Regresión
Se ejecutan tras cada cambio significativo para garantizar que nada se rompe.
- Subconjunto de casos críticos de pruebas funcionales y unitarias.

### 3.5 Pruebas de Validación Legal / Cumplimiento
Verifican conformidad con normativa española (Verifactu, IVA, NIF).
- Generación de XML conforme a esquema AEAT
- Validez de firmas digitales XAdES
- Cadena hash (fingerprint) entre facturas

---

## 4. Módulos y Casos de Prueba

### 4.1 Módulo: Cálculo Fiscal (CalculoService)

| ID | Caso de Prueba | Entrada | Resultado Esperado | Tipo |
|----|---------------|---------|-------------------|------|
| CP-CAL-01 | Cálculo base imponible con descuento | 100€, 10% dto | Base = 90€ | Unitaria |
| CP-CAL-02 | Cálculo IVA 21% sobre base | Base 90€ | IVA = 18,90€, Total = 108,90€ | Unitaria |
| CP-CAL-03 | Cálculo Recargo de Equivalencia 5,2% | Base 100€, RE activo | RE = 5,20€ | Unitaria |
| CP-CAL-04 | IVA tipo reducido 10% (Canarias IGIC 7%) | Región Canarias | IVA correcto según región | Unitaria |
| CP-CAL-05 | Factura con líneas mixtas (21%, 10%, exento) | 3 líneas distintas | Totales correctos por tipo | Unitaria |
| CP-CAL-06 | Descuentos acumulados (Especial + PP + Comercial) | 3 descuentos | Descuento total correcto | Unitaria |
| CP-CAL-07 | Tarifa de precio alternativa (precio2..precio8) | Cliente tarifa 3 | Aplica precio3 del artículo | Integración |
| CP-CAL-08 | Factura con importe 0€ (exenta total) | Líneas exentas | Total = 0€, sin IVA | Unitaria |

### 4.2 Módulo: Validación NIF/CIF (Validator)

| ID | Caso de Prueba | Entrada | Resultado Esperado | Tipo |
|----|---------------|---------|-------------------|------|
| CP-VAL-01 | NIF persona física válido | "12345678Z" | Válido | Unitaria |
| CP-VAL-02 | NIF con letra incorrecta | "12345678A" | NifInvalidoException | Unitaria |
| CP-VAL-03 | CIF empresa válido | "B74521980" | Válido | Unitaria |
| CP-VAL-04 | NIE extranjero válido | "X1234567L" | Válido | Unitaria |
| CP-VAL-05 | NIF vacío/nulo | "" | Error de validación | Unitaria |
| CP-VAL-06 | NIF con formato incorrecto | "ABCDEFGHZ" | Error de validación | Unitaria |

### 4.3 Módulo: Facturas

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-FAC-01 | Crear factura ordinaria (F1) con cliente y líneas | Factura guardada, número asignado, estado "borrador" | Funcional |
| CP-FAC-02 | Crear factura simplificada (F2/ticket) | Serie S, sin datos cliente obligatorios | Funcional |
| CP-FAC-03 | Crear factura rectificativa (R1) referenciando original | Campo factura_original relleno, tipo R1 | Funcional |
| CP-FAC-04 | Crear factura recapitulativa (F3) | Agrupa múltiples operaciones del periodo | Funcional |
| CP-FAC-05 | Numeración automática correlativa | Factura 2025-A-001 → 2025-A-002 | Integración |
| CP-FAC-06 | Generar PDF de factura | PDF descargable con logo, datos correctos, QR | Funcional |
| CP-FAC-07 | Enviar factura por email | Email recibido con PDF adjunto | Funcional |
| CP-FAC-08 | Exportar listado de facturas a Excel | XLSX con columnas correctas y datos | Funcional |
| CP-FAC-09 | Paginar listado de facturas | Página 2 muestra siguientes 20 registros | Funcional |
| CP-FAC-10 | Filtrar facturas por fecha y cliente | Solo muestra facturas del filtro | Funcional |
| CP-FAC-11 | Editar factura en estado borrador | Cambios guardados correctamente | Funcional |
| CP-FAC-12 | Intentar editar factura emitida (Verifactu enviado) | Error o advertencia al usuario | Funcional |

### 4.4 Módulo: Albaranes

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-ALB-01 | Crear albarán con artículos | Albarán guardado con número correlativo | Funcional |
| CP-ALB-02 | Editar albarán existente | Cambios persistidos en BD | Funcional |
| CP-ALB-03 | Facturar un albarán individual | Factura generada vinculada al albarán | Funcional |
| CP-ALB-04 | Facturar múltiples albaranes juntos | Factura única agrupa todos los albaranes | Funcional |
| CP-ALB-05 | Listar albaranes con paginación y filtros | Resultados correctos, paginación funciona | Funcional |

### 4.5 Módulo: Clientes

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-CLI-01 | Crear cliente empresa con CIF | CIF validado y guardado | Funcional |
| CP-CLI-02 | Crear cliente persona física con NIF | NIF validado y guardado | Funcional |
| CP-CLI-03 | Activar Recargo de Equivalencia en cliente | Campo RE activo, cálculos afectados | Funcional |
| CP-CLI-04 | Asignar tarifa de precios al cliente | Factura usa tarifa asignada | Integración |
| CP-CLI-05 | Buscar cliente por nombre/NIF en autocompletado | Resultados relevantes en <500ms | Funcional |
| CP-CLI-06 | Editar datos de cliente con facturas existentes | Datos actualizados sin afectar facturas ya emitidas | Funcional |

### 4.6 Módulo: Artículos / Catálogo

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-ART-01 | Crear artículo con 8 tarifas de precio | Todos los precios guardados | Funcional |
| CP-ART-02 | Buscar artículo por código de barras | Artículo encontrado y seleccionado en línea | Funcional |
| CP-ART-03 | Artículo con IVA exento | IVA = 0 en factura | Integración |
| CP-ART-04 | Stock mínimo: alerta al crear albarán | Aviso si stock < mínimo | Funcional |

### 4.7 Módulo: Configuración

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-CONF-01 | Crear nuevo tipo de IVA | Tipo guardado, disponible en facturas | Funcional |
| CP-CONF-02 | Crear canal/serie de facturación | Serie aparece al crear facturas | Funcional |
| CP-CONF-03 | Editar forma de pago | Cambio reflejado en formularios | Funcional |

### 4.8 Módulo: Verifactu / AEAT

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-VER-01 | Generar XML de factura F1 | XML válido según esquema AEAT | Integración |
| CP-VER-02 | XML de factura F2 (simplificada) | Campos correctos para F2 | Integración |
| CP-VER-03 | XML de factura rectificativa R1 | Referencia a factura original incluida | Integración |
| CP-VER-04 | Cadena hash entre facturas | Hash de factura N incluido en factura N+1 | Integración |
| CP-VER-05 | Firma digital XAdES del XML | Firma válida, verificable | Integración |
| CP-VER-06 | Envío a entorno de PRUEBAS AEAT | Respuesta 200 OK, registro guardado | Funcional |
| CP-VER-07 | Reintentos con backoff exponencial (max 5) | Sistema reintenta si falla la primera vez | Integración |
| CP-VER-08 | QR code de verificación en factura | URL AEAT correcta encodificada en QR | Integración |
| CP-VER-09 | Listado de registros Verifactu con estados | Estados: pendiente, enviado, error | Funcional |

### 4.9 Módulo: Generación de Documentos

| ID | Caso de Prueba | Resultado Esperado | Tipo |
|----|---------------|-------------------|------|
| CP-DOC-01 | Generar PDF factura ordinaria | PDF con todos los campos, legible | Funcional |
| CP-DOC-02 | Generar PDF albarán | PDF con cabecera y líneas correctas | Funcional |
| CP-DOC-03 | Generar PDF factura simplificada | Template simplificada aplicado | Funcional |
| CP-DOC-04 | Generar PDF proforma | Template proforma aplicado | Funcional |
| CP-DOC-05 | Exportar Excel listado facturas | XLSX descargable con datos correctos | Funcional |
| CP-DOC-06 | QR en PDF apunta a URL AEAT correcta | URL verificable | Funcional |

### 4.10 API REST Interna

| ID | Caso de Prueba | Endpoint | Resultado Esperado | Tipo |
|----|---------------|----------|-------------------|------|
| CP-API-01 | GET listado facturas paginado | `api/facturas.php?page=1` | JSON paginado, status 200 | Integración |
| CP-API-02 | POST crear factura con datos válidos | `api/facturas.php` | Factura creada, status 201 | Integración |
| CP-API-03 | POST con NIF inválido | `api/clientes.php` | Error 422 con mensaje descriptivo | Integración |
| CP-API-04 | GET búsqueda de artículos | `api/articulos.php?q=texto` | Array de artículos encontrados | Integración |
| CP-API-05 | Petición con método no permitido | cualquiera | Error 405 | Integración |

---

## 5. Entornos de Prueba

| Entorno | Descripción | Acceso |
|---------|------------|--------|
| **Local (XAMPP)** | Desarrollo y pruebas unitarias/integración | `localhost/SistemaGestionFacturas` |
| **Verifactu Pruebas** | Entorno sandbox AEAT para pruebas de envío | Configurado en `src/config/verifactu.php` |
| **BD de Prueba** | MySQL en puerto 3307, BD clonada de producción | `host=localhost, port=3307, dbname=Verifactu_test` |

---

## 6. Criterios de Aceptación

- **Pruebas unitarias:** 100% de casos pasan sin errores
- **Pruebas de integración:** Todos los endpoints devuelven estructura JSON correcta `{ ok, success, data }`
- **Cálculos fiscales:** Precisión de 2 decimales en todos los importes
- **Verifactu:** XML generado supera validación del esquema XSD de AEAT; firma digital verificable
- **PDF/Excel:** Documentos se generan sin errores en menos de 5 segundos
- **Email:** Factura recibida en bandeja en menos de 30 segundos en condiciones normales
- **UI:** No existen errores JavaScript en consola durante flujos principales
- **Regresión:** 0 fallos en casos críticos tras cada cambio

---

## 7. Criterios de Entrada y Salida

### Criterios de Entrada (para comenzar pruebas):
- Entorno local XAMPP en funcionamiento
- Base de datos de prueba inicializada con `database.sql`
- Dependencias Composer instaladas (`vendor/`)
- Certificado digital disponible para pruebas Verifactu

### Criterios de Salida (para dar pruebas por completadas):
- Todos los casos de prueba ejecutados y documentados
- Defectos críticos (bloquean flujo principal) resueltos
- Informe de resultados entregado

---

## 7.7. Despliegue y mantenimiento

### 7.7.1 Requisitos del entorno de producción

| Componente | Versión mínima | Notas |
|-----------|---------------|-------|
| **PHP** | 8.1 | Extensiones: `openssl`, `mbstring`, `xml`, `zip`, `gd`, `curl`, `intl` |
| **MySQL / MariaDB** | 5.7 / 10.4 | Motor InnoDB, charset `utf8mb4` |
| **Servidor web** | Apache 2.4 / Nginx 1.20 | `mod_rewrite` habilitado (Apache) |
| **Composer** | 2.x | Gestión de dependencias PHP |
| **Certificado SSL** | TLS 1.2+ | Obligatorio para envío HTTPS a AEAT |
| **Certificado digital** | FNMT / eIDAS | Para firma XAdES de XMLs Verifactu |
| **Espacio en disco** | ≥ 5 GB | PDFs, XMLs, logs, backups locales |
| **RAM** | ≥ 2 GB | Generación de documentos en lote |

---

### 7.7.2 Proceso de despliegue inicial

#### Paso 1 — Subir el código al servidor

```bash
# Clonar repositorio o subir los archivos por SFTP/SCP
git clone <repositorio> /var/www/html/SistemaGestionFacturas
# o copiar archivos via FTP/SFTP
```

#### Paso 2 — Instalar dependencias PHP

```bash
cd /var/www/html/SistemaGestionFacturas
composer install --no-dev --optimize-autoloader
```

Librerías gestionadas (`composer.json`):
- `phpmailer/phpmailer` — Envío de emails por SMTP
- `endroid/qr-code` — Generación de códigos QR en PDFs
- `tecnickcom/tcpdf` o `avadim/fastexcel` — PDF / Excel

#### Paso 3 — Crear y configurar la base de datos

```sql
-- Crear base de datos de producción
CREATE DATABASE SistemaFacturas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'factura_user'@'localhost' IDENTIFIED BY '<contraseña_segura>';
GRANT ALL PRIVILEGES ON SistemaFacturas.* TO 'factura_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
# Importar esquema y datos iniciales
mysql -u factura_user -p SistemaFacturas < database.sql
```

#### Paso 4 — Configurar los archivos de entorno

| Archivo | Variables clave a revisar |
|---------|--------------------------|
| `src/config/database.php` | `host`, `dbname`, `user`, `pass` |
| `src/config/empresa.php` | NIF empresa, razón social, dirección |
| `src/config/verifactu.php` | URL endpoint AEAT (producción vs. pruebas), ruta certificado |
| `src/config/app.php` | Decimales, separadores, zona horaria |

> **Importante:** En producción, la URL de Verifactu debe apuntar al endpoint real de la AEAT, no al sandbox.

#### Paso 5 — Configurar permisos de directorios

```bash
# Directorios que necesitan escritura por el servidor web
chmod -R 775 storage/pdfs/
chmod -R 775 storage/xmls/
chmod -R 775 storage/logs/
chown -R www-data:www-data storage/
```

#### Paso 6 — Configurar el servidor web (Apache)

```apache
<VirtualHost *:443>
    ServerName facturas.solventia.es
    DocumentRoot /var/www/html/SistemaGestionFacturas/public

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/solventia.crt
    SSLCertificateKeyFile /etc/ssl/private/solventia.key

    <Directory /var/www/html/SistemaGestionFacturas/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  /var/log/apache2/facturas_error.log
    CustomLog /var/log/apache2/facturas_access.log combined
</VirtualHost>
```

#### Paso 7 — Verificación post-despliegue

| Comprobación | Resultado esperado |
|-------------|-------------------|
| Acceso a la URL principal | Pantalla de login o dashboard |
| Crear factura de prueba | Factura guardada con número asignado |
| Generar PDF | PDF descargable sin errores |
| Enviar XML a AEAT (entorno pruebas primero) | Respuesta `200 OK` de AEAT |
| Enviar email con factura | Email recibido correctamente |
| Acceso HTTPS sin advertencias SSL | Certificado válido en el navegador |

---

### 7.7.3 Actualizaciones del sistema

#### Proceso de actualización de código

```bash
# 1. Hacer backup de BD antes de actualizar
mysqldump -u factura_user -p SistemaFacturas > backup_pre_update_$(date +%Y%m%d).sql

# 2. Activar modo mantenimiento (si existe página de mantenimiento)
touch maintenance.flag

# 3. Actualizar código
git pull origin main

# 4. Actualizar dependencias Composer
composer install --no-dev --optimize-autoloader

# 5. Ejecutar migraciones de BD si las hay
mysql -u factura_user -p SistemaFacturas < migrations/nueva_migracion.sql

# 6. Limpiar caché (si aplica)
php artisan cache:clear  # o método equivalente del proyecto

# 7. Desactivar modo mantenimiento
rm maintenance.flag
```

#### Actualización de la normativa Verifactu

Cuando la AEAT publique nuevas versiones del esquema XSD:
1. Descargar el nuevo XSD desde el portal de la AEAT.
2. Actualizar el archivo en `src/libs/xsd/` o la ruta configurada en `VerifactuWrapper.php`.
3. Ejecutar los casos de prueba CP-VER-01 a CP-VER-05 para validar conformidad.
4. Actualizar la versión del esquema en `src/config/verifactu.php`.

#### Renovación del certificado digital (firma XAdES)

| Acción | Cuándo | Responsable |
|--------|--------|-------------|
| Revisar caducidad del certificado FNMT | Mensualmente | Administrador sistema |
| Solicitar renovación | 2 meses antes de caducidad | Administrador / Empresa |
| Sustituir `.p12` / `.pfx` en servidor | Tras renovación | Administrador sistema |
| Verificar firma en XML de prueba | Inmediatamente tras sustitución | Desarrollador |

---

### 7.7.4 Copias de seguridad

#### Política de backups

| Tipo | Frecuencia | Retención | Destino |
|------|-----------|-----------|---------|
| **BD completa** | Diaria (02:00h) | 30 días | Disco externo / NAS |
| **BD incremental** | Cada 6h | 7 días | Servidor secundario |
| **Archivos (PDFs, XMLs)** | Semanal | 90 días | NAS / almacenamiento cloud |
| **Configuración** | Tras cada cambio | Indefinido | Repositorio Git |

#### Script de backup automatizado

```bash
#!/bin/bash
# /usr/local/bin/backup_facturas.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/backups/facturas
DB_NAME=SistemaFacturas
DB_USER=factura_user

mkdir -p $BACKUP_DIR

# Backup BD
mysqldump -u $DB_USER -p"$DB_PASS" $DB_NAME | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Backup archivos generados
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" /var/www/html/SistemaGestionFacturas/storage/

# Eliminar backups de BD con más de 30 días
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete

echo "Backup completado: $DATE"
```

Añadir al cron del servidor:
```
0 2 * * * /usr/local/bin/backup_facturas.sh >> /var/log/backup_facturas.log 2>&1
```

---

### 7.7.5 Monitorización y logs

#### Archivos de log del sistema

| Log | Ruta | Contenido |
|-----|------|-----------|
| Errores PHP | `storage/logs/php_errors.log` | Excepciones, errores fatales |
| Envíos Verifactu | `storage/logs/verifactu.log` | Peticiones/respuestas AEAT, reintentos |
| Emails enviados | `storage/logs/email.log` | Destinatario, asunto, resultado |
| Accesos Apache | `/var/log/apache2/facturas_access.log` | Peticiones HTTP |
| Errores Apache | `/var/log/apache2/facturas_error.log` | Errores del servidor web |

#### Alertas recomendadas

| Alerta | Condición | Acción |
|--------|-----------|--------|
| Error envío AEAT repetido | Más de 3 fallos en 1h | Notificar por email al administrador |
| Certificado digital próximo a caducar | < 60 días | Alerta automática por email |
| Espacio en disco bajo | < 500 MB libres | Revisar y limpiar archivos temporales |
| Error BD | Conexión fallida | Notificación inmediata |

---

### 7.7.6 Procedimientos ante incidencias

#### Incidencia: fallo en envío a AEAT

1. Revisar `storage/logs/verifactu.log` para identificar el código de error devuelto por la AEAT.
2. Verificar conectividad al endpoint: `curl -I https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturae/VerifactuSoap`
3. Comprobar vigencia del certificado digital.
4. Si el error es de esquema XML, revisar la versión del XSD con la publicada en el portal AEAT.
5. Las facturas en estado `error` en el listado Verifactu pueden reenviarse desde la vista `src/views/Verifactu/listado.php`.

#### Incidencia: base de datos inaccesible

1. Verificar que el servicio MySQL está activo: `systemctl status mysql`
2. Revisar espacio en disco: `df -h`
3. Consultar log MySQL: `/var/log/mysql/error.log`
4. Restaurar desde último backup si la BD está corrupta.

#### Incidencia: PDFs o XMLs no se generan

1. Verificar permisos de escritura en `storage/pdfs/` y `storage/xmls/`.
2. Comprobar que las extensiones PHP `gd`, `zip` y `openssl` están habilitadas: `php -m | grep -E 'gd|zip|openssl'`
3. Revisar `storage/logs/php_errors.log` para la traza del error.

---

## 8. Gestión de Defectos

| Severidad | Descripción | Tiempo de Resolución |
|-----------|------------|---------------------|
| **Crítica** | Impide crear/enviar facturas | Inmediato |
| **Alta** | Error en cálculo fiscal o Verifactu | 24h |
| **Media** | Fallo en generación PDF/Excel | 48-72h |
| **Baja** | Problema visual o de usabilidad | Próxima iteración |
