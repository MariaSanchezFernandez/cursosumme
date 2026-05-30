// ─────────────────────────────────────────────────────────────
// utils/duracion.ts
// Fuente única de cómo se formatea una duración en segundos para
// presentarla al usuario. Antes vivía duplicada como
// formatearDuracion en inicio.astro y formatearMinutos en
// editar.astro / crear-curso.astro; ahora todos importan de aquí.
//
// Redondea al minuto más cercano y devuelve:
//   "2h 5min"   si hay horas y minutos
//   "2h"        si hay horas exactas
//   "45 min"    si son menos de 60 minutos
//   ""          si el valor es 0, NULL o no procesable
// ─────────────────────────────────────────────────────────────

export function formatearDuracion(segundos: number | null | undefined): string {
  const seg = Number(segundos);
  if (!seg || seg <= 0) return '';
  const mins = Math.round(seg / 60);
  if (mins <= 0) return '';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return h > 0 ? (m > 0 ? `${h}h ${m}min` : `${h}h`) : `${m} min`;
}
