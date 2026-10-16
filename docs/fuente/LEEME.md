# Fuentes de los manuales de APEX

Los manuales en PDF de la carpeta `docs/` se generan a partir de los archivos HTML de esta carpeta.

| Archivo | Contenido |
|---|---|
| `01_manual_administrador.html` … `07_seguridad.html` | Texto de cada manual |
| `manual.css` | Diseño común (portada, índice, recuadros, tablas, capturas) |
| `manual.js` | Numera capítulos y arma el índice con enlaces |
| `generar_pdf.js` | Convierte los HTML en PDF (tamaño carta, pie de página con numeración) |
| `capturas.js` | Toma las capturas de pantalla de `docs/img` |
| `marcadores.py` | Agrega los marcadores (panel de navegación) a los PDF |

## Regenerar los PDF

Requisitos: Node.js, Microsoft Edge o Google Chrome, y el paquete `playwright-core`.

```
npm install playwright-core
node docs/fuente/generar_pdf.js            (todos)
node docs/fuente/generar_pdf.js 02         (solo el que empieza por 02)
```

Opcional, para los marcadores del PDF (requiere Python y `pip install pymupdf`):

```
python docs/fuente/marcadores.py docs/APEX_0*.pdf
```

## Actualizar las capturas de pantalla

Las capturas deben tomarse en una **copia del sistema con datos de demostración**, nunca en la instalación de producción:

1. Copia la carpeta del sistema a `htdocs/ApexDocs`, cambia en su `.env` `APP_URL=http://localhost/ApexDocs` y `DB_NAME=apex_docs_db`.
2. Crea esa base con `database/schema.sql` (cambiando el nombre de la base) y carga los datos con `php database/seed_demo.php`.
3. Ajusta en `capturas.js` los identificadores de los registros (constante `IDS`) y ejecuta `node docs/fuente/capturas.js docs/img`.
4. Elimina la copia y su base de datos al terminar.

Los manuales describen la versión 1.3. Si cambia un módulo, actualiza el HTML correspondiente y regenera los PDF.
