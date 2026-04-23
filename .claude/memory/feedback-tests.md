# Tests E2E con Playwright

En CursosUmme usamos Playwright para tests E2E de los flujos críticos (login, crear/editar curso, subir vídeo, crear ticket, etc.).

**Why:** La usuaria (solo dev) quiere detectar errores ella misma antes de que lleguen a producción, sin depender de que los alumnos reporten fallos. Ya tuvimos un caso de fallo de subida desde otro PC que no se registró en el log.

**How to apply:**
- Cada nueva funcionalidad o flujo crítico debe incluir al menos un test E2E del "happy path" en `cursosumme/tests/e2e/`.
- Si se modifica un flujo existente con test, actualizar también el test.
- Recordárselo al añadir features — forma parte del "done".
- Correr `npx playwright test` antes de desplegar cambios grandes.
- Los tests deben usar un usuario de prueba dedicado (no datos reales de alumnos/cursos).
