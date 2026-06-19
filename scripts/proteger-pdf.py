# Cifra presentacion.pdf con contraseña de apertura -> presentacion-protegido.pdf
# Uso: python3 scripts/proteger-pdf.py [contraseña]
import sys
from pypdf import PdfReader, PdfWriter

password = sys.argv[1] if len(sys.argv) > 1 else "mariamaria123"
src = "presentacion.pdf"
dst = "presentacion-protegido.pdf"

reader = PdfReader(src)
writer = PdfWriter()
for page in reader.pages:
    writer.add_page(page)

writer.encrypt(user_password=password, owner_password=password, algorithm="AES-256")
with open(dst, "wb") as f:
    writer.write(f)

print(f"Cifrado OK -> {dst} (contraseña: {password})")
