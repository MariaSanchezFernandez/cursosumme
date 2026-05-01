---
name: Restricciones del hosting IONOS
description: Limitaciones técnicas del hosting compartido de IONOS que afectan decisiones de arquitectura
type: reference
---

# Restricciones del hosting IONOS (shared hosting)

## Límites conocidos

- **nginx**: body máximo de **1 GB** por petición — por eso los vídeos se suben en chunks de 800 MB
- **Cuota de cuenta**: aunque el panel muestra "ilimitado", hay una cuota real a nivel de SO. Con ~50 GB usados, el doble-write de PHP (`/tmp` + destino) puede excederla y causar `Disk quota exceeded`
- **`/tmp`**: espacio compartido entre usuarios del servidor, con cuota separada de la cuenta web
- **`upload_tmp_dir`**: es `PHP_INI_SYSTEM` — no se puede cambiar desde `.user.ini` ni `ini_set()`, solo el admin del servidor puede cambiarlo
- **Composer**: no disponible en el hosting compartido — las dependencias PHP deben incluirse manualmente
- **`exec()` y funciones de shell**: probablemente bloqueadas en el plan compartido
- **Inodos**: límite de **262.144 archivos** por cuenta (visible en el panel IONOS → Espacio web → Archivos)

## Configuración PHP activa (`.user.ini`)

```ini
upload_max_filesize = 3584M
post_max_size       = 3584M
memory_limit        = 512M
max_execution_time  = 600
max_input_time      = 600
```

Nota: `upload_max_filesize` y `post_max_size` ya no son el límite efectivo para vídeos y documentos porque usamos raw body streaming (`php://input`), no multipart. El límite real para vídeos es el chunk de 800 MB que impone nginx.

## Cómo aplicar

- Cualquier upload debe usar **raw body streaming** (`php://input`) para evitar el doble-write y los problemas de cuota. Ver `feedback-uploads.md`.
- No diseñar features que requieran Composer o comandos de shell.
- Vigilar el contador de inodos si se suben muchos archivos pequeños.
- El SFTP_REMOTE_PATH correcto es `/` — la raíz del SFTP es la raíz web del dominio.
