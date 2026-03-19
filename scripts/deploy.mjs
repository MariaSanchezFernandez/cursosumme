/**
 * deploy.mjs
 * -------------------------------------------------
 * Script de despliegue SFTP para Cursos Umme.
 *
 * Uso:
 *   npm run deploy
 *
 * Antes de ejecutar, crea un archivo .env en la raíz
 * del proyecto copiando .env.example y rellenando los valores.
 * La contraseña se puede poner en .env (SFTP_PASS) o se pedirá
 * por pantalla si no está definida.
 * -------------------------------------------------
 */

import SftpClient from 'ssh2-sftp-client';
import { execSync } from 'child_process';
import { existsSync, readFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import * as readline from 'readline';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');

// ── Cargar variables de entorno desde .env ──────────────────────────────────
function loadEnv() {
  const envPath = join(ROOT, '.env');
  if (!existsSync(envPath)) return;
  const lines = readFileSync(envPath, 'utf-8').split('\n');
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const [key, ...rest] = trimmed.split('=');
    process.env[key.trim()] = rest.join('=').trim().replace(/^["']|["']$/g, '');
  }
}

// ── Pedir contraseña por pantalla (oculta) ──────────────────────────────────
function askPassword(prompt) {
  return new Promise((resolve) => {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    process.stdout.write(prompt);
    // Ocultar la entrada
    process.stdin.setRawMode?.(true);
    let password = '';
    process.stdin.on('data', function handler(char) {
      char = char.toString();
      if (char === '\r' || char === '\n') {
        process.stdin.setRawMode?.(false);
        process.stdin.removeListener('data', handler);
        process.stdout.write('\n');
        rl.close();
        resolve(password);
      } else if (char === '\u0003') {
        process.stdout.write('\n');
        process.exit(1);
      } else if (char === '\u007f') {
        password = password.slice(0, -1);
      } else {
        password += char;
      }
    });
    process.stdin.resume();
  });
}

// ── Main ─────────────────────────────────────────────────────────────────────
async function main() {
  loadEnv();

  const host     = process.env.SFTP_HOST     || 'home335171042.1and1-data.host';
  const port     = parseInt(process.env.SFTP_PORT || '22', 10);
  const user     = process.env.SFTP_USER     || 'acc190978561';
  const remotePath = process.env.SFTP_REMOTE_PATH || '/';
  const localDist  = join(ROOT, 'dist');

  let password = process.env.SFTP_PASS || '';
  if (!password) {
    password = await askPassword(`Contraseña SFTP para ${user}@${host}: `);
  }

  // ── 1. Build ───────────────────────────────────────────────────────────────
  console.log('\n[1/2] Construyendo el proyecto...');
  try {
    execSync('npm run build', { cwd: ROOT, stdio: 'inherit' });
  } catch {
    console.error('Error al construir el proyecto. Abortando.');
    process.exit(1);
  }

  // ── 2. Subir por SFTP ──────────────────────────────────────────────────────
  console.log(`\n[2/2] Subiendo archivos a ${host}:${remotePath} ...`);
  const sftp = new SftpClient();

  try {
    await sftp.connect({ host, port, username: user, password });
    console.log('  Conectado. Sincronizando dist/ → servidor...');
    await sftp.uploadDir(localDist, remotePath);
    console.log('\n  Despliegue completado con exito.');
  } catch (err) {
    console.error('\n  Error durante el despliegue:', err.message);
    process.exit(1);
  } finally {
    await sftp.end();
  }
}

main();
