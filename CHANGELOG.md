# Cambios

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
