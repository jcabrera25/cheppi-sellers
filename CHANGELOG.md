# Cambios

## 0.1.0 — 2026-11-05

Fase 0: esqueleto del plugin.

- Núcleo de dominio: Money, BusinessRules, CommissionResolver, libro contable, PayoutSchedule, proveedores de envío, estados de vendedor.
- Pantalla Reglas de negocio en wp-admin con historial de cambios.
- API REST `cheppi/v1/rules`.
- Tablas propias, rol `cheppi_vendor` y compatibilidad con HPOS.
- Build del front con Vite + TypeScript, sin jQuery.
