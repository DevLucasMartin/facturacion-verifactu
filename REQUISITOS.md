# Requisitos técnicos — Sistema de Gestión de Facturas

Sistema de facturación en **PHP** con integración **Verifactu / AEAT**, generación de
PDF, QR, envío de emails y exportación a Excel.

---

## 1. Entorno de servidor

| Componente | Requisito | Notas |
|---|---|---|
| **PHP** | **≥ 8.2** | Lo exige `josemmo/verifactu-php`. El código usa tipos `int\|string`, propiedades tipadas, `match`, `mixed`. |
| **Apache** | Con `mod_rewrite` | Hay `.htaccess` en la raíz y en cada carpeta de `src/`. Incluido en **XAMPP**. |
| **Composer** | Para instalar dependencias | Ver `composer.json`. |

## 2. Base de datos

- **MySQL / MariaDB** (el incluido en XAMPP).
- **Puerto:** el que defina la instalación. El estándar de XAMPP es el **3306**. El puerto debe **coincidir con el que está escrito en `src/config/database.php`**. En el equipo de desarrollo actual está configurado en **3307**, pero eso es local: en otra máquina puede ser 3306 (o cualquier otro), basta con ajustar ese valor en `database.php`.
- Base de datos **`verifactu`**, charset **`utf8mb4`** / `utf8mb4_unicode_ci`. Se crea con `database.sql`.
- Conexión por **PDO MySQL**, usuario `root` sin contraseña (configuración XAMPP local).

## 3. Extensiones de PHP necesarias

| Extensión | Para qué |
|---|---|
| `pdo_mysql` | Conexión a la base de datos |
| `openssl` | Firma digital de los registros Verifactu (`FirmaDigitalService`) |
| `curl` | Comunicación SOAP con la AEAT (Guzzle) y llamadas a Gotenberg |
| `gd` | Generación del código QR (`endroid/qr-code`) |
| `mbstring`, `ctype`, `dom`/`xml`/`simplexml` | Manejo de XML SOAP y polyfills de Symfony |
| `zip` | Generación de DOCX (`phpoffice/phpword`) y Excel (`fast-excel-writer`) |

## 4. Dependencias Composer

| Paquete | Para qué |
|---|---|
| `josemmo/verifactu-php ^0.3.4` | Integración Verifactu / AEAT (arrastra `guzzlehttp/guzzle` y `symfony/validator`) |
| `phpoffice/phpword ^1.1` | Generación de documentos Word (`.docx`) |
| `endroid/qr-code ^6.0` | Códigos QR |
| `phpmailer/phpmailer ^7.0` | Envío de emails |
| `avadim/fast-excel-writer ^6.12` | Exportación a Excel |

### 4.1. Instalar Composer (gestor de dependencias) — por comandos

Composer es la herramienta que descarga e instala todas las librerías de la tabla anterior.
Hay dos formas por línea de comandos; usa la que prefieras.

**Opción A — con `winget`** (gestor de paquetes incluido en Windows 11). En PowerShell:

```powershell
winget install --id Composer.Composer -e --accept-source-agreements --accept-package-agreements
```

**Opción B — con el instalador oficial de PHP** (deja un `composer.phar` en el proyecto).
Asegúrate de tener el PHP de XAMPP en el `PATH` o usa la ruta completa `C:\xampp\php\php.exe`:

