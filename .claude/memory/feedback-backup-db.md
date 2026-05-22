---
name: feedback-backup-db
description: Antes de cualquier cambio en la BD hay que hacer un backup con npm run backup-db
metadata:
  type: feedback
---

Antes de ejecutar cualquier migración, modificar la estructura de la BD, o borrar/modificar datos de forma masiva, hacer siempre un backup:

```bash
npm run backup-db
```

**Why:** La BD contiene datos reales de alumnas (accesos, progreso, pagos). Un error en una migración sin backup puede ser irreversible.

**How to apply:** Cada vez que se vaya a:
- Ejecutar `npm run migrar`
- Añadir/eliminar columnas o tablas manualmente
- Borrar registros en masa (alumnos, cursos, materiales)
- Hacer cualquier `ALTER TABLE` o `DROP TABLE`

…recordar hacer el backup primero. El archivo se guarda en `backups/` (gitignoreado) y se mantienen los últimos 10 automáticamente.
