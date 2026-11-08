# Proyecto AEMET — Extracción de datos meteorológicos a MySQL

**Autor:** Jaime Fano Alijarde ([jaimeefano@gmail.com](mailto:jaimeefano@gmail.com)) — Universidad de Zaragoza  
**Fecha de creación:** Octubre 2026  
**Asignatura:** Sistemas de Información · Práctica de investigación 

---

## Descripción

Script en **Node.js** que consume la API REST de [AEMET OpenData](https://opendata.aemet.es) para obtener valores climatológicos diarios de 5 ciudades españolas y los almacena en una base de datos **MySQL**.

Este proyecto es la **fase 1** de una práctica en dos partes; la fase 2 construirá una aplicación web sobre estos mismos datos.

## Ciudades monitorizadas

| Ciudad | Indicativo AEMET |
|---|---|
| Madrid | 3195 |
| Barcelona | 0201D |
| Valencia | 8416 |
| Zaragoza | 9434 |
| Teruel | 8368U |

## Datos recogidos por ciudad y día

Temperatura máxima, mínima y media · Precipitación · Velocidad media, racha máxima y dirección del viento · Humedad relativa media, máxima y mínima · Horas de sol · Presión máxima y mínima. Además se guarda la altitud de cada estación.

> No todas las estaciones miden todas las variables (p. ej. Madrid y Barcelona no informan de horas de sol, y Barcelona tampoco de presión), por lo que esas columnas pueden ser `NULL`. AEMET publica los valores diarios con unos días de retraso.

## Estructura del repositorio

```
├── docker-compose.yml       # MySQL 8.4, phpMyAdmin, web y ETL (diario) en Docker
├── .env.example             # Variables de entorno necesarias (copiar a .env y ajustar)
├── database/
│   ├── schema.sql           # Esquema de la base de datos (ciudades, lecturas_diarias, usuarios y recuperaciones_password)
│   ├── migraciones/         # Cambios de esquema para bases de datos ya creadas
│   └── datos_aemet.sql      # Volcado completo (estructura + datos ya cargados) para importar tal cual
├── etl/                     # Carga de datos (Node.js)
│   ├── Dockerfile           # Imagen del ETL
│   ├── index.js             # Punto de entrada: carga .env, define rango de fechas y arranca el ETL
│   ├── scheduler.js         # Programador: lanza el ETL al arrancar y cada día a la hora configurada
│   ├── package.json         # Dependencias Node.js (mysql2, dotenv)
│   └── src/
│       ├── pool.js          # Pool de conexiones MySQL reutilizable
│       ├── aemet.js         # Cliente HTTP para la API REST de AEMET OpenData
│       ├── parser.js        # Transforma la respuesta cruda de AEMET al formato del schema
│       ├── etl.js           # Orquesta la extracción, transformación e inserción de datos
│       ├── rangos.js        # Divide el periodo en tramos que respetan el límite de AEMET
│       └── programador.js   # Calcula la próxima ejecución diaria (lo usa scheduler.js)
├── web/                     # Aplicación web en PHP (proyecto Climate Change Analyzer)
│   ├── Dockerfile           # Apache + PHP 8.3 con PDO MySQL
│   ├── public/              # Raíz del servidor web (index.php, assets)
│   └── app/                 # config, helpers, models, controllers, views (.html sin PHP)
└── docs/
    └── practica-investigacion/   # Enunciado y notas de la práctica de investigación
```

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Node.js 20+
- API key de AEMET OpenData (solicitar en [opendata.aemet.es](https://opendata.aemet.es))

## Puesta en marcha

```bash
# 1. Clonar el repositorio
git clone https://github.com/jaimefano/Proyecto_AEMET.git
cd Proyecto_AEMET

# 2. Configurar variables de entorno
cp .env.example .env
# Editar .env con tus contraseñas y API key

# 3. Levantar la base de datos
docker compose up -d
# MySQL disponible en localhost:3306
# phpMyAdmin disponible en http://localhost:8080

# 4. Lanzar la extracción
#    Opción A: en Docker. El servicio "etl" arranca con "docker compose up" (paso 3), actualiza al
#    arrancar y después cada día a las 00:30. Con la base de datos vacía, la primera vez carga el histórico.
#    Opción B: directamente con Node.js
cd etl
npm install
npm start        # equivale a: node index.js
```

**Actualización automática.** El servicio `etl` se queda en ejecución: al arrancar y después cada día a las 00:30 (`HORA_ACTUALIZACION` en el `.env`, hora de Madrid) lanza `npm run actualizar`, que solo descarga desde la última lectura guardada. Se sigue el log con `docker compose logs -f etl`. Para lanzarlo a mano: `docker compose run --rm etl node index.js` (histórico completo) o `docker compose run --rm etl npm run actualizar`. Tras cambiar código del ETL, usa `docker compose up -d --build`.

Por defecto se descarga el **histórico de los últimos 10 años** de cada ciudad (`HISTORICO_ANIOS`). Para otro rango, define `FECHA_INI` y `FECHA_FIN` (`YYYY-MM-DD`) en el `.env`.

Como AEMET limita cada petición a 6 meses, el ETL divide el rango en tramos de 180 días y espera entre ellos. Si AEMET responde con el límite de peticiones (429), un error de servidor o la red falla, reintenta automáticamente; si un tramo sigue fallando, lo anota y continúa con el resto, y al terminar muestra un resumen de los tramos fallidos.

El script se puede relanzar sin duplicar datos (`UNIQUE(id_ciudad, fecha)` + `ON DUPLICATE KEY UPDATE`). Para ponerte al día sin volver a descargarlo todo:

```bash
npm run actualizar   # continúa desde la última lectura guardada (con 7 días de solape)
```

> El archivo `database/schema.sql` se ejecuta automáticamente la primera vez que se crea el volumen.  
> Para reiniciar desde cero: `docker compose down -v && docker compose up -d`  
> Las variables `DB_*` del `.env` deben coincidir con las `MYSQL_*`. Si cambias una contraseña con la base ya creada, MySQL no la actualiza: recrea el volumen como indica arriba.

## Aplicación web (proyecto Climate Change Analyzer)

La carpeta `web/` contiene la aplicación en PHP (patrón MVC sin framework): `public/` es la raíz del servidor, y `app/` agrupa configuración, modelos, controladores, vistas y utilidades. Se ejecuta con Docker junto con la base de datos.

Bootstrap 5.3.3 y Chart.js 4.4.3 están incluidos en `web/public/assets/vendor`, así que la web funciona sin conexión a internet y no hace falta instalar nada más.

```bash
docker compose up -d --build
# Web disponible en http://localhost:8000
```

### Funcionalidad de usuarios

Todo el contenido es privado: sin sesión, cualquier página lleva al inicio de sesión.

| Página | Qué hace |
|---|---|
| `registro.php` / `login.php` / `logout.php` | Alta de usuario (validación exhaustiva en navegador y servidor), inicio y cierre de sesión. La sesión caduca a los 5 minutos de inactividad. |
| `recuperar.php` → `restablecer.php?token=…` | Recuperación de contraseña por email (ver abajo). |
| `perfil.php` | Consultar y modificar los datos personales (con la edad calculada). |
| `cambiar_password.php` | Cambiar la contraseña conociendo la actual. |
| `baja.php` | Darse de baja: elimina la cuenta y toda su información (pide la contraseña). |
| `administracion.php` | Solo administradores: crear administradores y eliminar usuarios o administradores. No se puede eliminar a uno mismo ni al último administrador. |

El administrador por defecto es el usuario `pepe` (contraseña `pepe`), creado por `database/schema.sql`. Las contraseñas se guardan con hash bcrypt; las consultas son preparadas (PDO) y los formularios llevan token CSRF.

### Recuperación de contraseña por email

El usuario escribe su email en `recuperar.php` y recibe en su correo un enlace a `restablecer.php?token=…` donde elige la contraseña nueva. El enlace es aleatorio (256 bits), caduca a la hora y solo sirve una vez; en la base de datos solo se guarda su hash SHA-256. La respuesta es la misma exista o no el email, y no se reenvía otro correo hasta pasado un minuto. El email de cada usuario es único.

El envío usa SMTP directamente (clase `Correo`, sin librerías externas) con STARTTLS o SSL y verificación del certificado. Hay que configurar en el `.env` (ver `.env.example`) el servidor SMTP. Con Gmail: activar la verificación en dos pasos, crear una *contraseña de aplicación* en <https://myaccount.google.com/apppasswords> y poner `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_SEGURIDAD=tls`, `SMTP_USUARIO` (la cuenta) y `SMTP_PASSWORD` (esa contraseña de aplicación). Después, `docker compose up -d` para que la web recoja las variables. Sin `SMTP_HOST` no se envía nada y el contenido del correo, con el enlace, queda en `docker compose logs web` (útil para probar).

**Base de datos ya creada.** `schema.sql` solo se ejecuta al crear el volumen. Si ya lo tenías creado, aplica una vez la migración (añade el email único y la tabla `recuperaciones_password`), o recrea el volumen con `docker compose down -v`:

```bash
docker exec -i meteo_mysql mysql -umeteo -p meteo_aemet < database/migraciones/001_recuperacion_password.sql
```

## Importar el volcado de datos (sin usar la API)

Para obtener la base de datos con los datos ya cargados, sin API key:

```bash
docker exec -i meteo_mysql mysql -umeteo -p meteo_aemet < database/datos_aemet.sql
```

o bien con la pestaña **Importar** de phpMyAdmin. Si cargas nuevos datos con el ETL, regenera el volcado:

```bash
docker exec meteo_mysql mysqldump -umeteo -p --no-tablespaces --databases meteo_aemet > database/datos_aemet.sql
```
