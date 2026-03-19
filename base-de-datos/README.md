# Base de datos — Cursos Umme

## Conexión
| Parámetro | Valor |
|-----------|-------|
| Servidor  | `db5020047845.hosting-data.io` |
| Puerto    | `3306` |
| Base de datos | `dbs15459256` |
| Usuario   | `dbs15459256` |
| Contraseña | en `.env` → `DB_CONTRASENA` |

---

## Tablas

### `usuarios`
Usuarios de la plataforma. Gestiona el login y el rol de cada persona.

| Campo       | Tipo                    | Descripción                          |
|-------------|-------------------------|--------------------------------------|
| `id`        | INT AUTO_INCREMENT      | Clave primaria                       |
| `nombre`    | VARCHAR(100)            | Nombre                               |
| `apellidos` | VARCHAR(150)            | Apellidos                            |
| `email`     | VARCHAR(200) UNIQUE     | Usado para el login                  |
| `contrasena`| VARCHAR(255)            | Hash bcrypt — nunca texto plano      |
| `rol`       | ENUM('admin','alumno')  | Determina a qué panel accede         |
| `fecha_alta`| DATE                    | Fecha de registro                    |
| `activo`    | TINYINT(1)              | 1 = puede entrar, 0 = bloqueado      |

---

## Flujo de login

```
Usuario introduce email + contraseña
        ↓
Se busca el email en tabla usuarios (WHERE activo = 1)
        ↓
Se compara la contraseña con el hash bcrypt guardado
        ↓
  rol = 'admin'  →  redirige a /admin
  rol = 'alumno' →  redirige a /inicio
  no existe / hash no coincide → error de login
```

---

## Pendiente (próximos pasos)
- [ ] Tabla `modulos` — cursos disponibles
- [ ] Tabla `asignaciones` — qué módulos tiene asignados cada alumno
- [ ] Configurar Astro en modo SSR para poder conectarse a la BD
- [ ] Instalar driver MySQL (`mysql2`) y crear helper de conexión
