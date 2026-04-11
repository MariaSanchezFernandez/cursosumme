/**
 * auth.ts
 * -------------------------------------------------------
 * Módulo de autenticación para el cliente (browser).
 * - Sesiones en sessionStorage (8h, se borran al cerrar pestaña).
 * - Contraseñas enviadas en texto plano por HTTPS; el servidor
 *   verifica con bcrypt.
 * - Cada sesión incluye un token aleatorio generado por el servidor
 *   que se envía en cada petición como Authorization: Bearer <token>.
 * -------------------------------------------------------
 */

const SESSION_KEY = 'umme_session';
const SESSION_DURATION_MS = 8 * 60 * 60 * 1000; // 8 horas

export interface Sesion {
  userId: string;
  rol: 'admin' | 'alumno';
  nombre: string;
  email: string;
  token: string;  // token de servidor para autorización
  exp: number;
}

// ── Crear y guardar sesión ───────────────────────────────────────────────────
export function crearSesion(userId: string, rol: 'admin' | 'alumno', nombre: string, email: string, token: string): void {
  const sesion: Sesion = {
    userId, rol, nombre, email, token,
    exp: Date.now() + SESSION_DURATION_MS,
  };
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(sesion));
}

// ── Cabeceras de autorización para fetch() ───────────────────────────────────
export function authHeaders(): HeadersInit {
  try {
    const s: Sesion = JSON.parse(sessionStorage.getItem(SESSION_KEY) || '{}');
    return s.token
      ? { 'Content-Type': 'application/json', 'Authorization': `Bearer ${s.token}` }
      : { 'Content-Type': 'application/json' };
  } catch { return { 'Content-Type': 'application/json' }; }
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
