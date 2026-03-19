export type Rol = 'admin' | 'alumno';

export interface Usuario {
  id: string;
  nombre: string;
  apellidos: string;
  email: string;
  rol: Rol;
  modulosAsignados: string[]; // ids de cursos
  fechaAlta: string;
}

export const usuarios: Usuario[] = [
  {
    id: 'rocio',
    nombre: 'Rocío',
    apellidos: 'Fernandez',
    email: 'rocio@cursosumme.com',
    rol: 'admin',
    modulosAsignados: [],
    fechaAlta: '2024-01-01',
  },
  {
    id: 'a1',
    nombre: 'Ana',
    apellidos: 'García López',
    email: 'ana@ejemplo.com',
    rol: 'alumno',
    modulosAsignados: ['c1', 'c3'],
    fechaAlta: '2024-03-10',
  },
  {
    id: 'a2',
    nombre: 'María',
    apellidos: 'Martínez Ruiz',
    email: 'maria@ejemplo.com',
    rol: 'alumno',
    modulosAsignados: ['c2'],
    fechaAlta: '2024-04-05',
  },
  {
    id: 'a3',
    nombre: 'Carmen',
    apellidos: 'Sánchez Torres',
    email: 'carmen@ejemplo.com',
    rol: 'alumno',
    modulosAsignados: ['c1', 'c2', 'c4'],
    fechaAlta: '2024-04-18',
  },
  {
    id: 'a4',
    nombre: 'Lucía',
    apellidos: 'Fernández Gil',
    email: 'lucia@ejemplo.com',
    rol: 'alumno',
    modulosAsignados: ['c5', 'c6'],
    fechaAlta: '2024-05-02',
  },
  {
    id: 'a5',
    nombre: 'Isabel',
    apellidos: 'Romero Vega',
    email: 'isabel@ejemplo.com',
    rol: 'alumno',
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
