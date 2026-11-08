# Cambios

## 0.9.0 — 2026-11-08

- **Reporte de pedidos** en `/gestion/pedidos/` (equipo Cheppi) y `/vendedor/pedidos/` (cada tienda solo ve lo suyo):
  - cabecera con **Imprimir** (hoja de impresión horizontal solo con la tabla), **Exportar PDF** y **Exportar Excel** (.xlsx real; CSV si el servidor no tiene ZipArchive);
  - filtros por rango de fechas (desde/hasta), estado y — solo Cheppi — tienda; los exportes respetan los filtros;
  - tabla por columnas: # pedido, tienda (Cheppi), productos comprados, dirección de facturación, dirección de envío (con celular), venta bruta, ganancia del vendedor (y comisión), fecha, «Ver más»;
  - totales del filtro completo (los cancelados no suman a la venta bruta); en móvil cada pedido se ve como ficha.
- **Filtros de productos**: buscar, categoría, tipo (simple/variable), cantidad por página (10/20/50/100) y, en gestión, tienda.
## 0.8.0 — 2026-11-08

- **Editor de productos completo** (vendedor y equipo Cheppi), producto simple o variable:
  - descripción corta y larga con editor ligero (negrita, cursiva, listas, enlaces; HTML limpiado en el servidor);
  - varias categorías, marcas de productos, etiquetas;
  - precio, oferta y oferta programada (desde/hasta), producto virtual;
  - inventario: SKU, gestionar stock, cantidad, reservas, aviso de stock bajo, estado del inventario, vendido individualmente;
  - envío: peso y medidas;
  - atributos (hasta 5) visibles en la ficha o usados para variaciones;
  - variaciones con «Generar variaciones» (todas las combinaciones), precio/stock para todas, y por variación: activa, precio, oferta, SKU, stock o estado, peso, foto y descripción (hasta 60);
  - ventas dirigidas, ventas cruzadas y nota de compra.
  El código del editor se carga solo en esa pantalla (6,7 KB).
- **Panel de gestión de Cheppi** en `/gestion/` (fuera de wp-admin, mismo diseño del panel de vendedor): inicio con pendientes y métricas, tiendas (revisar, aprobar, condiciones, documentos, página premium, alta manual), todos los productos (aprobar, devolver con motivo por correo, editar con el editor completo para cualquier tienda, crear para una tienda), todos los pedidos (mover el estado de cualquier tienda), liquidaciones, campañas y reglas. Las mismas pantallas siguen disponibles en wp-admin. Acceso solo para administradores; enlace «Gestión Cheppi» en la barra superior.
- Correos al admin ahora enlazan al panel de gestión.

## 0.7.0 — 2026-11-07

Fase 5: promociones, métricas, carga masiva y páginas de marca premium.

- **Campañas** (Cheppi → Campañas): fechas y comisión rebajada; el vendedor se une desde Promociones y sus ventas en esas fechas pagan la comisión de campaña (si es menor).
- **Cupones del vendedor**: código, % o monto por producto, compra mínima, vencimiento y usos; solo descuentan productos de esa tienda.
- **Productos destacados pagados**: 3 a 30 días a S/ 5 por día (editable en Reglas); usa el «destacado» de WooCommerce, se cobra del saldo y se apaga solo al vencer.
- **Métricas** para el vendedor (7/30/90 días: ventas, pedidos, ticket, comisión, cumplimiento, horas a envío, atrasados, más vendidos) y para el admin (GMV, comisión efectiva, top vendedores, pedidos atrasados).
- **Importar/exportar productos** por CSV (Excel/Google Sheets): plantilla, actualización por SKU, fotos por URL, informe por fila.
- **Página de marca premium** en `/marca/<tienda>/`: el vendedor la solicita; el admin elige la página diseñada en Divi 5 y puede cobrar el diseño del saldo. La tienda muestra «Conoce la marca».

## 0.6.0 — 2026-11-07

Fase 4: liquidaciones.

