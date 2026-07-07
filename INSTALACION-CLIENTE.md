# Guía de instalación en un cliente nuevo

Esta guía explica cómo instalar el **Sistema de Gestión de Facturas** en el
ordenador de un cliente. Todo el sistema corre en Docker (3 contenedores), así
que **no hay que instalar XAMPP, PHP ni MySQL a mano**.

La idea clave: para un cliente nuevo **solo se edita el archivo `.env` y se copia
su certificado**. No se toca código.

---

## 0. Los 6 pasos de la instalación

Esta es la instalación completa en 6 pasos. Sigue el orden. Cada paso está
explicado con detalle más abajo en el documento (secciones 1 a 6).

1. **Instalar Docker Desktop.** Descargarlo, instalarlo y activar que arranque
   con Windows. → *Sección 1*
2. **Copiar el proyecto al ordenador del cliente** (la carpeta completa, a donde
   quieras). → *Sección 2*
3. **Copiar el certificado del cliente** (el archivo `.p12`) en la carpeta
   `src/storage/certs/`. → *Sección 3*
4. **Crear el archivo `.env`**: copiar `.env.example`, rellenar los datos del
   cliente y guardarlo con el nombre `.env`. → *Sección 4*
5. **Arrancar el sistema** desde la terminal, en la carpeta del proyecto:
   ```
   docker compose up -d --build
   ```
   Y comprobar que los 3 servicios están arriba:
   ```
   docker compose ps
   ```
   → *Sección 5*
6. **Comprobar que funciona** abriendo el navegador en:
   **http://localhost:8080/SistemaGestionFacturas/**
   Entrar con `admin` / `Admin1` y cambiar la contraseña. → *Sección 6*

> Si es la primera vez que lo haces, no te saltes las explicaciones de cada
> sección: cubren los detalles importantes (sobre todo el paso 4, el `.env`).

---

## 1. Requisitos previos

- **Windows 10/11** con permisos de administrador.
- **Docker Desktop** instalado:
  - Descarga: https://www.docker.com/products/docker-desktop/
  - Durante la instalación, dejar marcado **"Use WSL 2 instead of Hyper-V"**.
  - Tras instalar, abrir Docker Desktop y esperar a que la **ballena** 🐳 de la
    bandeja deje de animarse (engine arrancado).
- Activar el arranque automático: en Docker Desktop → **Settings (⚙️) → General →
  "Start Docker Desktop when you sign in"**.

> Con esto + el `restart: unless-stopped` que ya trae el `docker-compose.yml`, los
> contenedores se levantan solos cada vez que se enciende el PC (hay ~10-30 s hasta
> que todo responde).

---

## 2. Copiar el proyecto

