/**
 * hash-password.mjs
 * -------------------------------------------------
 * Herramienta para generar el hash SHA-256 de una
 * contraseña nueva, listo para pegar en usuarios.ts.
 *
 * Uso:
 *   npm run hash-password
 * -------------------------------------------------
 */

import { createHash } from 'crypto';
import * as readline from 'readline';

const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

function preguntar(prompt) {
  return new Promise((resolve) => {
    process.stdout.write(prompt);
    process.stdin.setRawMode?.(true);
    let input = '';
    process.stdin.on('data', function handler(char) {
      char = char.toString();
      if (char === '\r' || char === '\n') {
        process.stdin.setRawMode?.(false);
        process.stdin.removeListener('data', handler);
        process.stdout.write('\n');
        rl.close();
        resolve(input);
      } else if (char === '\u0003') {
        process.stdout.write('\n');
        process.exit(0);
      } else if (char === '\u007f') {
        input = input.slice(0, -1);
      } else {
        input += char;
      }
    });
    process.stdin.resume();
  });
}

console.log('\n── Generador de hash de contraseña ─────────────────');
console.log('El resultado es un hash SHA-256 que puedes pegar');
console.log('en el campo "hashAcceso" de usuarios.ts.\n');

const pass = await preguntar('Nueva contraseña: ');

if (!pass) {
  console.error('La contraseña no puede estar vacía.');
  process.exit(1);
}

const hash = createHash('sha256').update(pass).digest('hex');

console.log('\n── Hash generado ────────────────────────────────────');
console.log(hash);
console.log('\nPega este valor en el campo hashAcceso del usuario');
console.log('en src/data/usuarios.ts y vuelve a hacer deploy.\n');
