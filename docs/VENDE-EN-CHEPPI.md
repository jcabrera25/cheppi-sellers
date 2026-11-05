# Página «Vende en Cheppi»

La página se arma en Divi 5 con módulos nativos (títulos, textos, imágenes, preguntas frecuentes).
Las dos piezas dinámicas se insertan con el módulo **Código** o **Shortcode** de Divi:

| Shortcode | Qué muestra |
| --- | --- |
| `[cheppi_calculadora]` | Calculadora de ganancia: precio y ventas al mes → cuánto recibe con cada plan. Usa los planes y comisiones de **Cheppi → Reglas de negocio**, así que se actualiza sola. |
| `[cheppi_boton_vender]` | Botón píldora lima que lleva a `/vendedor/registro/`. Opciones: `texto="Quiero vender"`, `estilo="secundario"` (violeta). |

Estructura sugerida:

1. Hero: «Vende tus productos con comisión baja» + `[cheppi_boton_vender]`.
2. Cómo funciona en 3 pasos: regístrate, te aprobamos en 2 días, sube tus productos.
3. Calculadora: `[cheppi_calculadora]`.
4. Requisitos: RUC 10 o 20, DNI, cuenta bancaria, Notificación Sanitaria para cosméticos.
5. Preguntas frecuentes y botón final.

Enlaces útiles:

- Panel del vendedor: `/vendedor/`
- Registro: `/vendedor/registro/`
- Términos para vendedores: crea la página con slug `terminos-para-vendedores` (o cambia la URL con el filtro `cheppi_sellers_terms_url`).
