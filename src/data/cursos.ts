export interface Tema {
  titulo: string;
  duracion: string;
}

export interface Curso {
  id: string;
  titulo: string;
  etiqueta: string;
  subtemas: number;
  duracion: string;
  nivel: string;
  acento: 'c1' | 'c2' | 'c3' | 'c4';
  temas: Tema[];
}

export const cursos: Curso[] = [
  {
    id: 'c1',
    titulo: 'Lorem ipsum dolor sit amet consectetur',
    etiqueta: 'Categoria A',
    subtemas: 4,
    duracion: '4 h 30 min',
    nivel: 'Nivel basico',
    acento: 'c1',
    temas: [
      { titulo: 'Lorem ipsum dolor sit amet', duracion: '45 min' },
      { titulo: 'Consectetur adipiscing elit sed', duracion: '30 min' },
      { titulo: 'Ut enim ad minim veniam quis', duracion: '1 h 10 min' },
      { titulo: 'Duis aute irure dolor reprehenderit', duracion: '55 min' },
    ],
  },
  {
    id: 'c2',
    titulo: 'Consectetur adipiscing elit sed do eiusmod',
    etiqueta: 'Categoria B',
    subtemas: 3,
    duracion: '2 h 15 min',
    nivel: 'Nivel intermedio',
    acento: 'c2',
    temas: [
      { titulo: 'Excepteur sint occaecat cupidatat', duracion: '40 min' },
      { titulo: 'Non proident sunt in culpa officia', duracion: '50 min' },
      { titulo: 'Deserunt mollit anim id est laborum', duracion: '45 min' },
    ],
  },
  {
    id: 'c3',
    titulo: 'Sed do eiusmod tempor incididunt ut labore',
    etiqueta: 'Categoria A',
    subtemas: 6,
    duracion: '8 h 00 min',
    nivel: 'Nivel avanzado',
    acento: 'c3',
    temas: [
      { titulo: 'Lorem ipsum dolor sit amet', duracion: '1 h 20 min' },
      { titulo: 'Consectetur adipiscing elit do', duracion: '1 h 00 min' },
      { titulo: 'Sed eiusmod tempor incididunt', duracion: '1 h 30 min' },
      { titulo: 'Ut labore et dolore magna aliqua', duracion: '1 h 10 min' },
      { titulo: 'Quis nostrud exercitation ullamco', duracion: '1 h 00 min' },
      { titulo: 'Laboris nisi ut aliquip commodo', duracion: '1 h 00 min' },
    ],
  },
  {
    id: 'c4',
    titulo: 'Incididunt ut labore et dolore magna aliqua',
    etiqueta: 'Categoria C',
    subtemas: 2,
    duracion: '1 h 45 min',
    nivel: 'Nivel basico',
    acento: 'c4',
    temas: [
      { titulo: 'Duis aute irure dolor reprehenderit', duracion: '50 min' },
      { titulo: 'Voluptate velit esse cillum dolore', duracion: '55 min' },
    ],
  },
  {
    id: 'c5',
    titulo: 'Quis nostrud exercitation ullamco laboris',
    etiqueta: 'Categoria B',
    subtemas: 5,
    duracion: '5 h 20 min',
    nivel: 'Nivel intermedio',
    acento: 'c1',
    temas: [
      { titulo: 'Lorem ipsum dolor sit amet', duracion: '1 h 00 min' },
      { titulo: 'Ut enim ad minim veniam quis', duracion: '1 h 00 min' },
      { titulo: 'Nostrud exercitation ullamco laboris', duracion: '1 h 10 min' },
      { titulo: 'Nisi ut aliquip ex ea commodo', duracion: '1 h 00 min' },
      { titulo: 'Consequat duis aute irure dolor', duracion: '1 h 10 min' },
    ],
  },
  {
    id: 'c6',
    titulo: 'Dolore magna aliqua ut enim ad minim',
    etiqueta: 'Categoria C',
    subtemas: 3,
    duracion: '3 h 10 min',
    nivel: 'Nivel avanzado',
    acento: 'c2',
    temas: [
      { titulo: 'Excepteur sint occaecat cupidatat', duracion: '1 h 00 min' },
      { titulo: 'Non proident sunt in culpa qui', duracion: '1 h 05 min' },
      { titulo: 'Officia deserunt mollit anim laborum', duracion: '1 h 05 min' },
    ],
  },
];
