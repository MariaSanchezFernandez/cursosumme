/**
 * auth.ts
 * -------------------------------------------------------
 * Módulo de autenticación para el cliente (browser).
 *
 * - Las sesiones se guardan en sessionStorage (se borran al cerrar
 *   el navegador o la pestaña).
 * - Las contraseñas se comparan siempre como hashes SHA-256;
 *   nunca se almacena ni se envía la contraseña en texto plano.
 * - Duración de sesión: 8 horas desde el inicio de sesión.
 * -------------------------------------------------------
 */

const SESSION_KEY = 'umme_session';
const SESSION_DURATION_MS = 8 * 60 * 60 * 1000; // 8 horas

export interface Sesion {
  userId: string;
  rol: 'admin' | 'alumno';
  email: string;
  exp: number; // timestamp de expiración
}

// ── Hash SHA-256 usando Web Crypto API ───────────────────────────────────────
export async function hashPassword(password: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(password);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

// ── Crear y guardar sesión ───────────────────────────────────────────────────
export function crearSesion(userId: string, rol: 'admin' | 'alumno', email: string): void {
  const sesion: Sesion = {
    userId,
    rol,
    email,
    exp: Date.now() + SESSION_DURATION_MS,
  };
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(sesion));
}

// ── Obtener sesión activa (null si no existe o expiró) ───────────────────────
export function obtenerSesion(): Sesion | null {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY);
    if (!raw) return null;
    const sesion: Sesion = JSON.parse(raw);
    if (Date.now() > sesion.exp) {
      sessionStorage.removeItem(SESSION_KEY);
      return null;
    }
    return sesion;
  } catch {
    return null;
  }
}

// ── Cerrar sesión ────────────────────────────────────────────────────────────
export function cerrarSesion(): void {
  sessionStorage.removeItem(SESSION_KEY);
}

// ── Proteger ruta: redirige a login si no hay sesión válida con el rol pedido ─
export function protegerRuta(rolRequerido: 'admin' | 'alumno' | 'cualquiera'): void {
  const sesion = obtenerSesion();

  if (!sesion) {
    window.location.replace('/');
    return;
  }

  if (rolRequerido === 'admin' && sesion.rol !== 'admin') {
    // Un alumno que intente entrar en /admin va a /inicio
    window.location.replace('/inicio');
    return;
  }
}
