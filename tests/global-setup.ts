// Hace login una sola vez al inicio del run y cachea los tokens en
// tests/.auth-tokens.json (gitignoreado). Así los specs no saturan el
// rate-limit de login.php cuando se ejecutan en paralelo.

import { request as playwrightRequest, type FullConfig } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import * as dotenv from 'dotenv';

dotenv.config();

export const TOKENS_FILE = join(process.cwd(), 'tests', '.auth-tokens.json');

async function loginUna(baseURL: string, email: string, contrasena: string) {
  const ctx = await playwrightRequest.newContext({ baseURL });
  const res = await ctx.post('/api/login.php', {
    data: { email, contrasena },
    headers: { 'Content-Type': 'application/json' },
  });
  const data = await res.json();
  await ctx.dispose();
  if (!res.ok() || !data.ok || !data.token) {
    throw new Error(`Login falló para ${email}: HTTP ${res.status()} · ${JSON.stringify(data)}`);
  }
  return { token: data.token, userId: parseInt(data.id, 10), rol: data.rol, email: data.email };
}

async function limpiarSesionesViaApi(baseURL: string, adminToken: string, emailAlumno: string) {
  const ctx = await playwrightRequest.newContext({ baseURL });
  try {
    // 1. Encontrar el ID del alumno por email
    const resAlumnos = await ctx.get('/api/alumnos.php', {
      headers: { 'X-Token': adminToken },
    });
    const dataAlumnos = await resAlumnos.json();
    if (!dataAlumnos.ok) return;
    const alumno = (dataAlumnos.alumnos as Array<{ id: number; email: string }>)
      .find((a) => a.email.toLowerCase() === emailAlumno.toLowerCase());
    if (!alumno) return;

    // 2. Borrar todas sus sesiones activas
    await ctx.delete(`/api/sesiones.php?usuario_id=${alumno.id}`, {
      headers: { 'X-Token': adminToken },
    });
  } finally {
    await ctx.dispose();
  }
}

export default async function globalSetup(_config: FullConfig) {
  const baseURL = process.env.BASE_URL || 'http://cursosumme.es';
  const out: Record<string, unknown> = {};

  // 1. Login del admin primero — su sesión nos sirve para limpiar al alumno
  if (process.env.TEST_ADMIN_EMAIL && process.env.TEST_ADMIN_PASS) {
    out.admin = await loginUna(baseURL, process.env.TEST_ADMIN_EMAIL, process.env.TEST_ADMIN_PASS);
  }

  // 2. Si tenemos admin, limpiar sesiones del alumno antes de loguearlo
  if ((out.admin as any)?.token && process.env.TEST_ALUMNO_EMAIL) {
    await limpiarSesionesViaApi(baseURL, (out.admin as any).token, process.env.TEST_ALUMNO_EMAIL);
  }

  // 3. Login del alumno (ahora sin sesiones acumuladas)
  if (process.env.TEST_ALUMNO_EMAIL && process.env.TEST_ALUMNO_PASS) {
    out.alumno = await loginUna(baseURL, process.env.TEST_ALUMNO_EMAIL, process.env.TEST_ALUMNO_PASS);
  }

  mkdirSync(dirname(TOKENS_FILE), { recursive: true });
  writeFileSync(TOKENS_FILE, JSON.stringify(out, null, 2));
}