```powershell
cd C:\xampp\htdocs\SistemaGestionFacturas
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

Comprueba que quedó instalado (cierra y reabre la terminal tras instalar):

```powershell
composer --version
```

> Con la **Opción B** el comando es `php composer.phar ...` en lugar de `composer ...`
> (a no ser que muevas el `composer.phar` a una carpeta del `PATH`).
> Si `composer` no se reconoce, reinicia la terminal o la sesión de Windows para aplicar el `PATH`.

### 4.2. Instalar las librerías del proyecto

Con Composer ya instalado, sitúate en la carpeta del proyecto e instala las dependencias.
Esto lee `composer.json` y descarga todo dentro de la carpeta `vendor/`.

```powershell
cd C:\xampp\htdocs\SistemaGestionFacturas
composer install
```

- Si vas a **actualizar** a versiones nuevas (respetando los límites de `composer.json`):
  ```powershell
  composer update
  ```
- Para añadir una librería nueva en el futuro, por ejemplo:
  ```powershell
  composer require proveedor/paquete
  ```

> Al terminar debe existir la carpeta **`vendor/`** con un `autoload.php` dentro. Ese
> `autoload.php` es el que cargan los servicios del proyecto (`require_once .../vendor/autoload.php`).

## 5. Generación de PDF — Gotenberg (LibreOffice)

El PDF **no** se genera dentro de PHP. El flujo (`src/services/PdfService.php`) es:

1. `phpoffice/phpword` rellena la plantilla `.docx`.
2. El `.docx` se envía por HTTP a **Gotenberg**, que usa **LibreOffice** para convertirlo a PDF.

- Gotenberg corre como **servicio Docker**, por defecto en **`http://localhost:3000`**.
- Configurable con la variable de entorno **`GOTENBERG_URL`**.
- Endpoint usado: **`/forms/libreoffice/convert`**.
- **Si Gotenberg no está disponible**, el sistema no falla: entrega el documento en **Word (.docx)**. Por tanto Gotenberg es necesario para obtener PDF, pero degrada a DOCX si está apagado.

### 5.1. Instalar Docker y Gotenberg — por comandos

Gotenberg se distribuye como **imagen de Docker**, así que primero hace falta Docker.

1. **Instala Docker Desktop por comandos** con `winget` (en PowerShell):
   ```powershell
   winget install --id Docker.DockerDesktop -e --accept-source-agreements --accept-package-agreements
   ```
2. **Arranca el motor de Docker** (la primera vez conviene lanzarlo y esperar a que quede listo):
   ```powershell
   Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"
   ```
   Verifica que el motor responde:
   ```powershell
   docker version
   ```
3. **Descarga la imagen** de Gotenberg (solo la primera vez):
   ```powershell
   docker pull gotenberg/gotenberg:8
   ```

> La primera instalación de Docker puede pedir **reiniciar** Windows y activar **WSL2**.
> Si `wsl` no estuviera instalado, se activa también por comando: `wsl --install`.

### 5.2. Levantar Gotenberg

- **Prueba rápida** (se cierra al cerrar la terminal):
  ```powershell
  docker run --rm -p 3000:3000 gotenberg/gotenberg:8
  ```
- **Uso normal** (en segundo plano y que vuelva a arrancar solo — ver también la sección 9):
  ```powershell
  docker run -d --name gotenberg --restart unless-stopped -p 3000:3000 gotenberg/gotenberg:8
  ```
- **Comprobar que responde:**
  ```powershell
  curl http://localhost:3000/health
  ```

> Si el puerto **3000** está ocupado, publica otro (p. ej. `-p 3001:3000`) y apunta la
> aplicación a él con la variable de entorno `GOTENBERG_URL=http://localhost:3001`.

## 6. Servicios externos / configuración

- **Certificado digital** (`.p12`/`.pfx` + contraseña) válido para firmar y comunicar con la AEAT.
- **Cuenta SMTP** para PHPMailer. Se configura en `src/config/empresa.php` (clave `smtp`) o por variables de entorno: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `FROM_EMAIL`, `FROM_NAME`. Por defecto `smtp.gmail.com:587` con TLS.
- **Conectividad a internet** hacia los endpoints SOAP de la AEAT.
- Permisos de **escritura** en `src/storage/logs/` y `src/storage/pdf/`.

## 7. Frontend

- Sin framework ni build: **HTML/CSS/JavaScript vanilla** (`assets/js/`) que consume la API PHP.
- Usa **SweetAlert** para los avisos. Solo necesita un navegador moderno.

---

## 8. Resumen de instalación

