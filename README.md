# Sistema de Gestión de Facturas — integración VeriFactu / AEAT

Aplicación web de facturación para España con envío directo de los registros de facturación a la AEAT mediante **VeriFactu** (RD 1007/2023). Proyecto final del ciclo de Desarrollo de Aplicaciones Web, con una versión posterior ampliada y dockerizada.

Se levanta con una sola orden y toda la configuración vive en un `.env`, pensada para poder entregarse a un cliente final.

---

## Qué hace

**Facturación**
- Facturas completas, simplificadas, rectificativas y recapitulativas.
- Numeración correlativa por canal y ejercicio, con bloqueo para evitar huecos en la serie.
- Bases, IVA, recargo de equivalencia y tres tipos de descuento.
- Cobros, pagos, abonos y cierre de facturas.

**Albaranes**
- CRUD completo, plantillas reutilizables y conversión de uno o varios albaranes a factura.

**VeriFactu / AEAT**
- Genera el registro de facturación, calcula la huella SHA-256 encadenada con la factura anterior, firma el XML con certificado y lo envía por SOAP a la AEAT.
- **Modo sin conexión:** si no hay internet, la factura queda en cola como pendiente y un script la envía después (pensado para cron o el Programador de tareas).
- La cola valida el encadenamiento antes de cada envío y se detiene al primer fallo, para no corromper la cadena de huellas.
- Gestión de rechazos: reintentos, anulaciones, descarga del XML enviado y de la respuesta de Hacienda.

**Documentos**
- PDF generados desde plantillas `.docx` con PHPWord y convertidos con Gotenberg. Si Gotenberg no responde, entrega el `.docx` en su lugar.
- QR de verificación VeriFactu embebido en la factura.
- Envío por email y exportación a Excel.

**Maestros**
Clientes (con varias direcciones y teléfonos, y validación de NIF), artículos, familias, tarifas, tipos de IVA, formas de pago, canales, países y usuarios.

---

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.2+ sin framework, con MVC propio (`api/` → `controllers/` → `services/` → `models/`) |
| Base de datos | MariaDB 11, acceso con PDO |
| Frontend | HTML, CSS y JavaScript sin build, consumiendo la API con `fetch` (+ SweetAlert) |
| Infraestructura | Docker Compose: Apache + PHP, MariaDB y Gotenberg |
| Librerías | josemmo/verifactu-php, PHPWord, endroid/qr-code, PHPMailer, fast-excel-writer |

---

## Seguridad

- Protección CSRF y gestión de sesiones.
- Validación centralizada de entrada.
- Consultas preparadas (PDO) en todo el acceso a datos.
- Firma XML con certificado y cadena de huellas SHA-256 entre facturas consecutivas.
- `.htaccess` que bloquea el acceso a `vendor/`, `src/config/` y `src/core/`, y añade cabeceras de seguridad.
- Logging separado por servicio.
- Certificados, `.env` y logs quedan fuera del repositorio.

---

## Puesta en marcha

Requisitos: **Docker Desktop** instalado y arrancado.

```bash
git clone https://github.com/DevLucasMartin/facturacion-verifactu.git
cd facturacion-verifactu
copy .env.example .env        # y edita los valores
docker compose up -d --build
```

Abrir **http://localhost:8080/SistemaGestionFacturas/**

La base de datos se inicializa automáticamente la primera vez con `database-clean.sql`: catálogos esenciales y ningún dato de prueba, porque mezclar registros de prueba con los de un cliente real rompería la cadena de huellas de VeriFactu. Para probar con datos de ejemplo, usa `database.sql`.

Las credenciales iniciales y el resto de comandos están en la guía de instalación. **Cámbialas antes de cualquier uso real.**

📄 **[Guía completa de instalación y operación](docs/INSTALACION-DOCKER.md)** — alta de clientes, comandos útiles, copias de seguridad y resolución de problemas.

También hay instalación alternativa sobre XAMPP en `REQUISITOS.md`, y la guía de entrega a cliente en `INSTALACION-CLIENTE.md`.

---

## Autoría

Proyecto académico desarrollado por **Lucas Martín García**, **Ilias Tighadouini** y **Hugo Alelú** (IES Ciudad Escolar, curso 2025/26).

Mi parte en el proyecto:

- **Integración VeriFactu / AEAT completa**: construcción del registro de facturación, huella SHA-256 encadenada, firma con certificado, envío SOAP, cola sin conexión y gestión de rechazos.
- **Seguridad transversal**: CSRF, sesiones, validación centralizada, consultas preparadas y cabeceras.
- **Núcleo de facturación**: tipos de factura, numeración correlativa, validación de NIF y códigos de territorio.
- **Dockerización con Docker Compose**, posterior a la entrega del proyecto.

El historial de commits refleja el reparto real del trabajo.h

---

## Aviso

Los datos de `database.sql` son ficticios y sirven solo para pruebas. La aplicación se ha probado contra el entorno de pruebas de la AEAT, no contra producción.
