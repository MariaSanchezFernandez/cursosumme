/**
 * DECLARACIONES GLOBALES — CursosUmme
 * -----------------------------------
 * Tipados ambient de las APIs globales que exponemos en `window`
 * desde scripts inline de los layouts (ver Plantilla.astro y
 * PlantillaAdmin.astro). Esto da autocompletado en TypeScript y
 * evita "Property X does not exist on type 'Window'".
 *
 * No añadir aquí lógica — solo tipos.
 */

/// <reference types="astro/client" />

type TipoToast = 'success' | 'error' | 'info' | 'warning';

interface ToastApi {
  success(mensaje: string, duracionMs?: number): HTMLElement | null;
  error(mensaje: string, duracionMs?: number): HTMLElement | null;
  info(mensaje: string, duracionMs?: number): HTMLElement | null;
  warning(mensaje: string, duracionMs?: number): HTMLElement | null;
}

interface Window {
  /** Sistema de toasts — definido en src/components/Toast.astro */
  mostrarToast(mensaje: string, tipo?: TipoToast, duracionMs?: number): HTMLElement | null;
  toast: ToastApi;

  /** Registro de errores manejados — definido en los layouts */
  logAppError(tipo: string, mensaje: string, extra?: { detalle?: string; stack?: string }): void;

  /**
   * Estado de carga reutilizable para CUALQUIER `<button>`.
   * Definido en src/components/BotonCargando.astro.
   *
   * Reemplaza el contenido del botón por un spinner + texto contextual,
   * lo deja `disabled` mientras dura la acción y lo restaura al terminar
   * (también si la acción lanza una excepción).
   */
  botonCargando<T>(
    btn: HTMLButtonElement | HTMLElement | null,
    textoMientrasCarga: string,
    accion: () => Promise<T>
  ): Promise<T | undefined>;

  /**
   * Modal de confirmación con estética Umme, en sustitución del
   * confirm() nativo del navegador. Definido en src/components/Modal.astro.
   *
   * Resuelve `true` si el usuario pulsa "Aceptar", `false` si pulsa
   * "Cancelar", Escape o clica fuera del modal.
   */
  confirmar(
    opcionesOMensaje: string | {
      titulo?: string;
      mensaje: string;
      textoConfirmar?: string;
      textoCancelar?: string;
      peligro?: boolean;
    }
  ): Promise<boolean>;
}
