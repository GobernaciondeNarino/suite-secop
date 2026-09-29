# Editor avanzado del módulo Contratación + drill-down

## Análisis de datos (Consulta.sql, cardinalidad)
Dimensiones útiles: dependencia(27), tipo_de_contrato(8), modalidad(7), estado(6),
tipo_documento_proveedor(6), nombreplan/programa(159→TopN), rubro(415→TopN),
nombretercero/contratista(1690→TopN), mensual(mes, hoy 1 valor). Inútiles (1 valor):
origen, municipio_entidad, departamento_entidad, nivel_entidad, nombre_de_la_entidad.
Métricas seguras: SUM(valordebito)=ejecución, SUM(saldoporejecutaresp)=saldo,
COUNT(DISTINCT numero_del_contrato)=nº contratos, COUNT(*)=nº registros. valor_contrato
se duplica por filas (SUM engaña) → evitar o documentar.

## Fases (bump de versión por fase)
- **F1 (5.1.4):** vista previa backend SIN textos de análisis (solo gráfica); paleta por
  defecto 8 colores `#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c`
  editable en modo avanzado; campo "dependencia" como `<select>` poblado por list_dependencies().
- **F2 (5.1.5):** ampliar DIM_COLUMN/COMPAT/dim_label con tipo_documento_proveedor, estado,
  programa(nombreplan), rubro, contratista; métricas configurables incluyendo COUNT DISTINCT
  (nº contratos) y COUNT (nº registros) — requiere soportar count-distinct en la capa de datos;
  order by (métrica/etiqueta) + dirección.
- **F3 (5.1.6):** editor avanzado de card completo: filtros (campo/op/valor con dropdown de
  dependencia), order by, campos del tooltip (qué columnas se muestran), editor de colores,
  tipo de gráfica, límite.
- **F4 (5.1.7):** atributos de shortcode que reflejan el editor (colores, orden, tipo, altura,
  leyenda, tooltip, límite, dependencia) — estilo d3plus.
- **F5 (5.1.8):** click en barra/elemento → popup con la lista de contratos asociados a esa
  categoría (AJAX), activable por atributo del shortcode (p.ej. drill="on").

## Verificación
Tests núcleo verdes, lint PHP, node --check, checkpoints manuales del usuario por fase.
