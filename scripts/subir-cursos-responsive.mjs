/**
 * subir-cursos-responsive.mjs — Sube SOLO la página admin/cursos y sus
 * 2 CSS nuevos (mejora responsive). No toca db-config.php ni el resto.
 *
 * Sube los CSS primero y el HTML después (para que el HTML nunca apunte
 * a un CSS que aún no existe). Comprueba que /admin/cursos responde 200.
 *
 * Uso: node scripts/subir-cursos-responsive.mjs
 */
import SftpClient from 'ssh2-sftp-client';
import { readFileSync, existsSync } from 'fs';
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

// Lista de archivos a subir, leyendo los nombres reales referenciados por la
// página construida (así no hay que actualizar hashes a mano).
const html = readFileSync('dist/admin/cursos/index.html', 'utf-8');
const cssRefs = [...new Set([...html.matchAll(/\/_astro\/[A-Za-z0-9._-]+\.css/g)].map((m) => m[0]))];

const files = [];
// CSS primero
for (const ref of cssRefs) {
  const local = 'dist' + ref;
  if (existsSync(local)) files.push({ local, remote: remote(ref) });
}
// HTML después
files.push({ local: 'dist/admin/cursos/index.html', remote: remote('admin/cursos/index.html') });

const sftp = new SftpClient();
await sftp.connect(cfg);
for (const f of files) {
  await sftp.put(f.local, f.remote);
  console.log('· Subido:', f.remote);
}
await sftp.end();

const home = await status('https://cursosumme.es/');
const cursos = await status('https://cursosumme.es/admin/cursos');
console.log(`· Home: ${home}   /admin/cursos: ${cursos}`);
if (home === 200 && (cursos === 200 || cursos === 301)) {
  console.log('✅ OK. Subido solo admin/cursos + sus CSS; el resto del sitio intacto.');
} else {
  console.log('⚠️  Revisar: alguna respuesta no esperada.');
}
