/**
 * setup-paquetes-stripe.mjs — Llama al endpoint del servidor que
 * crea productos+precios en Stripe (modo test) y rellena la BD con
 * `precio`, `stripe_price_id` y los enlaces `pack_cursos`.
 *
 * Es necesario llamar al servidor porque la BD de IONOS solo es
 * accesible desde dentro del hosting (no admite conexiones externas).
 *
 * Uso: node scripts/setup-paquetes-stripe.mjs
 *
 * Idempotente: se puede relanzar sin riesgo, el endpoint salta lo
 * que ya esté creado.
 */

import dotenv from 'dotenv';

dotenv.config();

const URL    = 'http://cursosumme.es/api/setup-paquetes-stripe.php';
const secret = process.env.BACKUP_SECRET;

if (!secret) {
  console.error('❌ BACKUP_SECRET no está en .env');
  process.exit(1);
}

console.log('Llamando al endpoint de setup…');
const res = await fetch(URL, {
  method:  'POST',
  headers: { 'Content-Type': 'application/json' },
  body:    JSON.stringify({ key: secret }),
});

const txt = await res.text();
let data;
try { data = JSON.parse(txt); }
catch { console.error(`HTTP ${res.status} — respuesta no JSON:\n${txt.slice(0, 500)}`); process.exit(1); }

if (!res.ok || !data.ok) {
  console.error(`❌ Error (HTTP ${res.status}):`, data.mensaje || data);
  if (data.log) console.error('Log parcial:', data.log);
  process.exit(1);
}

console.log('\n── Log ──');
for (const l of data.log) console.log('  ' + l);

console.log('\n── Cursos con precio ──');
console.table(data.cursos);

console.log('\n── Packs ──');
console.table(data.packs);

console.log('\n── Enlaces pack_cursos ──');
console.table(data.enlaces);

console.log('\n✅ Setup completo.');
