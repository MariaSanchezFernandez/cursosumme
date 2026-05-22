/**
 * backup-db.mjs — Descarga un backup SQL completo de la BD del servidor
 * Uso: npm run backup-db
 *
 * Llama a http://cursosumme.es/api/backup-db.php?key=BACKUP_SECRET,
 * descarga el archivo y lo guarda en backups/ con timestamp.
 * Mantiene solo los últimos 10 backups.
 */

import { mkdirSync, writeFileSync, readdirSync, unlinkSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';

dotenv.config();

const __dir     = dirname(fileURLToPath(import.meta.url));
const ROOT       = join(__dir, '..');
const BACKUPS_DIR = join(ROOT, 'backups');
const MAX_BACKUPS = 10;
const BASE_URL    = 'http://cursosumme.es';

// ── Verificar BACKUP_SECRET ────────────────────────────────────────────────
const secret = process.env.BACKUP_SECRET;
if (!secret) {
  console.error('Error: BACKUP_SECRET no está definido en .env');
  console.error('Añade BACKUP_SECRET=<clave_larga_aleatoria> a tu .env y');
  console.error('asegúrate de que db-config.php llama a getenv("BACKUP_SECRET").');
  process.exit(1);
}

// ── Crear carpeta backups/ si no existe ────────────────────────────────────
mkdirSync(BACKUPS_DIR, { recursive: true });

// ── Llamar al endpoint ─────────────────────────────────────────────────────
const url = `${BASE_URL}/api/backup-db.php?key=${encodeURIComponent(secret)}`;

console.log('Solicitando backup al servidor…');
const res = await fetch(url);

if (!res.ok) {
  const cuerpo = await res.text();
  console.error(`Error ${res.status}: ${cuerpo}`);
  process.exit(1);
}

const sql = await res.text();

// ── Guardar archivo ────────────────────────────────────────────────────────
const ahora     = new Date();
const timestamp = ahora.toISOString()
  .replace('T', '_')
  .replace(/:/g, '-')
  .slice(0, 19);
const nombreArchivo = `${timestamp}.sql`;
const rutaArchivo   = join(BACKUPS_DIR, nombreArchivo);

writeFileSync(rutaArchivo, sql, 'utf8');

const kb = Math.round(sql.length / 1024);
console.log(`Backup guardado: backups/${nombreArchivo} (${kb} KB)`);

// ── Limpiar backups antiguos ───────────────────────────────────────────────
const archivos = readdirSync(BACKUPS_DIR)
  .filter(f => f.endsWith('.sql'))
  .sort();

if (archivos.length > MAX_BACKUPS) {
  const aEliminar = archivos.slice(0, archivos.length - MAX_BACKUPS);
  for (const viejo of aEliminar) {
    unlinkSync(join(BACKUPS_DIR, viejo));
    console.log(`Backup antiguo eliminado: ${viejo}`);
  }
}
