# Deploy con `npm run deploy`

El despliegue está automatizado: [scripts/deploy.mjs](../../scripts/deploy.mjs) hace `npm run build` y sube `dist/` entero al servidor IONOS vía SFTP usando `ssh2-sftp-client.uploadDir`.

## Comando

```bash
npm run deploy
```

Lee credenciales de `cursosumme/.env` (variables `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PASS`, `SFTP_REMOTE_PATH`). Si `SFTP_PASS` no está, las pide por terminal.

## Reglas importantes

- **Siempre `npm run deploy`** — no hacer `put` manual. El script sube TODO `dist/` de forma atómica.
- Tras cualquier cambio en `.astro` o layouts, los hashes en `dist/_astro/` cambian. El script ya sube el `_astro/` completo, así que no hay que pensarlo.
- Para desplegar solo cambios PHP, aun así es preferible `npm run deploy` completo — es rápido y evita inconsistencias.
- **Nunca** subir `.env`, `db-config.php` ni nada de `uploads/` al repo (están en `.gitignore`).

## Si falla el deploy

- Verificar conexión (`.env` actualizado con el usuario SFTP vigente)
- Mirar `test-results/` o consola — el script imprime el error exacto
- Como último recurso, `sftp` manual al host — los datos están en `.env`
