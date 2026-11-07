# Auditoría de seguridad y velocidad — 0.7.0 (2026-11-07)

Revisión hecha sobre el código del plugin y en vivo en cheppimarket.ccrea.pe.

## Seguridad

| Punto | Estado |
| --- | --- |
| Formularios (`admin-post.php`) | Los 31 manejadores verifican nonce. Los de admin exigen `manage_cheppi_marketplace`; los del panel, un vendedor aprobado con sesión. Solo el registro acepta visitantes (`nopriv`), con nonce, campo trampa y límite por hora. |
| Propiedad de los datos | Producto, pedido, cupón, destacado y estado de cuenta se buscan siempre filtrando por el vendedor de la sesión; abrir el de otro devuelve «no encontrado» o 403 (probado). |
| SQL | Todas las consultas con datos usan `$wpdb->prepare`; las dos sin `prepare` son constantes (`GROUP BY status`). |
| Salida | Las plantillas escapan con `esc_html`/`esc_attr`/`esc_url`; el HTML de imágenes viene de funciones del núcleo. |
| Archivos | Documentos de vendedores en `uploads/cheppi-private/` (403 desde la web, probado). Fotos: tipo y tamaño validados, convertidas a WebP. Importación CSV: 2 MB, 100 filas, 30 fotos por archivo; las fotos por URL se descargan con `download_url` (bloquea IP privadas). |
| CSV exportados | Celdas que empiezan con `= + - @` se neutralizan (inyección de fórmulas en Excel). |
| Dinero | El libro contable no se edita: anulaciones y cancelaciones escriben asientos contrarios. Cortes idempotentes por vendedor y día. La comisión se congela en cada línea al comprar. |
| Datos bancarios | Se muestran enmascarados al vendedor (CCI •••• 1234) y completos solo al administrador y en su CSV. |
| Páginas personales | `/vendedor/*` envía `no-store`, `X-LiteSpeed-Cache-Control: no-cache` y `noindex` (probado). |

## Velocidad (medido)

| Página | TTFB | Recursos del plugin |
| --- | --- | --- |
| Inicio, tienda, ficha de producto | 0,5–0,9 s (sin caché de página) | ninguno |
| `/tienda/<marca>/` | ~0,47 s | 1,6 KB de CSS, 0 JS |
| Panel `/vendedor/*` | ~0,40–0,45 s | 5,6 KB CSS + 2,5 KB JS (comprimidos) |

- Gráficos en SVG generados en el servidor: sin librerías de gráficos.
- Campañas y reglas en opciones autocargadas (0 consultas extra en el checkout); vendedores del carrito en una sola consulta.
- Índices propios en libro contable (`vendor_id, available_at`), pedidos por vendedor (`vendor_id, status, created_at`) y liquidaciones.

## Pendiente para el lanzamiento (requiere tu decisión)

1. **LiteSpeed Cache está instalado pero inactivo.** Recomendado: activarlo con caché de página para visitantes, excluir `/vendedor/`, `/carrito/`, `/checkout/`, `/mi-cuenta/` (el plugin ya envía `no-cache` en el panel) y activar «Guest Mode» y WebP. Bajaría el TTFB público a ~0,1 s.
2. **Cron real**: en hPanel → Avanzado → Cron jobs, llamar `wp-cron.php` cada 15 min, para que el corte de las 06:00 no dependa de visitas.
3. **Métodos de pago y envío de Cheppi** en WooCommerce (hoy no hay pasarela activa ni tarifa para los productos propios).
4. **SMTP** para que los correos (pedidos, pagos) no caigan en spam.
