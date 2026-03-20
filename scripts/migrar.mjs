/**
 * migrar.mjs — Ejecuta las migraciones SQL directamente contra la BD de IONOS
 * Uso: npm run migrar
 */

import mysql from 'mysql2/promise';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';

dotenv.config();

const __dir = dirname(fileURLToPath(import.meta.url));

const conexion = await mysql.createConnection({
  host:     process.env.DB_HOST,
  port:     Number(process.env.DB_PUERTO) || 3306,
  user:     process.env.DB_USUARIO,
  password: process.env.DB_CONTRASENA,
  database: process.env.DB_NOMBRE,
  multipleStatements: true,
});

console.log('Conectado a la base de datos.');

const archivo = resolve(__dir, '../base-de-datos/estructura.sql');
let sql = readFileSync(archivo, 'utf8');

// Eliminar la línea USE para evitar errores de permisos
sql = sql.replace(/USE\s+\w+;/gi, '');

try {
  await conexion.query(sql);
  console.log('Migración completada con éxito.');
} catch (err) {
  console.error('Error en la migración:', err.message);
} finally {
  await conexion.end();
}
