<?php
/**
 * Stats — utilidades estadísticas y de formato PURAS (sin WordPress).
 * Diseñado para ser testeable de forma aislada.
 *
 * @package SecopSuite
 */
declare(strict_types=1);

namespace SecopSuite;

final class Stats
{
    /** Año (vigencia) de una fecha 'Y-m-d' o 'Y-m-d H:i:s'. */
    public static function vigencia_from_date(string $date): int
    {
        return (int) substr(trim($date), 0, 4);
    }

    /**
     * Meses transcurridos de una vigencia a una fecha de referencia.
     * 0 si la vigencia es futura; 12 si ya terminó.
     */
    public static function meses_transcurridos(string $ref_date, int $vigencia): int
    {
        $ref_year  = self::vigencia_from_date($ref_date);
        $ref_month = (int) substr(trim($ref_date), 5, 2);
        if ($ref_year < $vigencia) return 0;
        if ($ref_year > $vigencia) return 12;
        return max(0, min(12, $ref_month));
    }

    /**
     * Regresión lineal por mínimos cuadrados.
     * @param array $points lista de [x, y] (numéricos).
     * @return array{slope:?float,intercept:?float,r2:?float,se:?float,n:int,insufficient:bool}
     */
    public static function linear_regression(array $points): array
    {
        $n = count($points);
        if ($n < 2) {
            return ['slope' => null, 'intercept' => null, 'r2' => null, 'se' => null, 'n' => $n, 'insufficient' => true];
        }
        $sx = $sy = $sxx = $sxy = $syy = 0.0;
        foreach ($points as [$x, $y]) {
            $x = (float) $x; $y = (float) $y;
            $sx += $x; $sy += $y; $sxx += $x * $x; $sxy += $x * $y; $syy += $y * $y;
        }
        $denom = ($n * $sxx) - ($sx * $sx);
        if ($denom == 0.0) {
            return ['slope' => null, 'intercept' => null, 'r2' => null, 'se' => null, 'n' => $n, 'insufficient' => true];
        }
        $slope     = (($n * $sxy) - ($sx * $sy)) / $denom;
        $intercept = ($sy - ($slope * $sx)) / $n;

        // R² = 1 - SS_res/SS_tot (coefficient of determination)
        $ss_tot = $syy - ($sy * $sy) / $n;
        $ss_res = 0.0;
        foreach ($points as [$x, $y]) {
            $pred = $slope * (float) $x + $intercept;
            $ss_res += (((float) $y) - $pred) ** 2;
        }
        $r2 = $ss_tot > 0 ? max(0.0, 1.0 - ($ss_res / $ss_tot)) : 1.0;

        // Error estándar de la estimación (incertidumbre).
        $se = $n > 2 ? sqrt($ss_res / ($n - 2)) : 0.0;

        return ['slope' => $slope, 'intercept' => $intercept, 'r2' => $r2, 'se' => $se, 'n' => $n, 'insufficient' => false];
    }

    /** Proyectar y para un x dado a partir de un resultado de regresión. */
    public static function project(array $reg, float $x): ?float
    {
        if ($reg['insufficient'] || $reg['slope'] === null) return null;
        return $reg['slope'] * $x + $reg['intercept'];
    }

