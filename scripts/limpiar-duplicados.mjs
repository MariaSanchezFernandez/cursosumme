/**
 * limpiar-duplicados.mjs — Borra los HTML duplicados basura ("… N.html")
 * del servidor y de dist/ local. Solo toca ficheros con el patrón
 * "<algo> <dígito>.html"; NUNCA el index.html real.
 *
 * Uso: node scripts/limpiar-duplicados.mjs
 */
import SftpClient from 'ssh2-sftp-client';
import { readFileSync, readdirSync, statSync, rmSync } from 'fs';
import path from 'path';

function loadEnv() {
  for (const line of readFileSync('.env', 'utf-8').split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const [k, ...r] = t.split('=');
    process.env[k.trim()] = r.join('=').trim().replace(/^["']|["']$/g, '');
  }
}
function strays(dir, dist, out = []) {
  for (const name of readdirSync(dir)) {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) strays(full, dist, out);
    else if (/ \d+\.html$/.test(name)) out.push(path.relative(dist, full));
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

const files = strays('dist', 'dist');
console.log(`Duplicados a borrar: ${files.length}`);

const sftp = new SftpClient();
await sftp.connect(cfg);
for (const rel of files) {
  try { await sftp.delete(remote(rel)); console.log('· servidor borrado:', rel); }
  catch (e) { console.log('· (servidor) no estaba:', rel, '—', e.message); }
}
await sftp.end();

for (const rel of files) {
  rmSync(path.join('dist', rel), { force: true });
  console.log('· local borrado:', rel);
}
console.log('✅ Limpieza completada.');
