# Cheppi Sellers

Marketplace multivendedor para Cheppi Market (WordPress + WooCommerce), escrito para convertirse más adelante en una plataforma propia sin reescribir la lógica.

**Versión:** 0.1.0 — Fase 0 (esqueleto + Reglas de negocio)

## Requisitos

| Componente | Versión |
| --- | --- |
| PHP | 8.2 o superior (recomendado 8.3) |
| WordPress | 7.0 o superior |
| WooCommerce | 10.0 o superior, con HPOS activado |

## Qué incluye esta versión

- **Núcleo de dominio** en PHP puro (`src/Domain`): dinero en céntimos, reglas de negocio validadas, cálculo de comisión (vendedor > categoría > plan > general), libro contable con saldo disponible/retenido, calendario de cortes, proveedores de envío y estados del vendedor.
- **Pantalla wp-admin → Cheppi → Reglas de negocio**: comisiones, planes, categorías, plazos, cortes, monto mínimo y precios de la página de marca premium. Todo editable, con historial de cambios.
- **API REST** `GET/PUT /wp-json/cheppi/v1/rules` (solo administradores).
- **Instalador**: tablas `cheppi_vendors`, `cheppi_ledger`, `cheppi_payouts`, `cheppi_rules_history`, rol `cheppi_vendor` y valores de ejemplo.
- Declaración de compatibilidad con HPOS de WooCommerce.

## Estructura

```
cheppi-sellers.php           Arranque del plugin
src/Domain/                  Reglas puras, sin WordPress (reutilizable)
src/Application/             Casos de uso y puertos (interfaces)
src/Infrastructure/WordPress Adaptadores: admin, REST, base de datos
templates/                   Vistas PHP
assets/src/                  TypeScript + CSS (fuente)
assets/dist/                 Compilado por Vite (se incluye en el zip)
tests/                       Pruebas del dominio
```

## Desarrollo

```bash
npm install          # solo para compilar el front
npm run build        # tsc + vite → assets/dist
php tests/run.php    # pruebas del dominio (sin dependencias)
bin/build-zip.sh     # genera dist/cheppi-sellers-<versión>.zip para instalar
```

Reglas del proyecto:

- Nada de jQuery ni frameworks en el front. Cada pantalla carga su propio módulo, diferido, solo donde se usa.
- `src/Domain` no puede llamar funciones de WordPress.
- Montos siempre como `Money` (céntimos); comisiones en puntos básicos (800 = 8%).
- La comisión se congela en cada línea de pedido: cambiar las reglas no altera ventas pasadas.

## Instalación en el sitio de pruebas

1. Ejecuta `bin/build-zip.sh` o descarga el zip entregado.
2. wp-admin → Plugins → Añadir nuevo → Subir plugin → activar.
3. Ve a **Cheppi → Reglas de negocio**, revisa los valores de ejemplo y guarda.

## Desinstalar

Al borrar el plugin se conservan los registros contables. Para eliminarlos también, define `CHEPPI_SELLERS_DELETE_DATA` como `true` en `wp-config.php` antes de borrarlo.