    public static function median(array $values): float
    {
        $values = array_values(array_map('floatval', $values));
        sort($values);
        $n = count($values);
        if ($n === 0) return 0.0;
        $mid = intdiv($n, 2);
        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    public static function mean(array $values): float
    {
        $n = count($values);
        return $n ? array_sum(array_map('floatval', $values)) / $n : 0.0;
    }

    /** Coeficiente de variación (desv. estándar poblacional / media). */
    public static function cv(array $values): float
    {
        $n = count($values);
        if ($n === 0) return 0.0;
        $mean = self::mean($values);
        if ($mean == 0.0) return 0.0;
        $var = 0.0;
        foreach ($values as $v) { $var += ((float) $v - $mean) ** 2; }
        $var /= $n;
        return sqrt($var) / abs($mean);
    }

    /**
     * Índice Herfindahl-Hirschman NORMALIZADO de concentración [0..1].
     * 1 = monopolio (una sola categoría); 0 = reparto perfectamente equitativo.
     */
    public static function hhi(array $values): float
    {
        $values = array_map('floatval', array_filter($values, fn($v) => (float) $v > 0));
        $n = count($values);
        if ($n <= 1) return $n === 1 ? 1.0 : 0.0;
        $total = array_sum($values);
        if ($total == 0.0) return 0.0;
        $h = 0.0;
        foreach ($values as $v) { $h += ($v / $total) ** 2; }
        return max(0.0, ($h - 1 / $n) / (1 - 1 / $n)); // normalización
    }

    /** Participación acumulada de las `k` categorías mayores [0..1]. */
    public static function top_share(array $values, int $k = 1): float
    {
        $values = array_map('floatval', $values);
        $total  = array_sum($values);
        if ($total == 0.0) return 0.0;
        rsort($values);
        return array_sum(array_slice($values, 0, max(1, $k))) / $total;
    }

    /** Moneda colombiana: separador de miles '.', sin decimales. */
    public static function money(float $value): string
    {
        return '$' . number_format(round($value), 0, ',', '.');
    }

    /** Porcentaje es-CO; admite [0..1]. Quita decimal '0'. */
    public static function percent(float $ratio): string
    {
        $p = $ratio * 100;
        $s = number_format($p, 1, ',', '.');
        $s = preg_replace('/,0$/', '', $s);
        return $s . '%';
    }

    /** Número entero formateado es-CO. */
    public static function num(float $value): string
    {
        return number_format(round($value), 0, ',', '.');
    }

    /** Formatea una magnitud según la métrica: dinero ($) o conteo (entero). */
    public static function magnitud(float $value, bool $is_money): string
    {
        return $is_money ? self::money($value) : self::num($value);
    }

    /** Recorta a <=564 caracteres respetando límites de palabra. */
    public static function clamp564(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= 564) return $text;
        $cut = mb_substr($text, 0, 564);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 0) {
            $cut = mb_substr($cut, 0, $sp);
        } else {
            // Sin espacio en los 564 primeros chars: truncar a 563 para dejar sitio para '…'
            $cut = mb_substr($cut, 0, 563);
        }
        return rtrim($cut, " ,.;:") . '…';
    }

    private static function dim_label(string $dim): string
    {
        return [
            'dependencia'   => 'dependencia',
            'tipo_contrato' => 'tipo de contrato',
            'modalidad'     => 'modalidad de contratación',
            'tercero'       => 'contratista',
            'mensual'       => 'mes',
        ][$dim] ?? $dim;
    }

    /** Plural de la dimensión (para frases tipo «de 23 modalidades»). */
    private static function dim_label_plural(string $dim): string
    {
        return [
            'dependencia'   => 'dependencias',
            'tipo_contrato' => 'tipos de contrato',
            'modalidad'     => 'modalidades de contratación',
            'tercero'       => 'contratistas',
            'mensual'       => 'meses',
        ][$dim] ?? ($dim . 's');
    }

    /** Magnitud principal del dataset según la métrica (dinero o conteo). */
    private static function fmt_metric(array $d, float $v): string
    {
        return self::magnitud($v, $d['metric_is_money'] ?? true);
    }

    // ── Textos para la ciudadanía (lenguaje claro, sin jerga técnica) ──────────

    public static function analisis_descripcion(array $d): string
    {
        $cats = $d['categorias'] ?? [];
        $dim  = self::dim_label($d['dimension']);
        if (empty($cats)) {
            return self::clamp564(sprintf(
                'Todavía no hay contratos registrados por %s en la vigencia %d. La información se irá '
              . 'completando a medida que se publiquen contratos en el SECOP.',
                $dim, $d['vigencia']
            ));
        }
        $metric = mb_strtolower($d['metric_label'] ?? 'valor del contrato');
        $dimP   = self::dim_label_plural($d['dimension']);
        $top    = $cats[0];

        // Encabezado: métrica + dimensión + vigencia, y el alcance (top N de M).
        $intro = sprintf('El %s por %s en la vigencia %d', $metric, $dim, $d['vigencia']);
        if (($d['limit'] ?? 0) > 0 && ($d['total_categorias'] ?? 0) > count($cats)) {
            $intro .= sprintf(' (top %d de %d %s)', count($cats), $d['total_categorias'], $dimP);
        }
        $t = $intro . sprintf(
            ': «%s» encabeza con %s (%s del total)',
            $top['label'], self::fmt_metric($d, (float) $top['valor']), self::percent($top['pct'] ?? $d['share1'] ?? 0)
        );
        if (isset($cats[1])) {
            $t .= sprintf(
                ' y le sigue «%s» con %s (%s)',
                $cats[1]['label'], self::fmt_metric($d, (float) $cats[1]['valor']), self::percent($cats[1]['pct'] ?? 0)
            );
        }
        $t .= '. ';
        if (($d['share3'] ?? 0) > 0 && count($cats) >= 3) {
            $t .= sprintf('El top 3 reúne el %s. ', self::percent($d['share3']));
        }
        $resto = $d['resto'] ?? ['count' => 0];
        if (($resto['count'] ?? 0) > 0) {
            $restoLabel = $resto['count'] === 1 ? $dim : $dimP;
            $t .= sprintf(
                'El resto (%d %s) suma %s (%s).',
                $resto['count'], $restoLabel, self::fmt_metric($d, (float) $resto['valor']), self::percent($resto['pct'] ?? 0)
            );
        }
        return self::clamp564($t);
    }

    public static function analisis_cualitativo(array $d): string
    {
        $cats = $d['categorias'] ?? [];
        if (empty($cats)) {
            return self::clamp564('Aún no hay datos suficientes para analizar cómo se distribuye la contratación en esta vigencia.');
        }
        $metric = mb_strtolower($d['metric_label'] ?? 'valor del contrato');
        $dim    = self::dim_label($d['dimension']);
        $share1 = $d['share1'] ?? 0.0;
        $share3 = $d['share3'] ?? 0.0;
        $hhi    = $d['hhi'] ?? 0.0;
        $top    = $cats[0];
        $concentrada = $hhi > 0.5 || $share1 > 0.5;

        $t = sprintf(
            'La distribución del %s por %s es %s: «%s» concentra el %s',
            $metric, $dim,
            $concentrada ? 'muy desigual' : 'relativamente equilibrada',
            $top['label'], self::percent($share1)
        );
        if ($share3 > 0 && count($cats) >= 3) {
            $t .= sprintf(' y el top 3 llega al %s', self::percent($share3));
        }
        $t .= '. ';
        $t .= $concentrada
            ? 'Unos pocos nombres acaparan la mayor parte; conviene revisar que esa concentración esté justificada y promover más participación.'
            : 'La contratación se reparte de forma amplia, sin un actor que domine por completo: una señal de pluralidad.';
        return self::clamp564($t);
    }

    public static function analisis_cuantitativo(array $d): string
    {
        $cats = $d['categorias'] ?? [];
        if (empty($cats)) {
            return self::clamp564('Sin contratos registrados, todavía no hay cifras que resumir en esta vigencia.');
        }
        $isMoney = $d['metric_is_money'] ?? true;
        $metric  = mb_strtolower($d['metric_label'] ?? 'valor del contrato');
        $dimP    = self::dim_label_plural($d['dimension']);
        $media   = (float) ($d['media'] ?? 0);
        $mediana = (float) ($d['mediana'] ?? 0);
        $top     = $cats[0];
        $ratio   = $media > 0 ? (float) $top['valor'] / $media : 0.0;

        $t = sprintf(
            'En total, el %s suma %s repartido en %d %s. El promedio es %s y la mediana %s',
            $metric, self::fmt_metric($d, (float) ($d['total_valor'] ?? 0)),
            (int) ($d['total_categorias'] ?? count($cats)), $dimP,
            self::fmt_metric($d, $media), self::fmt_metric($d, $mediana)
        );
        if ($media > 0 && $mediana > 0 && $media > $mediana * 1.3) {
            $t .= ' (la media supera a la mediana: hay casos atípicos que jalan el promedio hacia arriba)';
        }
        $t .= '. ';
        if ($ratio >= 2) {
            $t .= sprintf('«%s» (%s) está %s veces por encima del promedio. ', $top['label'], self::fmt_metric($d, (float) $top['valor']), self::num($ratio));
        }
        // Avance presupuestal SÓLO cuando la métrica es monetaria y hay datos.
        $totalPpto = (float) ($d['ejecutado'] ?? 0) + (float) ($d['saldo'] ?? 0);
        if ($isMoney && $totalPpto > 0) {
            $t .= sprintf('Del presupuesto con seguimiento se ha ejecutado el %s (%s de %s).',
                self::percent($d['ejecutado'] / $totalPpto), self::money($d['ejecutado']), self::money($totalPpto));
        }
        return self::clamp564($t);
    }

    public static function analisis_prediccion(array $d): string
    {
        $vig     = $d['vigencia'];
        $metric  = mb_strtolower($d['metric_label'] ?? 'valor del contrato');
        $isMoney = $d['metric_is_money'] ?? true;

        // Dimensión temporal: proyección de cierre de toda la entidad.
        if (($d['dimension'] ?? '') === 'mensual') {
            $reg = self::linear_regression($d['serie_mensual'] ?? []);
            if ($reg['insufficient']) {
                return self::clamp564(sprintf(
                    'Aún no es posible proyectar cómo cerrará la vigencia %d: hay muy pocos meses con información (se necesitan al menos dos).',
                    $vig
                ));
            }
            $cierre = self::project($reg, 12.0);
            $actual = end($d['serie_mensual'])[1] ?? 0.0;
            $crec   = $actual > 0 ? ($cierre - $actual) / $actual : 0.0;
            $conf   = $reg['r2'] >= 0.8 ? 'alta' : ($reg['r2'] >= 0.5 ? 'media' : 'baja');
            return self::clamp564(sprintf(
                'Según la tendencia de los primeros %d meses, el %s de la entidad cerraría la vigencia %d cerca de %s, '
              . 'frente a %s a la fecha (variación ≈%s). La confiabilidad del ajuste es %s.',
                $reg['n'], $metric, $vig, self::magnitud((float) $cierre, $isMoney),
                self::magnitud($actual, $isMoney), self::percent($crec), $conf
            ));
        }

        // Dimensión categórica: proyectar la categoría líder.
        $lider = $d['lider'] ?? null;
        $serie = $d['serie_lider'] ?? [];
        $nombre = $lider['label'] ?? 'la categoría principal';
        if (!$lider || count($serie) < 2) {
            return self::clamp564(sprintf(
                'Todavía no hay suficiente historia mensual para proyectar cómo cerrará «%s», la %s con mayor %s. '
              . 'La estimación aparecerá conforme avance la vigencia %d.',
                $nombre, self::dim_label($d['dimension']), $metric, $vig
            ));
        }
        $reg = self::linear_regression($serie);
        if ($reg['insufficient']) {
            return self::clamp564(sprintf('No es posible proyectar el cierre de «%s» con los meses disponibles en la vigencia %d.', $nombre, $vig));
        }
        $cierre = self::project($reg, 12.0);
        $actual = end($serie)[1] ?? 0.0;
        $crec   = $actual > 0 ? ($cierre - $actual) / $actual : 0.0;
        $conf   = $reg['r2'] >= 0.8 ? 'alta' : ($reg['r2'] >= 0.5 ? 'media' : 'baja');
        return self::clamp564(sprintf(
            'Si «%s» mantiene su ritmo, cerraría la vigencia %d cerca de %s en %s (hoy concentra el %s del total). '
          . 'Variación frente a hoy ≈%s; confiabilidad %s.',
            $nombre, $vig, self::magnitud((float) $cierre, $isMoney), $metric,
            self::percent($lider['pct'] ?? $d['share1'] ?? 0), self::percent($crec), $conf
        ));
    }
}
