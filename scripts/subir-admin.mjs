/**
 * subir-admin.mjs — Sube SOLO las páginas admin indicadas (y los assets
 * nuevos que referencien). No toca db-config.php ni el resto del sitio.
 *
 * Uso: node scripts/subir-admin.mjs admin/cursos admin/estadisticas
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
  new Promise((res) => { https.get(url, (r) => { r.resume(); res(r.statusCode); }).on('error', () => res(0)); });

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

const pages = process.argv.slice(2);
if (!pages.length) { console.error('Indica al menos una página, p.ej. admin/cursos'); process.exit(1); }

// Reúne assets referenciados y detecta cuáles faltan en el servidor.
const assets = new Set();
for (const p of pages) {
  const html = readFileSync(`dist/${p}/index.html`, 'utf-8');
  for (const m of html.matchAll(/\/_astro\/[A-Za-z0-9._-]+\.(?:css|js)/g)) assets.add(m[0]);
}
const missing = [];
for (const a of assets) {
  const code = await status('https://cursosumme.es' + a);
  if (code !== 200) missing.push(a);
}
console.log(missing.length ? `· Assets nuevos a subir: ${missing.join(', ')}` : '· Sin assets nuevos.');

const sftp = new SftpClient();
await sftp.connect(cfg);
// 1) assets nuevos primero
for (const a of missing) {
  if (existsSync('dist' + a)) { await sftp.put('dist' + a, remote(a)); console.log('· Subido:', a); }
}
// 2) HTML de las páginas
for (const p of pages) {
  await sftp.put(`dist/${p}/index.html`, remote(`${p}/index.html`));
  console.log('· Subido:', `${p}/index.html`);
}
await sftp.end();

// Comprobación
const home = await status('https://cursosumme.es/');
console.log(`· Home: ${home}`);
for (const p of pages) {
  const c = await status('https://cursosumme.es/' + p);
  console.log(`· /${p}: ${c}`);
}
console.log(home === 200 ? '✅ OK. Home intacta.' : '⚠️  Revisar la home.');
