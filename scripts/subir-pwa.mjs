/**
 * subir-pwa.mjs — Sube SOLO lo tocado por la mejora "acceso directo móvil":
 *   - manifest.webmanifest + 3 iconos (nuevos)
 *   - todos los HTML públicos (su <head> cambió por Plantilla.astro)
 * NO sube admin/, api/, db-config.php, .env ni _astro (nada de eso cambió).
 * Sube archivo por archivo de una lista explícita.
 *
 * Uso: node scripts/subir-pwa.mjs
 */
import SftpClient from 'ssh2-sftp-client';
import { readFileSync, readdirSync, statSync, existsSync } from 'fs';
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

// Recorre dist y devuelve los *.html que NO están bajo admin/
function htmlPublicos(dir, dist) {
  let out = [];
  for (const name of readdirSync(dir)) {
    const full = path.join(dir, name);
    const rel = path.relative(dist, full);
    if (rel.split(path.sep)[0] === 'admin') continue;        // saltar admin/
    if (statSync(full).isDirectory()) out = out.concat(htmlPublicos(full, dist));
    else if (name.endsWith('.html')) out.push(rel);
  }
  return out;
}

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
const remote = (rel) => path.posix.join(base, rel.split(path.sep).join('/'));

const estaticos = ['manifest.webmanifest', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png'];
const htmls = htmlPublicos('dist', 'dist');

console.log(`A subir: ${estaticos.length} estáticos + ${htmls.length} HTML públicos. (admin/api/.env: NO)`);

const sftp = new SftpClient();
await sftp.connect(cfg);
for (const f of estaticos) {
  if (existsSync(path.join('dist', f))) { await sftp.put(path.join('dist', f), remote(f)); console.log('· nuevo:', f); }
}
for (const rel of htmls) {
  await sftp.put(path.join('dist', rel), remote(rel));
  console.log('· html :', rel);
}
await sftp.end();

const home = await status('https://cursosumme.es/');
const login = await status('https://cursosumme.es/login');
const man = await status('https://cursosumme.es/manifest.webmanifest');
const ico = await status('https://cursosumme.es/icon-192.png');
console.log(`· Home:${home}  Login:${login}  manifest:${man}  icon-192:${ico}`);
console.log(home === 200 ? '✅ OK. Solo se subieron HTML públicos + manifest + iconos.' : '⚠️  Revisar la home.');
