---
name: Arquitectura de subida de archivos (raw stream)
description: Cómo funciona el upload de vídeos y documentos, por qué no usar multipart/form-data y qué falló antes
type: feedback
---

# Subida de archivos — raw body streaming

Tanto vídeos como documentos se suben con **cuerpo crudo** (`Content-Type: application/octet-stream`) y metadata en los parámetros de la URL. No se usa `multipart/form-data`.

**Why:** IONOS shared hosting tiene cuota de disco que se excedía porque PHP escribía primero en `/tmp` (espacio compartido del servidor) y luego copiaba al destino web — doble escritura. Con raw body el archivo se escribe una sola vez directamente al destino, sin tocar `/tmp`.

## Protocolo actual

### Vídeos → `upload-chunk.php`
- Todos los vídeos (sin importar tamaño) usan subida por chunks
- Chunk size: 800 MB (límite nginx de IONOS es 1 GB)
- Metadata en query string: `upload_id`, `chunk_index`, `total_chunks`, `total_size`, `tema_id`, `nombre_original`, `duracion_seg` (solo último chunk)
- Cuerpo: bytes crudos del chunk
- El servidor escribe chunks sobre `uploads/videos/{upload_id}.part` (append)
- Al recibir el último chunk: `rename()` al nombre final
- Limpieza automática de `.part` huérfanos > 6 horas al iniciar cada nuevo upload

### Documentos → `upload.php`
- Metadata en query string: `tipo`, `tema_id`, `nombre_original`
- Cuerpo: bytes crudos del archivo completo
- El servidor escribe directamente en `uploads/documentos/` leyendo desde `php://input`

## Lo que NO hacer

- **No usar `multipart/form-data`** para uploads al servidor IONOS — causa `move_uploaded_file(): Unable to move` o `mkdir(): Disk quota exceeded` porque dobla el espacio necesario temporalmente
- **No usar `.tmp-uploads/`** — el antiguo sistema de chunks guardaba partes en `uploads/.tmp-uploads/{upload_id}/N.part` y luego las ensamblaba; dejaba directorios huérfanos y consumía cuota extra
- **No confiar en `$_FILES`** para subidas grandes — en IONOS, el límite de `/tmp` o la cuota de la cuenta puede hacer que `move_uploaded_file()` falle incluso si el archivo llegó correctamente

## How to apply

Si se añade un nuevo tipo de upload (imágenes de materiales, adjuntos, etc.), usar siempre raw body + URL params siguiendo el mismo patrón de `upload.php` o `upload-chunk.php`.