- **Corte automático** (WP-Cron diario, 06:00) los días configurados: junta lo liberado de cada vendedor (entregado + plazo de devolución, menos comisiones y servicios) si llega al mínimo. También «Hacer corte hoy».
- **Cheppi → Liquidaciones**: por transferir / pagadas / anuladas, CSV para transferencias (banco, titular, CCI, monto), «Pagado» con n.º de operación (avisa al vendedor por correo) y «Anular» (el dinero vuelve al saldo, sin duplicarse).
- **Estado de cuenta PDF** por liquidación, con los movimientos incluidos (generador PDF propio, sin librerías).
- **Pagos** en el panel: disponible, retenido, próximo corte, cuenta bancaria (validación de CCI de 20 dígitos), liquidaciones con PDF y movimientos.
- **Auditoría** de seguridad y velocidad en `docs/AUDIT.md`.

## 0.5.0 — 2026-11-07

Fase 3: pedidos, comisiones y envíos.

- **Libro contable por pedido**: cuando un pedido se paga (Procesando o Completado) cada línea de un vendedor registra la venta y la comisión de Cheppi, congelada al momento de la compra (vendedor > categoría > plan). El delivery cobrado por la marca se registra como suyo, sin comisión. Si el pedido se cancela, reembolsa o falla, se escriben los asientos contrarios (el libro nunca se edita). Todo es idempotente.
- **El dinero espera la entrega**: queda retenido hasta que el vendedor marca «Entregado»; desde ahí corre el plazo de devolución y luego pasa a disponible para el corte.
- **Pedidos en el panel** (`/vendedor/pedidos/`): lista con filtros por estado y detalle con productos, datos de entrega (celular con enlace a WhatsApp), estado (Nuevo → Preparando → En camino → Entregado), seguimiento y «Tu dinero» (venta, comisión, delivery, lo que recibe). Cada cambio envía una nota al cliente por correo; cuando todos los vendedores entregan, el pedido pasa a Completado.
- **Envíos de la marca** (`/vendedor/envios/`): hasta 6 zonas con distritos de Lima y Callao, tarifa, días de entrega y envío gratis desde un monto; opción «Todos los demás distritos».
- **Carrito dividido por vendedor**: un paquete de envío por marca («Envío de …») con su tarifa según el distrito; los productos de Cheppi siguen con las tarifas de WooCommerce. Funciona con el checkout de bloques; el campo Ciudad se llama «Distrito».
- **Correo al vendedor** por cada pedido nuevo y **caja «Vendedores»** en el pedido de WooCommerce con estado y comisión por vendedor.
- **Inicio**: «Por cobrar» (y disponible), «Pedidos por atender», ventas por día con total, «Más vendidos» y aviso si faltan las zonas de envío. Las ventas canceladas no cuentan.
- **Productos en cuadrícula o lista** (se recuerda la elección).
- **Página de tienda como página de WordPress** («Tiendas», `/tienda/`): el header y footer de Divi 5 cargan con sus estilos; `/tienda/` lista las tiendas aprobadas.
- **Logo original** del sitio en el panel y en el registro.
- Correcciones: los botones-enlace del panel perdían su color; columnas que se desbordaban entre 760 y 1180 px; montos que se partían en dos líneas; avatar oculto en celular.

## 0.4.0 — 2026-11-05

Fase 2: panel y productos.