1. Instalar **XAMPP** (Apache + MySQL/MariaDB + PHP 8.2+) y clonar el proyecto en `htdocs`.
2. Asegurarte de que el **puerto de MySQL coincide con el de `src/config/database.php`** (estándar XAMPP: 3306) e importar `database.sql`.
3. Habilitar las extensiones PHP de la sección 3 en `php.ini`.
4. Instalar **Composer** (sección 4.1) y ejecutar **`composer install`** (sección 4.2) para descargar las librerías a `vendor/`.
5. Instalar **Docker Desktop**, descargar la imagen y **levantar Gotenberg** (secciones 5.1 y 5.2).
6. Instalar el **certificado digital** y configurar la cuenta **SMTP**.

---

## 9. Arranque automático en segundo plano (Windows 11)

Objetivo: que **XAMPP (Apache + MySQL)** y **Gotenberg** queden levantados solos al
encender el ordenador, sin tener que abrir ninguna ventana.

### 9.1. XAMPP como servicios de Windows

Al instalarlos como servicios, Apache y MySQL arrancan en segundo plano en el inicio
de Windows (antes incluso de iniciar sesión).

1. Abre el **XAMPP Control Panel como Administrador** (clic derecho → *Ejecutar como administrador*).
2. Marca la casilla **"Service"** (la cruz/check de la columna izquierda) de **Apache** y de **MySQL**. Eso los instala como servicios de Windows con arranque automático.
   - Alternativa por consola (XAMPP en `C:\xampp`), en una terminal **como Administrador**:
     ```powershell
     C:\xampp\apache\apache_installservice.bat
     C:\xampp\mysql\mysql_installservice.bat
     ```
3. Asegúrate de que los servicios están en **arranque automático**:
   ```powershell
   Set-Service -Name Apache2.4 -StartupType Automatic
   Set-Service -Name mysql     -StartupType Automatic
   ```
   > Los nombres pueden ser `Apache2.4` y `mysql`. Compruébalos con `Get-Service *apache*,*mysql*`.

> **Importante (puerto de MySQL):** el puerto del servicio debe coincidir con el de
> `src/config/database.php`. Si usas un puerto distinto al estándar (3306), edita
> `C:\xampp\mysql\bin\my.ini` y pon ese `port=` en las secciones `[client]` y `[mysqld]`
> **antes** de instalar el servicio. (En el equipo de desarrollo actual es 3307.)

### 9.2. Gotenberg (Docker) en segundo plano

Gotenberg corre en Docker, así que hay que conseguir dos cosas: **(a)** que Docker
arranque al iniciar el equipo y **(b)** que el contenedor se levante solo y se reinicie.

1. **Instala Docker Desktop** y déjalo configurado para arrancar al iniciar sesión:
   *Docker Desktop → Settings → General → marcar "Start Docker Desktop when you sign in"*.

2. **Crea el contenedor con reinicio automático** (una sola vez). Usa
   `--restart unless-stopped`: Docker lo volverá a levantar cada vez que arranque el motor.
   ```powershell
   docker run -d --name gotenberg --restart unless-stopped -p 3000:3000 gotenberg/gotenberg:8
   ```
   A partir de aquí, siempre que Docker Desktop esté en marcha, Gotenberg estará escuchando
   en `http://localhost:3000` sin abrir ninguna ventana.

3. **Comprobar que responde:**
   ```powershell
   curl http://localhost:3000/health
   ```

> **Opción "todo en segundo plano de verdad" (sin iniciar sesión):**
> *Docker Desktop* solo arranca al **iniciar sesión** un usuario, no en el arranque puro de
> Windows. Si necesitas que funcione aunque nadie inicie sesión (p. ej. un servidor),
> las opciones son:
> - Activar el inicio de sesión automático de Windows del usuario que tiene Docker, **o**
> - Usar **Docker Engine sin Docker Desktop** (vía WSL2) y lanzar el contenedor mediante una
>   **Tarea Programada** de Windows con el disparador *"Al iniciar el equipo"*.

### 9.3. Verificación tras reiniciar

Después de reiniciar el ordenador, sin abrir nada manualmente, comprueba:

```powershell
Get-Service *apache*, *mysql*          # deben aparecer como "Running"
docker ps                              # el contenedor "gotenberg" debe estar "Up"
curl http://localhost:3000/health      # Gotenberg responde
```

Si los tres responden, la aplicación funcionará (con PDF incluido) sin intervención manual
al encender el equipo.
