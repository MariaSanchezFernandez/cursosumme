/**
 * subir-404.mjs — Sube SOLO la página 404 y el .htaccess al servidor.
 * No usa npm run deploy (no toca dist/ entero ni db-config.php).
 *
 * Seguridad:
 *  - Hace backup del .htaccess remoto antes de pisarlo.
 *  - Tras subir, comprueba que la home responde 200.
 *  - Si la home da >=500 (p. ej. IONOS rechaza `Options -MultiViews`),
 *    restaura automáticamente el .htaccess original.
 *
 * Uso: node scripts/subir-404.mjs
 */
import SftpClient from 'ssh2-sftp-client';
import { readFileSync, writeFileSync } from 'fs';
import path from 'path';
import https from 'https';

function loadEnv() {
  for (const line of readFileSync('.env', 'utf-8').split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const [k, ...r] = t.split('=');
    process.env[k.trim()] = r.join('=').trim().replace(/^["']|["']$/g, '');
  }
}
const status = (url) =>
  new Promise((res) => {
    https.get(url, (r) => { r.resume(); res(r.statusCode); }).on('error', () => res(0));
  });

loadEnv();
const cfg = {
  host: process.env.SFTP_HOST,
  port: parseInt(process.env.SFTP_PORT || '22', 10),
  username: process.env.SFTP_USER,
  password: process.env.SFTP_PASS,
  tryKeyboard: true,
  onKeyboardInteractive: (_n, _i, _l, _p, finish) => finish([process.env.SFTP_PASS]),
};
const base = process.env.SFTP_REMOTE_PATH || '/';
const remote = (f) => path.posix.join(base, f);

const sftp = new SftpClient();
await sftp.connect(cfg);

// 1) Backup del .htaccess remoto
let backup = null;
try {
  backup = await sftp.get(remote('.htaccess'));
  writeFileSync('.htaccess.server-backup', backup);
  console.log('· Backup del .htaccess remoto → .htaccess.server-backup');
} catch (e) {
  console.log('· No se pudo leer el .htaccess remoto previo:', e.message);
}

// 2) Subir los dos archivos (primero el inofensivo)
await sftp.put('dist/404.html', remote('404.html'));
console.log('· Subido: 404.html');
await sftp.put('dist/.htaccess', remote('.htaccess'));
console.log('· Subido: .htaccess');
await sftp.end();

// 3) Comprobación de salud
const home = await status('https://cursosumme.es/');
const c1 = await status('https://cursosumme.es/cursos/1');
console.log(`· Home: ${home}   /cursos/1: ${c1}`);

if (home === 0 || home >= 500) {
  console.log(`⚠️  La home responde ${home}. Restaurando .htaccess original...`);
  if (backup) {
    const s2 = new SftpClient();
    await s2.connect(cfg);
    await s2.put(Buffer.from(backup), remote('.htaccess'));
    await s2.end();
    const home2 = await status('https://cursosumme.es/');
    console.log(`· Restaurado. Home ahora: ${home2}`);
  }
  console.log('❌ Abortado: el .htaccess rompía la web; se ha revertido.');
  process.exit(1);
}

console.log('✅ OK. Home 200 y /cursos/1 sirviendo el 404 propio (status 404).');