- **Inicio con cifras reales**: productos por estado, stock bajo, ventas de los últimos 14 días y productos por categoría, con gráficos SVG generados en el servidor (sin librerías de gráficos).
- **Productos desde el panel** (`/vendedor/productos/`): crear, editar, buscar, filtrar por estado y eliminar productos simples y variables (una opción como tono o tamaño, hasta 30 variantes con precio, oferta, stock y SKU propios). Hasta 8 fotos por producto, convertidas a WebP y limitadas a 1600 px. Borradores sin foto; para publicar se exige al menos una.
- **Moderación** configurable en Reglas del negocio: los productos nuevos quedan «En revisión» hasta que Cheppi los apruebe. En WooCommerce → Productos hay columna «Vendedor» y acción «Aprobar y publicar»; el vendedor recibe un correo cuando su producto se publica.
- **Límite de productos por plan** al crear (Emprende 50, Pro sin límite).
- **Mi tienda** (`/vendedor/mi-tienda/`): logo, portada y presentación.
- **Página pública de la tienda** `/tienda/<nombre>/` dentro del tema, con título, descripción y canonical propios; solo tiendas aprobadas (las demás dan 404).
- **Rendimiento**: CSS separado en tokens/controles/componentes, admin en un solo bundle, conteos de vendedores en caché, consulta única de vendedores en el listado de productos del admin; la tienda pública carga solo 1,4 KB de CSS y ningún JS; el resto del sitio no carga nada del plugin.
- Correcciones encontradas en pruebas: al aprobar un producto ahora recibe su URL (antes quedaba `/product/`); no se puede quitar la última foto de un producto publicado; los errores de foto y de datos se muestran juntos.

## 0.3.0 — 2026-11-05

Gestión de vendedores desde el admin.

- **Añadir vendedor** desde wp-admin → Cheppi → Vendedores: con una cuenta existente (por correo) o creando una nueva con correo para que defina su contraseña; datos de la tienda, documentos opcionales, estado inicial (aprobada o en revisión), plan y comisión especial.
- **Editar datos** de la tienda (nombre, dirección `/tienda/…`, RUC, razón social, celular, categoría, distrito, redes) con control de RUC y dirección únicos.
- **Documentos desde el admin**: ver, subir, reemplazar y eliminar cada documento.
- **Eliminar vendedor** con confirmación escribiendo el nombre de la tienda; borra la tienda y sus documentos y conserva la cuenta de usuario. Bloqueado si la tienda tiene ventas o pagos (se suspende en su lugar).
- **Buscador** por tienda, razón social o RUC, y listado titulado «Vendedores y marcas».

## 0.2.0 — 2026-11-05

Fase 1: vendedores.

- **Sistema de diseño Cheppi**: tokens de marca (violeta, lima, noche, lavanda; Bricolage Grotesque y DM Sans) y componentes reutilizables (tarjeta de cifra, chip de estado, botones píldora, campos, carga de archivos, pasos, pendientes).
- **Panel del vendedor** en `/vendedor/`: página propia sin tema ni Divi, estilo dashboard con barra lateral (barra inferior en celular), estado de la solicitud, pendientes, plan, comisión y próximo corte.
- **Registro en 4 pasos** en `/vendedor/registro/`: cuenta, negocio, documentos y confirmación. Valida RUC (dígito verificador), celular, distrito y archivos en el navegador y en el servidor; funciona también sin JavaScript. Antispam con campo trampa y límite de intentos por hora.
- **Documentos privados** en `uploads/cheppi-private/` bloqueados para la web; solo el admin los abre, con permiso y nonce.
- **wp-admin → Cheppi → Vendedores**: cifras por estado, filtros, ficha del vendedor con documentos, y decisión (aprobar, pedir corrección, rechazar, suspender) con comentario, plan y comisión especial.
- **Correos** con la marca: solicitud recibida (vendedor y admin), cambio de estado y documentos reenviados.
- **Reenvío de documentos** por el vendedor cuando se le pide corrección.
- **Shortcodes para la página «Vende en Cheppi»**: `[cheppi_calculadora]` y `[cheppi_boton_vender]`.
- Esquema de base de datos v2: columnas nuevas en vendedores y tabla `cheppi_vendor_documents`.

## 0.1.0 — 2026-11-05

Fase 0: esqueleto del plugin.

- Núcleo de dominio: Money, BusinessRules, CommissionResolver, libro contable, PayoutSchedule, proveedores de envío, estados de vendedor.
- Pantalla Reglas de negocio en wp-admin con historial de cambios.
- API REST `cheppi/v1/rules`.
- Tablas propias, rol `cheppi_vendor` y compatibilidad con HPOS.
- Build del front con Vite + TypeScript, sin jQuery.
