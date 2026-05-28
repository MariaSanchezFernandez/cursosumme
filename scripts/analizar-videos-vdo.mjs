/**
 * analizar-videos-vdo.mjs — SOLO LECTURA. No borra ni modifica nada.
 *
 * Cruza la tabla `materiales` con la cuenta de VdoCipher para detectar:
 *   1. Grupos de vídeos duplicados (mismo nombre subido varias veces con
 *      distinto videoId, antes de existir la reutilización).
 *   2. Vídeos en VdoCipher que NINGÚN material referencia (huérfanos).
 *   3. Referencias en BD a vídeos que ya no existen en VdoCipher (rotas).
 *
 * Uso: node scripts/analizar-videos-vdo.mjs
 */

import mysql from 'mysql2/promise';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';

dotenv.config();
const __dir = dirname(fileURLToPath(import.meta.url));

// ── API key de VdoCipher desde db-config.php ──────────────────
function leerApiKey() {
  const php = readFileSync(resolve(__dir, '../public/api/db-config.php'), 'utf8');
  const m = php.match(/define\(\s*'VDOCIPHER_API_KEY'\s*,\s*'([^']+)'\s*\)/);
  if (!m) throw new Error('No se encontró VDOCIPHER_API_KEY en db-config.php');
  return m[1];
}
const API_KEY = leerApiKey();

// ── Listar TODOS los vídeos de la cuenta VdoCipher (paginado) ─
async function listarVideosVdo() {
  const todos = [];
  let page = 1;
  while (true) {
    const res = await fetch(`https://dev.vdocipher.com/api/videos?page=${page}&limit=40`, {
      headers: { Authorization: `Apisecret ${API_KEY}`, Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`VdoCipher list HTTP ${res.status}: ${await res.text()}`);
    const data = await res.json();
    const rows = data.rows || [];
    todos.push(...rows);
    if (rows.length < 40) break;
    page++;
  }
  return todos;
}

const fmtSeg = (s) => {
  s = Math.round(Number(s) || 0);
  if (s <= 0) return '—';
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
  return h > 0 ? `${h}:${String(m).padStart(2,'0')}:${String(x).padStart(2,'0')}`
              : `${m}:${String(x).padStart(2,'0')}`;
};

// ──────────────────────────────────────────────────────────────
const cx = await mysql.createConnection({
  host:     process.env.DB_HOST,
  port:     Number(process.env.DB_PUERTO) || 3306,
  user:     process.env.DB_USUARIO,
  password: process.env.DB_CONTRASENA,
  database: process.env.DB_NOMBRE,
});

const [mats] = await cx.execute(
  `SELECT m.id, m.tema_id, m.nombre, m.duracion_seg, m.vdocipher_video_id, m.vdo_status,
          t.titulo AS tema, t.curso_id
   FROM materiales m
   LEFT JOIN temas t ON t.id = m.tema_id
   WHERE m.tipo = 'video' AND m.vdocipher_video_id IS NOT NULL
   ORDER BY m.nombre, m.id`
);
await cx.end();

console.log(`\nMateriales de vídeo en BD: ${mats.length}`);

const vdos = await listarVideosVdo();
console.log(`Vídeos en la cuenta VdoCipher: ${vdos.length}\n`);

const vdoById = new Map(vdos.map(v => [v.id, v]));
const referenciados = new Set(mats.map(m => m.vdocipher_video_id));

// ── 1. Grupos duplicados (mismo nombre, >1 videoId distinto) ──
const porNombre = new Map();
for (const m of mats) {
  if (!porNombre.has(m.nombre)) porNombre.set(m.nombre, []);
  porNombre.get(m.nombre).push(m);
}

console.log('═══ 1) GRUPOS DUPLICADOS (mismo nombre, varios videoId) ═══\n');
let totalCopiasSobrantes = 0;
let grupos = 0;
for (const [nombre, lista] of porNombre) {
  const ids = [...new Set(lista.map(m => m.vdocipher_video_id))];
  if (ids.length <= 1) continue;
  grupos++;
  totalCopiasSobrantes += ids.length - 1;
  console.log(`▸ "${nombre}"  — ${ids.length} copias en VdoCipher`);
  for (const id of ids) {
    const usados = lista.filter(m => m.vdocipher_video_id === id);
    const v = vdoById.get(id);
    const dur = fmtSeg(usados[0].duracion_seg);
    const lenVdo = v ? fmtSeg(v.length) : 'NO EXISTE EN VDO';
    const temas = usados.map(u => `${u.tema || '?'}#${u.tema_id}`).join(', ');
    console.log(`    ${id}  dur=${dur} (vdo=${lenVdo})  status=${v?.status ?? '?'}  → temas: ${temas}`);
  }
  console.log('');
}
console.log(`Grupos duplicados: ${grupos} · copias sobrantes que se podrían borrar: ${totalCopiasSobrantes}\n`);

// ── 2. Huérfanos en VdoCipher (no referenciados por ningún material) ──
console.log('═══ 2) VÍDEOS EN VDOCIPHER SIN REFERENCIA EN BD ═══\n');
const huerfanos = vdos.filter(v => !referenciados.has(v.id));
for (const v of huerfanos) {
  console.log(`    ${v.id}  "${v.title}"  ${fmtSeg(v.length)}  status=${v.status}`);
}
console.log(`\nHuérfanos: ${huerfanos.length}\n`);

// ── 3. Referencias rotas (BD apunta a un videoId que ya no existe) ──
console.log('═══ 3) REFERENCIAS EN BD A VÍDEOS QUE NO EXISTEN EN VDOCIPHER ═══\n');
const rotas = mats.filter(m => !vdoById.has(m.vdocipher_video_id));
for (const m of rotas) {
  console.log(`    material#${m.id} tema "${m.tema}"#${m.tema_id}  → ${m.vdocipher_video_id} (NO existe)`);
}
console.log(`\nReferencias rotas: ${rotas.length}\n`);

console.log('─── Este script NO ha borrado ni modificado nada. ───\n');
