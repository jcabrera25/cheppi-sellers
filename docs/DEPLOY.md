# Publicar una versión en cheppimarket.ccrea.pe

El sitio está en Hostinger (cuenta `u601376748`, instalación WordPress `31205727`).
Mientras el marketplace no tenga vendedores ni pedidos reales se trabaja directo en el sitio;
cuando entren, se pasa a una copia Staging.

## Pasos

1. Sube la versión en `cheppi-sellers.php` (cabecera y `CHEPPI_SELLERS_VERSION`) y anota los cambios en `CHANGELOG.md`.
2. `bin/build-zip.sh` → corre las pruebas, compila el front y genera `dist/cheppi-sellers-<versión>.zip`.
3. Sube el contenido del zip a `wp-content/plugins/cheppi-sellers-upload/` con la API de archivos de Hostinger
   (`hosting_files_generate-upload-url`, protocolo TUS).
4. Despliega con `wordpress_plugins_deploy`: `slug = cheppi-sellers`, `plugin_path = cheppi-sellers-upload`.
   Hostinger mueve la versión anterior a `cheppi-sellers-old-<id>` y activa la nueva.
5. Verifica: `wordpress_plugins_list-installed` (activo y con la versión nueva),
   el sitio responde 200 y la pantalla **Cheppi → Reglas de negocio** carga sin errores de consola.
6. Commit en `main` con el mensaje `release: <versión>`.

## Cuidados

- No subir directo a `wp-content/plugins/cheppi-sellers/`: el despliegue necesita una carpeta de origen distinta.
- Las carpetas `cheppi-sellers-old-*` quedan como respaldo; bórralas desde wp-admin → Plugins cuando la nueva versión esté probada.
- LiteSpeed Cache sigue apagado durante el desarrollo.
- Los cambios de esquema se aplican solos: `Installer::maybeUpgrade()` compara `cheppi_sellers_db_version`.
