# Presentación TFM — CursosUmme

Guion completo para las slides. Cada bloque `## Slide N` es una diapositiva.
El texto en viñetas va EN la slide (poco texto, frases cortas). El bloque
**🎤 Narración** son tus notas de orador: lo que dices, no lo que se ve.

Sugerencia de herramienta: Google Slides (gratis, fácil de compartir por URL)
o Gamma (genera el diseño a partir de este guion casi solo).

---

## Slide 1 — Portada

- **CursosUmme**
- Plataforma e-learning con venta, DRM y panel de gestión propios
- Trabajo de Fin de Máster
- María Sánchez Fernández · José Manuel Borrás Rodríguez
- 🌐 cursosumme.es
- *(logo de CursosUmme)*

**🎤 Narración:** "Os presento CursosUmme, una plataforma de formación online que hemos construido de cero y que ya está en producción vendiendo cursos reales."

---

## Slide 2 — El problema

- La academia Umme quería vender y servir su formación online…
- …sin depender de plataformas tipo Udemy/Hotmart que:
  - Se llevan una comisión de cada venta
  - Limitan el control sobre el contenido y el alumnado
  - No protegen el vídeo frente a descargas/piratería
- Necesitaba: **vender + cobrar + servir vídeo protegido + autogestionarse**, todo propio.

**🎤 Narración:** "El punto de partida es un problema de negocio real: una academia que no quería ceder el control ni la comisión a un tercero, y que necesitaba proteger su contenido en vídeo."

---

## Slide 3 — La solución (visión en una frase)

- Una plataforma **end-to-end** que cubre todo el ciclo:
  1. **Capta y vende** — web comercial + checkout con Stripe
  2. **Da de alta sola** — webhook crea la cuenta y envía credenciales
  3. **Sirve protegido** — vídeo con DRM y marca de agua
  4. **Se autogestiona** — panel de administración completo
- Una sola persona administra toda la academia.

**🎤 Narración:** "La solución cubre las cuatro fases del ciclo, de la venta a la gestión, sin intervención manual entre el pago y el acceso del alumno."

---

## Slide 4 — Demo / capturas

- *(2-3 capturas: landing, reproductor con marca de agua, panel admin)*
- Recorrido en vivo si hay tiempo: compra → email → login → ver curso.

**🎤 Narración:** "Os enseño el flujo real: así se compra, así llega el email con las credenciales, y así se ve un curso con el vídeo protegido."

> 💡 Graba un GIF/vídeo corto del flujo de compra por si la demo en vivo falla.

---

## Slide 5 — Arquitectura

- **Frontend:** Astro v5 + TypeScript (sitio estático + JS puntual)
- **Backend:** API REST en PHP 8 + PDO (un endpoint por recurso)
- **BD:** MySQL/MariaDB en IONOS
- **Vídeo:** VdoCipher (DRM)  ·  **Pagos:** Stripe  ·  **Email:** SMTP
- *(diagrama: Navegador → API PHP → MySQL; + VdoCipher y Stripe a los lados)*

**🎤 Narración:** "La arquitectura es deliberadamente simple y sin framework de backend: cada recurso es un endpoint PHP que valida sesión, habla con MySQL y devuelve JSON. La complejidad la aportan las integraciones: DRM y pagos."

---

## Slide 6 — Seguridad (el punto fuerte)

- Contraseñas con **bcrypt** (cost 12) + migración transparente desde SHA-256
- **Sesiones por dispositivo** (tabla `sesiones`, token `X-Token`)
- **Rate limiting** de login por IP
- **DRM Widevine + FairPlay** + marca de agua dinámica con el email
- Webhook de Stripe con **verificación de firma** e idempotencia
- Cumplimiento **RGPD** + evidencia de renuncia al desistimiento
- Secretos **fuera del repositorio** (.gitignore)

**🎤 Narración:** "La seguridad fue una prioridad transversal. Destaco tres cosas: el vídeo está protegido con DRM real, el webhook de pagos verifica firma para que nadie se fabrique accesos gratis, y ningún secreto vive en el repositorio."

---

## Slide 7 — Retos técnicos resueltos

- **Subida de vídeos de hasta 3,5 GB** en hosting compartido → subida por *chunks* con *raw body streaming* (sin `/tmp`, evitando la cuota de disco)
- **Apache elimina headers** en ciertas rutas → token también por `?token=`
- **DRM y compositing del navegador** → un `overflow:hidden` mal puesto rompía la protección anti-captura; se rediseñó el contenedor
- **Pago → acceso sin intervención** → webhook idempotente que crea cuenta, asigna cursos y envía email

**🎤 Narración:** "Estos son los problemas que de verdad costaron y que muestran resolución propia, no solo seguir un tutorial."

---

## Slide 8 — Buenas prácticas y testing

- **Tests E2E con Playwright** de los flujos críticos (login, uploads, autorización de vídeo, estadísticas, responsive)
- **Migraciones idempotentes** de BD versionadas
- **Backups automáticos** antes de tocar datos (se conservan los últimos 10)
- **Auditoría**: log de cambios + captura global de errores JS del frontend
- Despliegue **automatizado** (un comando: `npm run deploy`)

**🎤 Narración:** "Trabajamos como un proyecto real: tests automáticos, migraciones, backups y despliegue de un solo comando. El log de errores nos avisa de fallos en cliente sin esperar a que los reporte el alumnado."

---

## Slide 9 — Uso de IA en el desarrollo

- Gran parte del código se generó con **IA (Claude Code)** como par de programación
- Nuestro papel: **dirigir, estructurar, revisar y validar** cada pieza
- La IA aceleró: endpoints repetitivos, tests, refactors, documentación
- Las decisiones de arquitectura, seguridad y negocio son **nuestras**
- *(Honestidad: la IA es la herramienta de desarrollo, no una función del producto)*

**🎤 Narración:** "Aplicamos lo aprendido en el máster sobre IA: la usamos intensamente para construir, pero supervisando y validando. La IA escribió mucho código; nosotros decidimos qué código y por qué."

---

## Slide 10 — Estado y futuro

- ✅ **En producción** en cursosumme.es (HTTPS)
- ✅ Pagos, DRM, panel y soporte funcionando
- 🔜 Stripe a modo *live* para cobrar en real
- 🔜 Facturación automática a partir de los datos fiscales que ya se guardan
- 🔜 Más analítica de progreso del alumnado

**🎤 Narración:** "El proyecto ya está vivo. Lo siguiente es pasar Stripe a producción real y aprovechar los datos fiscales que ya capturamos para emitir facturas automáticas."

---

## Slide 11 — Cierre

- **CursosUmme** — de un problema de negocio real a un producto en producción
- Gracias 🙌
- 🌐 cursosumme.es · 📧 sanchez.fdez.maria@gmail.com

**🎤 Narración:** "En resumen: partimos de una necesidad real y la convertimos en un producto completo, seguro y desplegado. Gracias."

---

## Checklist antes de presentar

- [ ] Pasar este guion a Google Slides / Gamma y dar formato con la marca de Umme
- [ ] Añadir capturas reales (landing, reproductor, panel admin)
- [ ] Grabar un GIF/vídeo del flujo de compra como plan B de la demo
- [ ] Ensayar para que quepa en el tiempo asignado
- [ ] Subir las slides y obtener la URL pública para el formulario
