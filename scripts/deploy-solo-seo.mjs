/**
 * deploy-solo-seo.mjs
 * -------------------------------------------------
 * Despliegue PARCIAL: sube todo `dist/` EXCEPTO las carpetas `api`
 * (backend PHP) y `admin` (panel). Pensado para publicar solo cambios
 * públicos/SEO (robots.txt, sitemap, /quienes-somos, páginas con el
 * nuevo enlace de cabecera/pie + assets _astro) sin tocar el backend
 * ni el panel de administración que ya están en el servidor.
 *
 * Uso:
 *   node scripts/deploy-solo-seo.mjs
 *
 * Requiere lo mismo que deploy.mjs: .env con SFTP_* y un dist/ ya
 * construido (lanza antes `npm run build`).
 *
 * NOTA: NO borra nada en el servidor (uploadDir/fastPut solo escriben).
 * Por eso db-config.php, /api y /admin del servidor quedan intactos.
 * -------------------------------------------------
 */

import SftpClient from 'ssh2-sftp-client';
import { existsSync, readFileSync, readdirSync, statSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');

// Carpetas de primer nivel de dist/ que NO se suben.
const EXCLUIR = new Set(['api', 'admin']);

function loadEnv() {
  const envPath = join(ROOT, '.env');
  if (!existsSync(envPath)) return;
  for (const line of readFileSync(envPath, 'utf-8').split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const [key, ...rest] = t.split('=');
    process.env[key.trim()] = rest.join('=').trim().replace(/^["']|["']$/g, '');
  }
}

async function main() {
  loadEnv();

  const host = process.env.SFTP_HOST || 'home335171042.1and1-data.host';
  const port = parseInt(process.env.SFTP_PORT || '22', 10);
  const user = process.env.SFTP_USER || 'acc190978561';
  const remoteBase = (process.env.SFTP_REMOTE_PATH || '/').replace(/\/$/, '');
  const password = process.env.SFTP_PASS || '';
  const localDist = join(ROOT, 'dist');

  if (!existsSync(localDist)) {
    console.error('No existe dist/. Lanza `npm run build` primero.');
    process.exit(1);
  }
  if (!password) {
    console.error('SFTP_PASS vacío en .env. Abortando (este script es no interactivo).');
    process.exit(1);
  }

  const entradas = readdirSync(localDist);
  const aSubir = entradas.filter((n) => !EXCLUIR.has(n));
  console.log(`\nSubida dirigida (solo SEO/público) a ${host}:${remoteBase || '/'}`);
  console.log(`  Excluidas: ${[...EXCLUIR].join(', ')}`);
  console.log(`  Entradas a subir: ${aSubir.length}\n`);

  const sftp = new SftpClient();
  try {
    await sftp.connect({
      host, port, username: user, password,
      tryKeyboard: true,
      onKeyboardInteractive: (_n, _i, _l, _p, finish) => finish([password]),
    });

    for (const nombre of aSubir) {
      const local = join(localDist, nombre);
      const remoto = `${remoteBase}/${nombre}`;
      if (statSync(local).isDirectory()) {
        process.stdout.write(`  [dir]  ${nombre}/ ... `);
        await sftp.uploadDir(local, remoto);
        console.log('ok');
      } else {
        process.stdout.write(`  [file] ${nombre} ... `);
        await sftp.fastPut(local, remoto);
        console.log('ok');
      }
    }
    console.log('\n  ✅ Subida SEO completada. /api y /admin del servidor NO se han tocado.');
  } catch (err) {
    console.error('\n  ❌ Error durante la subida:', err.message);
    process.exit(1);
  } finally {
    await sftp.end();
  }
}

main();
