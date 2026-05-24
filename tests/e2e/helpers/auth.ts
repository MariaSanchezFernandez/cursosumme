// Helpers de autenticación para tests E2E.
// El login real se hace UNA vez en tests/global-setup.ts y los tokens
// se guardan en tests/.auth-tokens.json. Aquí solo leemos esa caché
// para evitar saturar el rate-limit de /api/login.php.

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import type { APIRequestContext } from '@playwright/test';

export interface SesionTest {
  token:  string;
  userId: number;
  rol:    'admin' | 'alumno';
  email:  string;
}

const TOKENS_FILE = join(process.cwd(), 'tests', '.auth-tokens.json');

interface TokensCache { alumno?: SesionTest; admin?: SesionTest }

function leerCache(): TokensCache {
  try {
    return JSON.parse(readFileSync(TOKENS_FILE, 'utf-8'));
  } catch {
    throw new Error(
      'No se pudo leer tests/.auth-tokens.json. ¿Has corrido el global-setup? ' +
      'Asegúrate de tener TEST_ALUMNO_* y TEST_ADMIN_* en .env.',
    );
  }
}

export function getAlumno(): SesionTest {
  const c = leerCache();
  if (!c.alumno) throw new Error('Faltan credenciales TEST_ALUMNO_* en .env');
  return c.alumno;
}

export function getAdmin(): SesionTest {
  const c = leerCache();
  if (!c.admin) throw new Error('Faltan credenciales TEST_ADMIN_* en .env');
  return c.admin;
}

// Compat: si algún test prefiere "hacer login" en runtime, devolvemos la
// sesión cacheada (no hace un login real). Acepta el request por API
// pero lo ignora.
export async function loginAlumno(_request: APIRequestContext): Promise<SesionTest> {
  return getAlumno();
}
export async function loginAdmin(_request: APIRequestContext): Promise<SesionTest> {
  return getAdmin();
}

export function authHeaders(sesion: SesionTest): Record<string, string> {
  return { 'X-Token': sesion.token };
}
