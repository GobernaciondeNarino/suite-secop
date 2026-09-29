# Cards prediseñadas del módulo Contratación (patrón "Elementos" de suite-oni)

## Objetivo
Que existan gráficas prediseñadas listas (no crear una a una), organizadas en cards (gráfica + descripción + análisis cualitativo/cuantitativo/predicción), expuestas por shortcodes con atributo `preset` y un catálogo en admin. Conservar el CPT para cards a medida (avanzado).

## Análisis de datos (Consulta.sql, vigencia 2026)
1.911 filas / 1.699 contratos; ejecutado $104.783 MM; saldo $44.750 MM; 27 dependencias.
Útiles: dependencia, tipo, modalidad, estado, contratistas. Descartados por falta de variación: `origen` (único "SECOPII"), `valorcredito` ($0). `mes`: solo mayo aún → evolución/predicción se completan al avanzar el año (el motor ya advierte "datos insuficientes").

## Cards seleccionadas por el usuario (3)
1. `por_dependencia` — x=nombredependencia, métrica=valordebito (SUM), barras.
2. `top_contratistas` — x=nombretercero, valordebito (SUM), barras, límite 10.
3. `evolucion_mensual` — x=mes, valordebito (SUM), línea, con predicción.

## Arquitectura (reusa el motor Visualizer ya integrado)
- **Presets en código**: `Tracking::presets()` (o `includes/data/contratacion-presets.php`) → cada preset: id, titulo, dimension, chart_type, metric, limit, descripcion.
- **Provisión automática (find-or-create)**: `Tracking::get_preset_post_id($id)` busca un post `secop_dep_card` con meta `_secop_preset_id=$id`; si no existe lo crea; siempre refresca `_secop_dep_card_config` y `_secop_chart_config` (vía `card_to_chart_config`). Así el shortcode `preset` reutiliza el AJAX + frontend.js del Visualizer sin tocar el render.
- **Shortcodes con `preset`**:
  - `[secop_dep_chart preset="por_dependencia"]` → resuelve a post backing y renderiza con el motor Visualizer.
  - `[secop_dep_analisis preset="por_dependencia" tipo="cuantitativo"]` → dataset por dimensión del preset → `Stats::analisis_*`.
- **Capa de datos**: añadir `DIM_COLUMN['tercero']='nombretercero'`, `COMPAT['tercero']=[bar,treemap,pie,donut,stacked_bar]`, `Stats::dim_label('tercero')='contratista'`. `card_to_chart_config` honra `limit` del preset.
- **Catálogo admin**: página "Contratación" que lista los 3 presets como cards (descripción + 4 análisis computados + shortcodes copiables + preview de la gráfica). CPT renombrado para cards a medida (avanzado).

## Verificación
- Tests núcleo verdes (`php tests/run.php`), lint PHP, node --check.
- Manual: catálogo muestra las 3 cards; shortcodes `preset` renderizan sin crear nada.
