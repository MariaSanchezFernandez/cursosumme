/**
 * usuarios.ts
 * -------------------------------------------------------
 * Datos de usuarios del sistema.
 *
 * Las contraseñas se almacenan como hashes SHA-256.
 * NUNCA almacenar contraseñas en texto plano.
 *
 * Para generar el hash de una nueva contraseña ejecuta:
 *   npm run hash-password
 * -------------------------------------------------------
 */

export type Rol = 'admin' | 'alumno';

export interface Usuario {
  id: string;
  nombre: string;
  apellidos: string;
  email: string;
  rol: Rol;
  /** Hash SHA-256 de la contraseña de acceso */
  hashAcceso: string;
  modulosAsignados: string[]; // ids de cursos
  fechaAlta: string;
}

/**
 * Contraseñas iniciales (cámbialas tras el primer acceso):
 *   Rocío (admin) → Umme@Admin24
 *   Alumnos       → Umme@2024
 *
 * Para cambiar una contraseña:
 *   1. Ejecuta: npm run hash-password
 *   2. Sustituye el passwordHash correspondiente por el nuevo hash.
 *   3. Vuelve a hacer deploy: npm run deploy
 */
export const usuarios: Usuario[] = [
  {
    id: 'rocio',
    nombre: 'Rocío',
    apellidos: 'Fernandez',
    email: 'rocio@cursosumme.com',
    rol: 'admin',
    // Contraseña inicial: Umme@Admin24
    hashAcceso: '22e87b18cceccce65108b355e5ff15e6b25141cae9ce1ddebcfa9cd720bb7dac',
    modulosAsignados: [],
    fechaAlta: '2024-01-01',
  },
  {
    id: 'a1',
    nombre: 'Ana',
    apellidos: 'García López',
    email: 'ana@ejemplo.com',
    rol: 'alumno',
    // Contraseña inicial: Umme@2024
    hashAcceso: '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa',
    modulosAsignados: ['c1', 'c3'],
    fechaAlta: '2024-03-10',
  },
  {
    id: 'a2',
    nombre: 'María',
    apellidos: 'Martínez Ruiz',
    email: 'maria@ejemplo.com',
    rol: 'alumno',
    // Contraseña inicial: Umme@2024
    hashAcceso: '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa',
    modulosAsignados: ['c2'],
    fechaAlta: '2024-04-05',
  },
  {
    id: 'a3',
    nombre: 'Carmen',
    apellidos: 'Sánchez Torres',
    email: 'carmen@ejemplo.com',
    rol: 'alumno',
    // Contraseña inicial: Umme@2024
    hashAcceso: '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa',
    modulosAsignados: ['c1', 'c2', 'c4'],
    fechaAlta: '2024-04-18',
  },
  {
    id: 'a4',
    nombre: 'Lucía',
    apellidos: 'Fernández Gil',
    email: 'lucia@ejemplo.com',
    rol: 'alumno',
    // Contraseña inicial: Umme@2024
    hashAcceso: '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa',
    modulosAsignados: ['c5', 'c6'],
    fechaAlta: '2024-05-02',
  },
  {
    id: 'a5',
    nombre: 'Isabel',
    apellidos: 'Romero Vega',
    email: 'isabel@ejemplo.com',
    rol: 'alumno',
    // Contraseña inicial: Umme@2024
    hashAcceso: '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa',
    modulosAsignados: [],
    fechaAlta: '2024-05-20',
  },
];

export function getAlumnos(): Usuario[] {
  return usuarios.filter(u => u.rol === 'alumno');
}

export function getUsuarioByEmail(email: string): Usuario | undefined {
  return usuarios.find(u => u.email.toLowerCase() === email.toLowerCase());
}

export function getUsuarioById(id: string): Usuario | undefined {
  return usuarios.find(u => u.id === id);
}