Copiar la carpeta completa del proyecto al PC del cliente (por ejemplo a
`C:\SistemaGestionFacturas\`). Da igual la ruta de la carpeta en el host; dentro
del contenedor siempre se monta en la ruta correcta.

> El proyecto ya incluye la carpeta `vendor/` (dependencias PHP), así que no hace
> falta ejecutar `composer install`.

---

## 3. Certificado digital del cliente

El certificado es lo que identifica al cliente ante la AEAT.

1. **Borrar** el certificado de pruebas que viene de ejemplo:
   `src/storage/certs/SDC_1_2_21_A_ALELU_MUNOZ_HUGO___51224383W.p12`
2. **Copiar** el `.p12` (o `.pfx`) del cliente en `src/storage/certs/`.
3. Apuntar su nombre exacto y la contraseña → irán en el `.env` (paso 4).

> ⚠️ El certificado **no se versiona en git** (lo protege `.gitignore`). Es un dato
> sensible: trátalo con cuidado.

---

## 4. Configurar el `.env` (datos del cliente)

### 4.0. Cómo crear y editar el archivo

1. **Crear el `.env`** (solo la primera vez) copiando la plantilla. Desde la
   carpeta del proyecto:
   ```
   copy .env.example .env
   ```
   > No edites `.env.example`: es la plantilla para futuros clientes. El que se
   > rellena es siempre `.env`.

2. **Abrirlo** con cualquier editor de texto: VS Code, o el Bloc de notas (clic
   derecho sobre `.env` → Abrir con → Bloc de notas), o `notepad .env`.
   > ⚠️ Si guardas con el Bloc de notas, el nombre debe quedar **`.env`** y no
   > `.env.txt`. En "Guardar como" elige tipo **"Todos los archivos"**.

3. **Rellenar** cada línea con el formato `CLAVE=valor`: se escribe solo después
   del `=`, sin espacios alrededor ni comillas. Ejemplo:
   ```
   EMPRESA_RAZON_SOCIAL=Talleres García S.L.
   EMPRESA_NIF=B12345678
   ```

Campos que **se dejan como vienen** (dirección interna entre contenedores):
`DB_HOST=db`, `DB_PORT=3306`, `DB_NAME=verifactu`, `DB_USER=verifactu`,
`GOTENBERG_URL` y `APP_PORT`. El resto se rellena con datos del cliente, por
secciones:

### 4.1. Base de datos
Poner contraseñas propias para este cliente:
```
DB_PASSWORD=una_password_segura
DB_ROOT_PASSWORD=otra_password_segura_root
```
El resto (`DB_HOST=db`, `DB_PORT=3306`, `DB_NAME=verifactu`, `DB_USER=verifactu`)
se deja como está: es la dirección interna entre contenedores.

### 4.2. Datos de la empresa (salen impresos en las facturas)
```
EMPRESA_RAZON_SOCIAL=...
EMPRESA_NIF=...
EMPRESA_TELEFONO=...
EMPRESA_EMAIL=...
EMPRESA_WEB=...
EMPRESA_DATOS_BANCARIOS=IBAN: ...  BIC: ...
EMPRESA_DIRECCION=...
EMPRESA_CODIGO_POSTAL=...
EMPRESA_POBLACION=...
EMPRESA_PROVINCIA=...
```

> ⚠️ **CRÍTICO:** `EMPRESA_NIF` y `EMPRESA_RAZON_SOCIAL` deben **coincidir con el
> titular del certificado digital**. Si no coinciden, la AEAT rechaza TODAS las
> facturas.

> 💡 **Si el cliente es autónomo:** en `EMPRESA_NIF` va su **DNI** (8 dígitos +
> letra). Fiscalmente el NIF de un autónomo es su propio DNI, y el sistema lo
> acepta como válido. En `EMPRESA_RAZON_SOCIAL` se pone su nombre completo.

> `EMPRESA_DATOS_BANCARIOS` **no es obligatorio**: si se rellena (IBAN/BIC),
> aparece impreso en el PDF de la factura; si se deja en blanco, simplemente no
> se muestra esa sección. La AEAT no lo exige.

### 4.3. SMTP (envío de facturas por email)
```
SMTP_HOST=...
SMTP_PORT=587
SMTP_USERNAME=...
SMTP_PASSWORD=...
SMTP_SECURE=tls
SMTP_FROM_EMAIL=...
SMTP_FROM_NAME=...
```

> Si el cliente usa **Gmail**, `SMTP_PASSWORD` es una **"contraseña de aplicación"**
> (se genera en la cuenta de Google), no la contraseña normal del correo.

### 4.4. Verifactu / AEAT
```
VERIFACTU_ENTORNO=produccion
VERIFACTU_CERT_ARCHIVO=nombre_exacto_del_certificado_del_cliente.p12
VERIFACTU_CERT_PASSWORD=la_contraseña_del_certificado
VERIFACTU_AUTO_ENVIO=false
```

- `VERIFACTU_ENTORNO=produccion` → para facturar de verdad ante la AEAT.
  (Déjalo en `pruebas` solo si quieres hacer una prueba previa contra el entorno
  de preproducción de la AEAT.)
- `VERIFACTU_CERT_ARCHIVO` → el nombre **exacto** del `.p12` que copiaste en el
  paso 3.

> ❗ No pongas comentarios al final de una línea de valor en el `.env`
> (`CLAVE=valor # comentario`). Docker se traga el comentario dentro del valor.
> Los comentarios van en su propia línea empezando por `#`.

### 4.5. Aplicar cambios en el `.env`

El `.env` se lee **al arrancar los contenedores**, no en caliente. Si editas el
`.env` cuando el sistema ya está corriendo, hay que recrear los contenedores para
que tome los nuevos valores (desde la carpeta del proyecto):

```
docker compose up -d
```

(sin `--build`; Docker detecta el `.env` cambiado y recrea lo necesario).

---

## 5. Arrancar el sistema

Desde la carpeta del proyecto:

```
docker compose up -d --build
```

La primera vez tarda unos minutos (descarga imágenes y construye la de PHP). La
base de datos se inicializa **automáticamente** con `database-clean.sql`:
catálogos esenciales (IVA, formas de pago, etc.) y **sin datos de prueba**.
`Verifactu_Registros` queda vacía para que la cadena de huellas del cliente
empiece limpia.

Comprobar que los 3 contenedores están arriba:
```
docker compose ps
```
Deberían aparecer `web`, `db` (healthy) y `gotenberg` como "Up".

---

## 6. Primer acceso

1. Abrir en el navegador: **http://localhost:8080/SistemaGestionFacturas/**
2. Entrar con el usuario inicial:
   - Usuario: `admin`
   - Contraseña: `Admin1`
3. **Cambiar inmediatamente la contraseña de `admin`** (Gestión → Usuarios).
4. Crear los usuarios reales del cliente.

---

## 7. Verificación post-instalación (recomendado)

Antes de dar por buena la instalación, hacer una prueba real:

1. Crear un **cliente** de prueba (con NIF válido).
2. Crear una **factura** con ese cliente.
3. Comprobar que se **envía a la AEAT** (estado "Enviado" + CSV asignado +
   enlace "Ver en AEAT").
4. Descargar el **PDF** de la factura (verifica que Gotenberg funciona).
5. Si todo va bien, borrar esa factura/cliente de prueba **solo si aún no se ha
   enviado a la AEAT real** (ver aviso en la sección 9).

---

## 8. Arranque automático

Ya queda configurado si en el paso 1 activaste "Start Docker Desktop when you
sign in". Al encender el PC:
1. Windows arranca Docker Desktop.
2. Docker levanta los 3 contenedores (`restart: unless-stopped`).
3. A los ~10-30 s la app responde en http://localhost:8080/SistemaGestionFacturas/

---

## 9. Avisos importantes sobre Verifactu

- **Nunca reinicies la base de datos (`docker compose down -v`) después de haber
  enviado facturas reales a la AEAT.** Verifactu encadena cada factura con la
  huella de la anterior y la numeración no debe reutilizarse. Si reinicias, los
  números de factura colisionarían con los ya registrados en la AEAT (error 3000
  "registro duplicado").
- El entorno de **producción** (`VERIFACTU_ENTORNO=produccion`) envía facturas
  fiscales reales. Asegúrate de que el certificado, el NIF y la razón social son
  los correctos antes de empezar a facturar.

---

## 10. Operación y mantenimiento

Todos los comandos se ejecutan desde la carpeta del proyecto.

| Acción | Comando |
|---|---|
| Ver estado de los servicios | `docker compose ps` |
| Ver logs de la app en vivo | `docker compose logs -f web` |
| Parar (conservando los datos) | `docker compose down` |
| Arrancar | `docker compose up -d` |
| Reiniciar tras cambiar el `.env` | `docker compose up -d` (recrea los contenedores) |

### Logs de la aplicación
Los errores quedan registrados en `src/storage/logs/` (un archivo por servicio:
`verifactu.log`, `email.log`, `pdf.log`, `database.log`...). Ver el README para
el detalle.

### Copia de seguridad de la base de datos
Hacer backups periódicos (la BD contiene las facturas y la cadena Verifactu):

```
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" verifactu' > backup_verifactu.sql
```
(la contraseña se toma sola de dentro del contenedor; es la `DB_ROOT_PASSWORD` del `.env`).

Restaurar un backup:
```
docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" verifactu' < backup_verifactu.sql
```

> Los datos de la BD viven en el volumen Docker `db_data` y **persisten** aunque
> pares los contenedores. Solo se borran con `docker compose down -v`.

---

## 11. Checklist de entrega

- [ ] Docker Desktop instalado y configurado para arrancar con Windows.
- [ ] Proyecto copiado al PC del cliente.
- [ ] Certificado `.p12` del cliente copiado en `src/storage/certs/` (y borrado el de pruebas).
- [ ] `.env` creado y **todos** los campos rellenados con datos del cliente.
- [ ] `EMPRESA_NIF` / `EMPRESA_RAZON_SOCIAL` coinciden con el titular del certificado.
- [ ] `VERIFACTU_ENTORNO=produccion`.
- [ ] Contraseñas de BD cambiadas (`DB_PASSWORD`, `DB_ROOT_PASSWORD`).
- [ ] SMTP del cliente configurado y probado.
- [ ] `docker compose up -d --build` ejecutado; 3 contenedores "Up".
- [ ] Login OK y contraseña de `admin` cambiada.
- [ ] Prueba de factura → enviada a la AEAT + PDF descargado correctamente.
- [ ] Backup inicial de la BD realizado.
