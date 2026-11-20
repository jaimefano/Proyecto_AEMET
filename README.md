# Climate Change Analyzer (CCA)

**Autor:** Jaime Fano Alijarde · Universidad de Zaragoza · Sistemas de Información

Práctica obligatoria (ERS `SI_PHP_CCA_2026`, requisitos resumidos en `requisitos-CCA.md`): web en **PHP** con estudios climáticos a partir de datos reales de AEMET para varias ciudades. Los datos los descarga un **ETL en Node.js** (práctica de investigación) y se guardan en MySQL; la web leerá de esa base de datos.

> Estado: la web se está rediseñando desde cero. La carpeta `web/` está vacía a la espera del nuevo código.

## Estructura

```
proyecto-cca/
├── web/                 Aplicación web (PHP), pendiente de rediseño
├── etl/                 Carga de datos de AEMET (Node.js)
│   ├── index.js, scheduler.js
│   └── src/
│       ├── nucleo/      etl.js, parser.js, rangos.js
│       ├── aemet/       aemet.js
│       └── operacion/   estado.js, programador.js, pool.js
├── database/schema.sql  Esquema de la base de datos (incluye el administrador por defecto, RQ11)
├── requisitos-CCA.md    Requisitos del ERS
└── .env.example         Plantilla de configuración (copiar a .env)
```

## Puesta en marcha con XAMPP

Requisitos: XAMPP (Apache + MySQL/MariaDB + PHP 8), Node.js 20+ y una API key de [AEMET OpenData](https://opendata.aemet.es).

1. Copiar esta carpeta a `C:\xampp\htdocs\proyecto-cca` e iniciar **Apache** y **MySQL**.
2. **Base de datos:** en `http://localhost/phpmyadmin`, pestaña **Importar**, subir `database/schema.sql`.
3. **Configuración:** copiar `.env.example` a `.env` y poner `AEMET_API_KEY`. El `.env` no se sube a git.
4. **Datos (ETL):** desde una terminal en la carpeta `etl`:
   ```
   npm install
   npm start              # descarga el histórico completo (10 años por defecto)
   npm run actualizar     # continúa desde la última lectura guardada
   npm run programador    # se queda en ejecución y actualiza cada día a las 00:30
   ```

Administrador por defecto: usuario `pepe`, contraseña `pepe` (RQ11).
