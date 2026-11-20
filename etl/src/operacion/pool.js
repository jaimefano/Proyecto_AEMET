// Author: Jaime Fano Alijarde
// Descripcion: Pool de conexiones MySQL reutilizable para toda la aplicación.
const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host:     process.env.DB_HOST     || 'localhost',
  port:     process.env.DB_PORT     || 3306,
  database: process.env.DB_NAME     || 'meteo_aemet',
  user:     process.env.DB_USER     || 'aemet',
  password: process.env.DB_PASSWORD || '',
  waitForConnections: true,
  connectionLimit: 5,
});

module.exports = pool;
