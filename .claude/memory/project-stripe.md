# Plan integración Stripe

## Objetivo

Cuando un usuario completa un pago en Stripe, se crea automáticamente su cuenta de alumno en la BD con acceso a los cursos comprados. Las credenciales se envían por email (SMTP de IONOS).

## Estado actual

- **Pendiente de implementar** — la integración aún no existe en el código
- Los cursos YA existen en la BD (`cursos` table), solo hay que añadirles precio y el ID de producto/precio de Stripe
- El email de IONOS ya está creado por la usuaria (falta confirmar dirección y credenciales SMTP)

## Preguntas pendientes (necesarias antes de implementar)

1. **Stripe keys** — necesita pasar:
   - `STRIPE_SECRET_KEY` (sk_live_... o sk_test_...)
   - `STRIPE_WEBHOOK_SECRET` (whsec_..., se obtiene al crear el webhook en el panel de Stripe)
2. **Email IONOS** — dirección y contraseña del email creado específicamente para esto
   - SMTP host probable: `smtp.ionos.es`, puerto `587`
3. **Modelo de venta** — ¿cursos individuales, packs, o ambos?
4. **Contraseña inicial** — ¿aleatoria por email (más seguro) o fija como `Umme@2024`?

## Flujo técnico acordado

```
Usuario selecciona curso(s) → pulsa "Comprar"
→ stripe-checkout.php crea sesión Stripe Checkout
→ Stripe redirige a su página de pago segura
→ Usuario paga
→ Stripe llama al webhook (stripe-webhook.php)
→ webhook crea alumno en BD + asigna cursos + envía email con credenciales
→ Usuario llega a /pago-ok con mensaje de confirmación
```

## Archivos a crear

| Archivo | Descripción |
|---|---|
| `public/api/stripe-checkout.php` | Crea sesión Stripe Checkout (recibe cursos seleccionados) |
| `public/api/stripe-webhook.php` | Recibe evento `checkout.session.completed` de Stripe, crea alumno |
| `src/pages/precios.astro` | Página pública con cursos/packs y botón de compra |
| `src/pages/pago-ok.astro` | Página de confirmación tras pago exitoso |
| `src/pages/pago-ko.astro` | Página de error si el pago falla o se cancela |

## Cambios en BD necesarios

```sql
-- Añadir precio y stripe_price_id a cursos
ALTER TABLE cursos ADD COLUMN precio DECIMAL(8,2) DEFAULT NULL;
ALTER TABLE cursos ADD COLUMN stripe_price_id VARCHAR(100) DEFAULT NULL;

-- Nueva tabla para registrar pagos
CREATE TABLE pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stripe_session_id VARCHAR(200) NOT NULL UNIQUE,
  email VARCHAR(200) NOT NULL,
  cursos_ids TEXT NOT NULL,       -- JSON array de IDs
  alumno_id INT DEFAULT NULL,     -- NULL hasta que se crea el alumno
  estado ENUM('pendiente','completado','fallido') NOT NULL DEFAULT 'pendiente',
  importe DECIMAL(8,2),
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

## Dependencias PHP necesarias

- `stripe/stripe-php` vía Composer (o incluir el SDK manualmente si el hosting no tiene Composer)
- PHP `mail()` o SMTP con PHPMailer para enviar credenciales

## Notas de arquitectura

- El webhook debe verificar la firma de Stripe con `STRIPE_WEBHOOK_SECRET` para evitar fraudes
- La contraseña generada aleatoriamente se hashea con **bcrypt** (`password_hash` + `PASSWORD_BCRYPT`, cost 12), igual que el resto del sistema. Ver `public/api/login.php` y `public/api/cambiar-password.php`.
- Si el email ya existe en BD, no crear alumno duplicado — añadir los cursos nuevos al alumno existente
- El hosting es IONOS shared hosting — verificar si permite `exec()` o Composer; si no, incluir stripe-php manualmente
