# Módulo Seguimiento de Dependencias y Datos Abiertos — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir a SECOP Suite (v5.1.0) un módulo enfocado en la contratación de la
entidad que cruza contratos con ejecución presupuestal por dependencia (VIEW SQL),
con gráficas prediseñadas d3plus, análisis autogenerados con rigor estadístico,
interactividad (dependencia → contratos), y un submenú "Datos Abiertos" de shortcodes.

**Architecture:** Módulo dedicado (`includes/class-tracking.php` + `includes/class-stats.php`)
que NO toca el motor de gráficas existente. VIEW `{prefix}dat_seguimiento_dependencias`
(auto-whitelisted). CPT `secop_dep_card` solo para organización de backend. Todo filtrado
a la vigencia actual (`anio = YEAR(CURDATE())`), auto-actualizable.

**Tech Stack:** PHP 8.1 (WordPress), d3.v5 + d3plus@2, jQuery, MySQL VIEW. Tests del
núcleo puro con PHP CLI portable (sin WordPress). Integración WP verificada manualmente.

**Spec:** `docs/superpowers/specs/2026-06-19-modulo-seguimiento-dependencias-design.md`

---

## Estrategia de verificación

- **Núcleo puro (TDD real):** `class-stats.php`, helpers de vigencia, formato de
  números, recorte a 564 chars, validación de compatibilidad dimensión↔tipo, y
  generadores de párrafos (con datos inyectados). Se prueban con scripts PHP
  standalone ejecutados por `php` CLI — sin WordPress.
- **Integración WordPress (checkpoint manual):** VIEW/migración, CPT, metaboxes,
  shortcodes renderizados, AJAX, REST, páginas admin. Verificación: empaquetar el
  plugin, instalarlo en el WordPress del usuario, seguir un checklist de aceptación.
- **Lint:** `php -l` en cada archivo modificado.

---

## Mapa de archivos

| Archivo | Responsabilidad |
|---|---|
| `secop-suite.php` (mod) | Bump versión; instanciar `Tracking`; getter; submenús; migración VIEW |
| `includes/class-stats.php` (nuevo) | Estadística pura: regresión MC, R², proyección, mediana, CV, HHI/top-share, formato es-CO, recorte 564 |
| `includes/class-tracking.php` (nuevo) | VIEW, CPT `secop_dep_card`, shortcodes, AJAX, queries de vigencia, análisis, submenús |
| `includes/class-database.php` (mod) | `create_view()`, `sysman_tables_exist()` |
| `includes/class-rest-api.php` (mod) | Endpoint `GET /consulta` |
| `templates/admin/dep-cards-page.php` (nuevo) | (CPT usa UI nativa; archivo solo si se requiere landing admin) |
| `templates/admin/datos-abiertos-page.php` (nuevo) | Hub de shortcodes de datos abiertos |
| `templates/admin/dep-card-config.php` (nuevo) | Metabox de configuración de card |
| `templates/frontend/dep-chart.php` (nuevo) | Contenedor de gráfica prediseñada |
| `templates/frontend/dep-seguimiento.php` (nuevo) | Landing interactiva |
| `templates/frontend/dep-contratos.php` (nuevo) | Tabla de contratos |
| `templates/frontend/consulta.php` (nuevo) | Visor de datos abiertos del VIEW |
| `assets/js/dep-tracking.js` (nuevo) | Render d3plus + interactividad + tooltips |
| `assets/css/frontend.css` (mod) | Estilos del módulo (heredan look del plugin) |
| `tests/` (nuevo) | Tests PHP standalone del núcleo puro |

---

## Task 0: Tooling — PHP CLI portable + bootstrap de tests

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/run.php`

- [ ] **Step 1: Descargar PHP CLI portable (Windows, sin admin)**

```bash
mkdir -p /c/Users/Usuario/php-portable && cd /c/Users/Usuario/php-portable
curl -L -o php.zip https://windows.php.net/downloads/releases/php-8.3.14-nts-Win32-vs16-x64.zip
unzip -o php.zip >/dev/null && cp php.ini-development php.ini
./php.exe --version
```
Expected: imprime `PHP 8.3.x (cli)`. Si la URL caducó, usar la versión `-nts-Win32-vs16-x64`
vigente listada en https://windows.php.net/download/ (cualquier 8.1–8.3 sirve).

- [ ] **Step 2: Crear un micro-runner de tests (sin dependencias)**

`tests/bootstrap.php`:
```php
<?php
// Micro framework de aserciones para tests standalone (sin WordPress).
declare(strict_types=1);
$GLOBALS['__tests'] = ['pass' => 0, 'fail' => 0, 'msgs' => []];

function it(string $name, callable $fn): void {
    try { $fn(); $GLOBALS['__tests']['pass']++; echo "  ok  - {$name}\n"; }
    catch (\Throwable $e) {
        $GLOBALS['__tests']['fail']++;
        echo "  FAIL- {$name}: {$e->getMessage()}\n";
    }
}
function assert_eq($expected, $actual, string $m = ''): void {
    if ($expected !== $actual) {
        throw new \Exception(($m ?: 'assert_eq') . " — esperado " . var_export($expected, true) . ", obtenido " . var_export($actual, true));
    }
}
function assert_true(bool $cond, string $m = ''): void {
    if (!$cond) throw new \Exception($m ?: 'assert_true falló');
}
function assert_approx(float $expected, float $actual, float $eps, string $m = ''): void {
    if (abs($expected - $actual) > $eps) {
        throw new \Exception(($m ?: 'assert_approx') . " — |{$expected}-{$actual}| > {$eps}");
    }
}
function finish(): void {
    $t = $GLOBALS['__tests'];
    echo "\n{$t['pass']} passed, {$t['fail']} failed\n";
    exit($t['fail'] > 0 ? 1 : 0);
}
```

`tests/run.php`:
```php
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
// Cargar la clase bajo prueba sin arrancar WordPress.
require dirname(__DIR__) . '/includes/class-stats.php';
foreach (glob(__DIR__ . '/test-*.php') as $f) { require $f; }
finish();
```

- [ ] **Step 3: Verificar el runner (aún sin tests)**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: falla al hacer `require` de `class-stats.php` (aún no existe) — confirma que el
runner intenta cargarla. Se resuelve en Task 4.

- [ ] **Step 4: Commit**

```bash
git add tests/
git commit -m "test: bootstrap de tests PHP standalone para el núcleo puro"
```

Nota: si la descarga de PHP falla, fallback: portar las funciones puras a un archivo
`.mjs` y verificarlas con `node`; documentar la divergencia. El código real sigue siendo PHP.

---

## Task 1: Bump de versión a 5.1.0

**Files:**
- Modify: `secop-suite.php:6` (header `Version`)
- Modify: `secop-suite.php:28-29` (constantes)
- Modify: `README.md` (changelog)

- [ ] **Step 1: Actualizar header y constantes**

En `secop-suite.php` línea 6: `* Version: 5.1.0`
Líneas 28-29:
```php
define('SECOP_SUITE_VERSION', '5.1.0');
define('SECOP_SUITE_DB_VERSION', '5.1.0');
```

- [ ] **Step 2: Añadir entrada de changelog en README.md**

Insertar bajo el encabezado de versiones:
```markdown
## v5.1.0 — Módulo Seguimiento de Dependencias y Datos Abiertos
- VIEW `dat_seguimiento_dependencias` (contratos × ejecución presupuestal por dependencia).
- Gráficas prediseñadas + análisis autogenerados (regresión + R²), vigencia actual.
- Shortcodes: `[secop_seguimiento]`, `[secop_dep_chart]`, `[secop_dep_analisis]`,
  `[secop_dep_contratos]`, `[secop_consulta]`.
- Submenús admin: "Seguimiento Dependencias" y "Datos Abiertos".
```

- [ ] **Step 3: Lint y commit**

Run: `/c/Users/Usuario/php-portable/php.exe -l secop-suite.php`
Expected: `No syntax errors detected`
```bash
git add secop-suite.php README.md
git commit -m "chore: bump a v5.1.0 e inicio del módulo de dependencias"
```

---

## Task 2: Helper de vigencia actual (TDD puro)

**Files:**
- Create: `includes/class-stats.php` (inicio del archivo)
- Test: `tests/test-vigencia.php`

- [ ] **Step 1: Test que falla**

`tests/test-vigencia.php`:
```php
<?php
use SecopSuite\Stats;
it('vigencia_from_date extrae el año', function () {
    assert_eq(2026, Stats::vigencia_from_date('2026-06-19'));
    assert_eq(2024, Stats::vigencia_from_date('2024-01-01 00:00:00'));
});
it('meses_transcurridos cuenta correctamente', function () {
    assert_eq(6, Stats::meses_transcurridos('2026-06-19', 2026)); // enero..junio
    assert_eq(12, Stats::meses_transcurridos('2027-03-01', 2026)); // año pasado completo
    assert_eq(0, Stats::meses_transcurridos('2025-12-31', 2026)); // vigencia futura
});
```

- [ ] **Step 2: Ejecutar y ver fallar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: FAIL — `Class "SecopSuite\Stats" not found`.

- [ ] **Step 3: Implementación mínima**

`includes/class-stats.php`:
```php
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
}
```

- [ ] **Step 4: Ejecutar y ver pasar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `ok` en los tests de vigencia.

- [ ] **Step 5: Commit**

```bash
git add includes/class-stats.php tests/test-vigencia.php
git commit -m "feat(stats): helpers de vigencia (año y meses transcurridos)"
```

---

## Task 3: VIEW SQL + verificación de tablas Sysman

**Files:**
- Modify: `includes/class-database.php` (añadir métodos)
- Modify: `secop-suite.php` (migración + admin notice)

- [ ] **Step 1: Añadir métodos a `Database`**

Tras `get_available_tables()` en `class-database.php`:
```php
    /** Nombre del VIEW del módulo de seguimiento. */
    public function get_view_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'dat_seguimiento_dependencias';
    }

    /** ¿Existen las tablas Sysman requeridas por el VIEW? */
    public function sysman_tables_exist(): bool
    {
        global $wpdb;
        foreach (['sysman_auxiliar_cuentas', 'sysman_plan_presupuestal'] as $t) {
            $full = $wpdb->prefix . $t;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) {
                return false;
            }
        }
        return true;
    }

    /**
     * Crear/reemplazar el VIEW que cruza ejecución presupuestal y contratos.
     * Devuelve true si se creó; false si faltan tablas Sysman.
     */
    public function create_view(): bool
    {
        global $wpdb;
        if (!$this->sysman_tables_exist()) {
            Logger::log('VIEW no creado: faltan tablas Sysman (sysman_auxiliar_cuentas / sysman_plan_presupuestal)');
            return false;
        }
        $view = $this->get_view_name();
        $ac   = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $pp   = $wpdb->prefix . 'sysman_plan_presupuestal';
        $c    = $this->table_name; // {prefix}secop_contracts

        // Identificadores desde whitelist/$wpdb->prefix → seguro interpolar.
        $sql = "CREATE OR REPLACE VIEW `{$view}` AS
            SELECT
              pp.dependencia, pp.nombredependencia,
              ac.tercero, ac.nombretercero,
              ac.numero AS numero_de_proceso,
              ac.valordebito, ac.valorcredito, ac.saldoporejecutaresp,
              ac.cmpteafectado, ac.fecha, ac.anio, ac.mes,
              c.numero_del_contrato, c.nom_raz_social_contratista,
              c.fecha_inicio_ejecucion, c.fecha_fin_ejecucion,
              c.valor_contrato, c.objeto_del_proceso, c.url_contrato,
              c.tipo_de_contrato, c.modalidad_de_contratacion, c.origen
            FROM `{$ac}` ac
            INNER JOIN `{$pp}` pp ON ac.rubro = pp.codigo
            INNER JOIN `{$c}` c  ON ac.nrodocumento = c.numero_de_proceso
            WHERE ac.tipocpte = 'REs'";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query($sql);
        if ($result === false) {
            Logger::log('Error al crear VIEW: ' . $wpdb->last_error);
            return false;
        }
        Logger::info("VIEW {$view} creado/actualizado");
        return true;
    }
```

- [ ] **Step 2: Wire migración en `maybe_upgrade()` y activación**

En `secop-suite.php`, dentro de `maybe_upgrade()` tras crear/migrar tabla, y en `activate()`:
```php
        // v5.1.0: crear VIEW del módulo de seguimiento (si hay tablas Sysman).
        $this->database->create_view();
```
Añadir admin notice si faltan tablas. En `register_hooks()`:
```php
        add_action('admin_notices', [$this, 'maybe_sysman_notice']);
```
Y el método:
```php
    public function maybe_sysman_notice(): void
    {
        if (!current_user_can('manage_options')) return;
        $screen = get_current_screen();
        if (!$screen || !str_contains($screen->id, 'secop-suite')) return;
        if ($this->database->sysman_tables_exist()) return;
        echo '<div class="notice notice-warning"><p>'
           . esc_html__('SECOP Suite: el módulo de Seguimiento de Dependencias requiere las tablas Sysman (sysman_auxiliar_cuentas y sysman_plan_presupuestal) en la base de datos. El VIEW no se ha creado.', 'secop-suite')
           . '</p></div>';
    }
```

- [ ] **Step 3: Lint**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-database.php && /c/Users/Usuario/php-portable/php.exe -l secop-suite.php`
Expected: `No syntax errors detected` en ambos.

- [ ] **Step 4: Checkpoint manual (WordPress)**

Empaquetar e instalar; activar/reactivar el plugin. Verificar en la BD que existe el VIEW
`{prefix}dat_seguimiento_dependencias` y devuelve filas. Si faltan tablas Sysman, debe
verse el admin notice y NO un error fatal.

- [ ] **Step 5: Commit**

```bash
git add includes/class-database.php secop-suite.php
git commit -m "feat(db): VIEW dat_seguimiento_dependencias + verificación de tablas Sysman"
```

---

## Task 4: Regresión lineal por mínimos cuadrados + R² + proyección (TDD puro)

**Files:**
- Modify: `includes/class-stats.php`
- Test: `tests/test-regresion.php`

- [ ] **Step 1: Test que falla**

`tests/test-regresion.php`:
```php
<?php
use SecopSuite\Stats;

it('regresion ajusta una recta perfecta', function () {
    // y = 2x + 1 sobre x=1..5
    $pts = [[1,3],[2,5],[3,7],[4,9],[5,11]];
    $r = Stats::linear_regression($pts);
    assert_approx(2.0, $r['slope'], 1e-9, 'slope');
    assert_approx(1.0, $r['intercept'], 1e-9, 'intercept');
    assert_approx(1.0, $r['r2'], 1e-9, 'r2 perfecto');
});

it('proyeccion estima el valor en x futuro', function () {
    $pts = [[1,3],[2,5],[3,7]];
    $r = Stats::linear_regression($pts);
    assert_approx(25.0, Stats::project($r, 12), 1e-9); // 2*12+1
});

it('regresion con menos de 2 puntos retorna nulo seguro', function () {
    $r = Stats::linear_regression([[1,5]]);
    assert_true($r['slope'] === null, 'slope nulo con 1 punto');
    assert_true($r['insufficient'] === true);
});

it('r2 refleja dispersion', function () {
    $pts = [[1,1],[2,3],[3,2],[4,5],[5,4]];
    $r = Stats::linear_regression($pts);
    assert_true($r['r2'] > 0.5 && $r['r2'] < 1.0, 'r2 intermedio');
});
```

- [ ] **Step 2: Ejecutar y ver fallar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: FAIL — `Call to undefined method ... linear_regression`.

- [ ] **Step 3: Implementar en `class-stats.php`**

```php
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

        // R² = (cov^2) / (var_x * var_y)
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
```

- [ ] **Step 4: Ejecutar y ver pasar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `ok` en los 4 tests de regresión.

- [ ] **Step 5: Commit**

```bash
git add includes/class-stats.php tests/test-regresion.php
git commit -m "feat(stats): regresión lineal MC con R² y error estándar"
```

---

## Task 5: Estadística descriptiva y concentración (TDD puro)

**Files:**
- Modify: `includes/class-stats.php`
- Test: `tests/test-descriptiva.php`

- [ ] **Step 1: Test que falla**

`tests/test-descriptiva.php`:
```php
<?php
use SecopSuite\Stats;

it('mediana de impares y pares', function () {
    assert_eq(3.0, Stats::median([1,3,2,5,4]));
    assert_eq(2.5, Stats::median([1,2,3,4]));
    assert_eq(0.0, Stats::median([]));
});
it('coeficiente de variacion', function () {
    assert_approx(0.0, Stats::cv([5,5,5]), 1e-9);
    assert_true(Stats::cv([1,2,3,4,5]) > 0.0);
});
it('hhi normalizado: monopolio=1, equidad->0', function () {
    assert_approx(1.0, Stats::hhi([100]), 1e-9);
    assert_approx(0.0, Stats::hhi([50,50]), 1e-9);     // HHI normalizado de 2 iguales = 0
    assert_true(Stats::hhi([80,10,10]) > Stats::hhi([34,33,33]));
});
it('top_share: porcentaje del mayor', function () {
    assert_approx(0.5, Stats::top_share([50,30,20], 1), 1e-9);
    assert_approx(0.8, Stats::top_share([50,30,20], 2), 1e-9);
});
```

- [ ] **Step 2: Ejecutar y ver fallar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: FAIL — métodos no definidos.

- [ ] **Step 3: Implementar en `class-stats.php`**

```php
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
```

- [ ] **Step 4: Ejecutar y ver pasar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `ok` en los tests descriptivos.

- [ ] **Step 5: Commit**

```bash
git add includes/class-stats.php tests/test-descriptiva.php
git commit -m "feat(stats): mediana, media, CV, HHI normalizado y top-share"
```

---

## Task 6: Formato es-CO y recorte a 564 caracteres (TDD puro)

**Files:**
- Modify: `includes/class-stats.php`
- Test: `tests/test-formato.php`

- [ ] **Step 1: Test que falla**

`tests/test-formato.php`:
```php
<?php
use SecopSuite\Stats;

it('formato moneda colombiano', function () {
    assert_eq('$1.234.567', Stats::money(1234567));
    assert_eq('$0', Stats::money(0));
    assert_eq('$1.234.568', Stats::money(1234567.6)); // redondeo
});
it('formato porcentaje', function () {
    assert_eq('45,3%', Stats::percent(0.4527));
    assert_eq('100%', Stats::percent(1.0));
});
it('clamp recorta a 564 sin cortar palabras', function () {
    $s = str_repeat('palabra ', 100); // >564
    $out = Stats::clamp564($s);
    assert_true(mb_strlen($out) <= 564, 'longitud <= 564');
    assert_true(!str_ends_with($out, 'palabr'), 'no corta a media palabra');
});
it('clamp deja intacto texto corto', function () {
    assert_eq('Hola mundo', Stats::clamp564('Hola mundo'));
});
```

- [ ] **Step 2: Ejecutar y ver fallar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: FAIL — métodos no definidos.

- [ ] **Step 3: Implementar en `class-stats.php`**

```php
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

    /** Recorta a <=564 caracteres respetando límites de palabra. */
    public static function clamp564(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= 564) return $text;
        $cut = mb_substr($text, 0, 564);
        $sp  = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 0) $cut = mb_substr($cut, 0, $sp);
        return rtrim($cut, " ,.;:") . '…';
    }
```

- [ ] **Step 4: Ejecutar y ver pasar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `ok` en los tests de formato.

- [ ] **Step 5: Commit**

```bash
git add includes/class-stats.php tests/test-formato.php
git commit -m "feat(stats): formato es-CO (moneda/%) y recorte a 564 caracteres"
```

---

## Task 7: Generadores de párrafos de análisis (TDD puro)

**Files:**
- Modify: `includes/class-stats.php`
- Test: `tests/test-analisis.php`

Los generadores reciben un "dataset de análisis" ya calculado (array), de modo que son
puros y testeables sin BD. Estructura del dataset:
```php
// [
//   'dimension' => 'dependencia', 'vigencia' => 2026, 'meses' => 6,
//   'categorias' => [ ['label'=>'Salud','valor'=>900.0,'conteo'=>12], ... ],
//   'serie_mensual' => [ [1, 100.0], [2, 250.0], ... ],   // [mes, acumulado]
//   'total_valor' => 1500.0, 'total_conteo' => 30,
//   'ejecutado' => 900.0, 'saldo' => 600.0,
// ]
```

- [ ] **Step 1: Test que falla**

`tests/test-analisis.php`:
```php
<?php
use SecopSuite\Stats;

function ds(): array {
    return [
        'dimension' => 'dependencia', 'vigencia' => 2026, 'meses' => 6,
        'categorias' => [
            ['label' => 'Salud',    'valor' => 900.0, 'conteo' => 12],
            ['label' => 'Educación','valor' => 400.0, 'conteo' => 10],
            ['label' => 'Vías',     'valor' => 200.0, 'conteo' => 8],
        ],
        'serie_mensual' => [[1,100.0],[2,250.0],[3,500.0],[4,800.0],[5,1200.0],[6,1500.0]],
        'total_valor' => 1500.0, 'total_conteo' => 30,
        'ejecutado' => 900.0, 'saldo' => 600.0,
    ];
}

it('descripcion menciona vigencia y totales y respeta 564', function () {
    $t = Stats::analisis_descripcion(ds());
    assert_true(str_contains($t, '2026'));
    assert_true(str_contains($t, '30'));
    assert_true(mb_strlen($t) <= 564);
});
it('cualitativo menciona la categoria dominante', function () {
    $t = Stats::analisis_cualitativo(ds());
    assert_true(str_contains($t, 'Salud'));
    assert_true(mb_strlen($t) <= 564);
});
it('cuantitativo incluye cifras y % ejecucion', function () {
    $t = Stats::analisis_cuantitativo(ds());
    assert_true(str_contains($t, '$'));
    assert_true(mb_strlen($t) <= 564);
});
it('prediccion proyecta cierre con R2', function () {
    $t = Stats::analisis_prediccion(ds());
    assert_true(str_contains($t, 'R²') || str_contains($t, 'ajuste'));
    assert_true(mb_strlen($t) <= 564);
});
it('prediccion con datos insuficientes lo advierte', function () {
    $d = ds(); $d['serie_mensual'] = [[1,100.0]];
    $t = Stats::analisis_prediccion($d);
    assert_true(str_contains(mb_strtolower($t), 'insuficien') || str_contains(mb_strtolower($t), 'no es posible'));
});
```

- [ ] **Step 2: Ejecutar y ver fallar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: FAIL — métodos no definidos.

- [ ] **Step 3: Implementar en `class-stats.php`**

```php
    private static function dim_label(string $dim): string
    {
        return [
            'dependencia'  => 'dependencia',
            'tipo_contrato'=> 'tipo de contrato',
            'modalidad'    => 'modalidad de contratación',
            'fuente'       => 'fuente de financiación',
            'mensual'      => 'mes',
            'ejecucion'    => 'ejecución presupuestal',
        ][$dim] ?? $dim;
    }

    public static function analisis_descripcion(array $d): string
    {
        $dim = self::dim_label($d['dimension']);
        $ncat = count($d['categorias']);
        $t = sprintf(
            'Esta gráfica resume la contratación de la entidad para la vigencia %d, organizada por %s. '
          . 'Se incluyen %s contratos por un valor total de %s, distribuidos en %d %s. '
          . 'Los datos provienen del cruce entre la ejecución presupuestal (Sysman) y los contratos publicados en el SECOP, '
          . 'y se actualizan automáticamente con la vigencia en curso.',
            $d['vigencia'], $dim, self::num($d['total_conteo']), self::money($d['total_valor']),
            $ncat, $ncat === 1 ? 'categoría' : 'categorías'
        );
        return self::clamp564($t);
    }

    public static function analisis_cualitativo(array $d): string
    {
        $cats = $d['categorias'];
        usort($cats, fn($a, $b) => $b['valor'] <=> $a['valor']);
        $valores = array_map(fn($c) => $c['valor'], $cats);
        $hhi = self::hhi($valores);
        $top1 = $cats[0] ?? ['label' => 'N/D', 'valor' => 0];
        $share1 = self::top_share($valores, 1);
        $nivel = $hhi > 0.5 ? 'alta concentración' : ($hhi > 0.2 ? 'concentración moderada' : 'distribución equilibrada');
        $t = sprintf(
            'La contratación muestra una %s entre las %s. "%s" concentra la mayor participación con %s del valor total. '
          . 'Un índice de concentración (HHI normalizado) de %s indica %s: %s. '
          . 'Conviene revisar si esta distribución responde a la planeación o a una dependencia excesiva de pocos rubros.',
            $nivel, self::dim_label($d['dimension']) . 's', $top1['label'], self::percent($share1),
            self::percent($hhi),
            $hhi > 0.5 ? 'pocas categorías dominan el gasto' : 'el gasto está repartido',
            $nivel
        );
        return self::clamp564($t);
    }

    public static function analisis_cuantitativo(array $d): string
    {
        $valores = array_map(fn($c) => $c['valor'], $d['categorias']);
        $media = self::mean($valores);
        $mediana = self::median($valores);
        $cv = self::cv($valores);
        $totalPpto = $d['ejecutado'] + $d['saldo'];
        $pctEjec = $totalPpto > 0 ? $d['ejecutado'] / $totalPpto : 0.0;
        $t = sprintf(
            'Valor total contratado: %s en %s contratos. Por categoría, el promedio es %s y la mediana %s '
          . '(coeficiente de variación %s, que mide la dispersión). La ejecución presupuestal alcanza %s '
          . '(%s ejecutado de %s), con un saldo por ejecutar de %s. '
          . 'La diferencia entre media y mediana señala la presencia de valores atípicos.',
            self::money($d['total_valor']), self::num($d['total_conteo']),
            self::money($media), self::money($mediana), number_format($cv, 2, ',', '.'),
            self::percent($pctEjec), self::money($d['ejecutado']), self::money($totalPpto), self::money($d['saldo'])
        );
        return self::clamp564($t);
    }

    public static function analisis_prediccion(array $d): string
    {
        $reg = self::linear_regression($d['serie_mensual']);
        if ($reg['insufficient']) {
            return self::clamp564(sprintf(
                'No es posible proyectar el cierre de la vigencia %d: los datos disponibles son insuficientes '
              . '(se requieren al menos dos meses con ejecución registrada). La predicción se habilitará a medida '
              . 'que avance la vigencia y se acumulen más comprobantes de registro presupuestal.',
                $d['vigencia']
            ));
        }
        $cierre = self::project($reg, 12.0);
        $actual = end($d['serie_mensual'])[1] ?? 0.0;
        $crecimiento = $actual > 0 ? ($cierre - $actual) / $actual : 0.0;
        $confianza = $reg['r2'] >= 0.8 ? 'alta' : ($reg['r2'] >= 0.5 ? 'media' : 'baja');
        $t = sprintf(
            'Mediante regresión lineal sobre la ejecución mensual acumulada (%d meses), se proyecta un cierre de vigencia %d '
          . 'cercano a %s, frente a %s acumulados a la fecha (variación estimada %s). '
          . 'El ajuste del modelo es R²=%s (confiabilidad %s, ±%s de error estándar). '
          . 'La estimación asume continuidad de la tendencia y no contempla estacionalidad de cierre.',
            $reg['n'], $d['vigencia'], self::money((float) $cierre), self::money($actual),
            self::percent($crecimiento), number_format($reg['r2'], 2, ',', '.'), $confianza, self::money($reg['se'])
        );
        return self::clamp564($t);
    }
```

- [ ] **Step 4: Ejecutar y ver pasar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `ok` en los 5 tests de análisis.

- [ ] **Step 5: Commit**

```bash
git add includes/class-stats.php tests/test-analisis.php
git commit -m "feat(stats): generadores de párrafos (descripción/cualitativo/cuantitativo/predicción)"
```

---

## Task 8: Esqueleto de `Tracking` + queries de vigencia + compatibilidad dimensión↔tipo

**Files:**
- Create: `includes/class-tracking.php`
- Modify: `secop-suite.php` (instanciar + getter)
- Test: `tests/test-compat.php`

- [ ] **Step 1: Test que falla (compatibilidad, función pura estática)**

`tests/test-compat.php` (carga la clase con un stub mínimo de funciones WP):
```php
<?php
// Stubs mínimos para cargar class-tracking.php sin WordPress.
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
foreach (['add_action','add_shortcode','register_post_type','add_meta_box'] as $fn) {
    if (!function_exists($fn)) { eval("function {$fn}() {}"); }
}
require dirname(__DIR__) . '/includes/class-tracking.php';
use SecopSuite\Tracking;

it('tipos compatibles por dimension', function () {
    assert_true(Tracking::is_compatible('dependencia', 'bar'));
    assert_true(Tracking::is_compatible('mensual', 'line'));
    assert_true(!Tracking::is_compatible('mensual', 'pie'));
    assert_true(!Tracking::is_compatible('ejecucion', 'treemap'));
});
it('fallback al primer tipo compatible', function () {
    assert_eq('line', Tracking::default_type('mensual'));
    assert_eq('bar', Tracking::default_type('dependencia'));
});
it('help text lista los tipos', function () {
    assert_true(str_contains(Tracking::compat_help('mensual'), 'line'));
});
```

- [ ] **Step 2: Ejecutar y ver fallar**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: FAIL — clase/métodos no definidos.

- [ ] **Step 3: Crear `class-tracking.php` (parte 1: constantes + compatibilidad + esqueleto de hooks)**

```php
<?php
/**
 * Tracking — Módulo de Seguimiento de Dependencias y Datos Abiertos.
 *
 * @package SecopSuite
 */
declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Tracking
{
    private Database $db;
    private const POST_TYPE = 'secop_dep_card';

    /** Dimensiones del módulo → tipos de gráfica compatibles. */
    private const COMPAT = [
        'dependencia'   => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'tipo_contrato' => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'modalidad'     => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'fuente'        => ['bar', 'stacked_bar', 'treemap', 'pie', 'donut'],
        'mensual'       => ['line', 'area'],
        'ejecucion'     => ['donut', 'bar'],
    ];

    /** Columna del VIEW que agrupa cada dimensión. */
    private const DIM_COLUMN = [
        'dependencia'   => 'nombredependencia',
        'tipo_contrato' => 'tipo_de_contrato',
        'modalidad'     => 'modalidad_de_contratacion',
        'fuente'        => 'origen',
        'mensual'       => 'mes',
        'ejecucion'     => 'nombredependencia',
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->register_hooks();
    }

    // ── Compatibilidad dimensión ↔ tipo (puro, testeable) ──────
    public static function dimensions(): array { return array_keys(self::COMPAT); }

    public static function is_compatible(string $dimension, string $type): bool
    {
        return in_array($type, self::COMPAT[$dimension] ?? [], true);
    }

    public static function default_type(string $dimension): string
    {
        return self::COMPAT[$dimension][0] ?? 'bar';
    }

    public static function compat_help(string $dimension): string
    {
        $types = self::COMPAT[$dimension] ?? [];
        return sprintf(
            'Tipos compatibles para «%s»: %s.',
            $dimension,
            implode(', ', $types)
        );
    }

    private function register_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);
        // Shortcodes, AJAX, metaboxes y assets se añaden en tareas posteriores.
    }

    public function register_post_type(): void { /* Task 9 */ }
}
```

- [ ] **Step 4: Ejecutar y ver pasar; instanciar en el plugin**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `ok` en los tests de compatibilidad.

En `secop-suite.php`: añadir propiedad `private Tracking $tracking;`, instanciar en
`__construct()` tras `$this->updater`:
```php
        $this->tracking = new Tracking($this->database);
```
y getter:
```php
    public function tracking(): Tracking { return $this->tracking; }
```

- [ ] **Step 5: Lint y commit**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-tracking.php && /c/Users/Usuario/php-portable/php.exe -l secop-suite.php`
Expected: `No syntax errors detected`.
```bash
git add includes/class-tracking.php secop-suite.php tests/test-compat.php
git commit -m "feat(tracking): esqueleto + mapa de compatibilidad dimensión/tipo"
```

---

## Task 9: Queries del VIEW (vigencia actual) en `Tracking`

**Files:**
- Modify: `includes/class-tracking.php`

Métodos que construyen el "dataset de análisis" (el mismo formato que consumen los
generadores de Task 7) y datos para gráficas. Verificación: manual (requiere BD/VIEW).

- [ ] **Step 1: Implementar helpers de datos**

Añadir a `Tracking`:
```php
    /** Vigencia activa (año actual), server-side. */
    public function current_vigencia(): int
    {
        return (int) current_time('Y');
    }

    /** Agrupación por dimensión para la vigencia actual. */
    public function group_by_dimension(string $dimension, ?string $dependencia = null): array
    {
        global $wpdb;
        if (!isset(self::DIM_COLUMN[$dimension])) return [];
        $view   = $this->db->get_view_name();
        $col    = self::DIM_COLUMN[$dimension];
        $cols   = $this->db->get_table_columns($view);
        if (!isset($cols[$col])) return [];

        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dependencia !== null && $dependencia !== '') {
            $where[]  = 'nombredependencia = %s';
            $params[] = $dependencia;
        }
        $where_sql = implode(' AND ', $where);

        // Métrica: suma de débito (ejecución) y conteo de contratos distintos.
        $sql = "SELECT `{$col}` AS label,
                       SUM(valordebito) AS valor,
                       COUNT(DISTINCT numero_del_contrato) AS conteo
                FROM `{$view}` WHERE {$where_sql}
                GROUP BY `{$col}` ORDER BY valor DESC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map(fn($r) => [
            'label'  => $r['label'] ?? 'N/D',
            'valor'  => (float) $r['valor'],
            'conteo' => (int) $r['conteo'],
        ], $rows ?: []);
    }

    /** Serie mensual acumulada de ejecución (para predicción). */
    public function monthly_series(?string $dependencia = null): array
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dependencia !== null && $dependencia !== '') {
            $where[]  = 'nombredependencia = %s';
            $params[] = $dependencia;
        }
        $where_sql = implode(' AND ', $where);
        $sql = "SELECT mes, SUM(valordebito) AS valor FROM `{$view}`
                WHERE {$where_sql} GROUP BY mes ORDER BY mes ASC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $acc = 0.0; $serie = [];
        foreach ($rows ?: [] as $r) {
            $acc += (float) $r['valor'];
            $serie[] = [(int) $r['mes'], $acc];
        }
        return $serie;
    }

    /** Construir el dataset de análisis completo para una card. */
    public function build_dataset(string $dimension, ?string $dependencia = null): array
    {
        global $wpdb;
        $cats  = $this->group_by_dimension($dimension, $dependencia);
        $serie = $this->monthly_series($dependencia);
        $view  = $this->db->get_view_name();

        $where  = ['anio = %d'];
        $params = [$this->current_vigencia()];
        if ($dependencia) { $where[] = 'nombredependencia = %s'; $params[] = $dependencia; }
        $where_sql = implode(' AND ', $where);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $tot = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(valordebito) AS ejec, SUM(saldoporejecutaresp) AS saldo,
                    COUNT(DISTINCT numero_del_contrato) AS conteo,
                    SUM(valor_contrato) AS valc
             FROM `{$view}` WHERE {$where_sql}", ...$params), ARRAY_A);

        return [
            'dimension'     => $dimension,
            'vigencia'      => $this->current_vigencia(),
            'meses'         => count($serie),
            'categorias'    => $cats,
            'serie_mensual' => $serie,
            'total_valor'   => (float) ($tot['valc'] ?? 0),
            'total_conteo'  => (int) ($tot['conteo'] ?? 0),
            'ejecutado'     => (float) ($tot['ejec'] ?? 0),
            'saldo'         => (float) ($tot['saldo'] ?? 0),
        ];
    }
```

- [ ] **Step 2: Lint**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-tracking.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add includes/class-tracking.php
git commit -m "feat(tracking): queries del VIEW por dimensión y serie mensual (vigencia actual)"
```

---

## Task 10: CPT `secop_dep_card` + metaboxes + guardado

**Files:**
- Modify: `includes/class-tracking.php`
- Create: `templates/admin/dep-card-config.php`

Patrón espejo de `Visualizer` (líneas 67-184 de `class-visualizer.php`). Verificación: manual.

- [ ] **Step 1: Implementar `register_post_type`, metaboxes y save**

Reemplazar el stub `register_post_type()` y registrar hooks en `register_hooks()`:
```php
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_card_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
```
```php
    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => __('Cards de Dependencias', 'secop-suite'),
                'singular_name' => __('Card', 'secop-suite'),
                'menu_name'     => __('Seguimiento Dependencias', 'secop-suite'),
                'add_new_item'  => __('Nueva Card', 'secop-suite'),
                'edit_item'     => __('Editar Card', 'secop-suite'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'secop-suite',
            'capability_type'    => 'post',
            'supports'           => ['title'],
            'menu_icon'          => 'dashicons-analytics',
        ]);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box('secop_dep_card_config', __('Configuración de la Card', 'secop-suite'),
            [$this, 'render_config_metabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('secop_dep_card_shortcodes', __('Shortcodes', 'secop-suite'),
            [$this, 'render_shortcodes_metabox'], self::POST_TYPE, 'side', 'high');
        add_meta_box('secop_dep_card_preview', __('Análisis y Vista Previa', 'secop-suite'),
            [$this, 'render_preview_metabox'], self::POST_TYPE, 'normal', 'default');
    }

    public function render_config_metabox(\WP_Post $post): void
    {
        wp_nonce_field('secop_dep_card_config', 'secop_dep_card_nonce');
        $config = get_post_meta($post->ID, '_secop_dep_card_config', true) ?: [];
        $dimensions = self::COMPAT;
        include SECOP_SUITE_DIR . 'templates/admin/dep-card-config.php';
    }

    public function render_shortcodes_metabox(\WP_Post $post): void
    {
        $id = (int) $post->ID;
        echo '<p>' . esc_html__('Gráfica:', 'secop-suite') . '</p>';
        echo '<code>[secop_dep_chart card="' . $id . '"]</code>';
        foreach (['descripcion', 'cualitativo', 'cuantitativo', 'prediccion'] as $tipo) {
            echo '<p><code>[secop_dep_analisis card="' . $id . '" tipo="' . $tipo . '"]</code></p>';
        }
    }

    public function render_preview_metabox(\WP_Post $post): void
    {
        $config = get_post_meta($post->ID, '_secop_dep_card_config', true) ?: [];
        if (empty($config['dimension'])) {
            echo '<p>' . esc_html__('Guarde la card para ver el análisis.', 'secop-suite') . '</p>';
            return;
        }
        $ds = \SecopSuite\Plugin::get_instance()->tracking()->build_dataset(
            $config['dimension'], $config['dependencia'] ?? null
        );
        foreach (['descripcion', 'cualitativo', 'cuantitativo', 'prediccion'] as $tipo) {
            $m = 'analisis_' . $tipo;
            echo '<h4>' . esc_html(ucfirst($tipo)) . '</h4><p>' . esc_html(Stats::$m($ds)) . '</p>';
        }
    }

    public function save_card_meta(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['secop_dep_card_nonce']) ||
            !wp_verify_nonce($_POST['secop_dep_card_nonce'], 'secop_dep_card_config')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $dimension = sanitize_text_field($_POST['dep_dimension'] ?? 'dependencia');
        if (!isset(self::COMPAT[$dimension])) $dimension = 'dependencia';
        $type = sanitize_text_field($_POST['dep_chart_type'] ?? '');
        if (!self::is_compatible($dimension, $type)) $type = self::default_type($dimension);

        update_post_meta($post_id, '_secop_dep_card_config', [
            'dimension'   => $dimension,
            'chart_type'  => $type,
            'dependencia' => sanitize_text_field($_POST['dep_dependencia'] ?? ''),
            'metric'      => sanitize_text_field($_POST['dep_metric'] ?? 'valordebito'),
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) return;
        // Reutiliza las librerías de gráfica del Visualizer vía el handle compartido.
        wp_enqueue_style('secop-suite-admin', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
    }
```

- [ ] **Step 2: Plantilla de configuración**

`templates/admin/dep-card-config.php`:
```php
<?php if (!defined('ABSPATH')) exit; ?>
<table class="form-table">
  <tr>
    <th><label for="dep_dimension"><?php esc_html_e('Dimensión', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_dimension" id="dep_dimension">
        <?php foreach ($dimensions as $dim => $types) : ?>
          <option value="<?php echo esc_attr($dim); ?>" <?php selected($config['dimension'] ?? '', $dim); ?>>
            <?php echo esc_html($dim); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php esc_html_e('Cada dimensión admite ciertos tipos de gráfica.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_chart_type"><?php esc_html_e('Tipo de gráfica', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_chart_type" id="dep_chart_type">
        <?php foreach ($dimensions as $dim => $types) : foreach ($types as $tp) : ?>
          <option value="<?php echo esc_attr($tp); ?>" data-dim="<?php echo esc_attr($dim); ?>"
            <?php selected($config['chart_type'] ?? '', $tp); ?>><?php echo esc_html($tp); ?></option>
        <?php endforeach; endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_dependencia"><?php esc_html_e('Dependencia (opcional)', 'secop-suite'); ?></label></th>
    <td><input type="text" name="dep_dependencia" id="dep_dependencia"
        value="<?php echo esc_attr($config['dependencia'] ?? ''); ?>" class="regular-text"
        placeholder="<?php esc_attr_e('Vacío = todas las dependencias', 'secop-suite'); ?>"></td>
  </tr>
</table>
```

- [ ] **Step 3: Lint y checkpoint manual**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-tracking.php`
Checkpoint: crear una card en wp-admin, guardar, ver shortcodes y los 4 párrafos en la vista previa.

- [ ] **Step 4: Commit**

```bash
git add includes/class-tracking.php templates/admin/dep-card-config.php
git commit -m "feat(tracking): CPT secop_dep_card con metaboxes, guardado y vista previa de análisis"
```

---

## Task 11: Shortcodes `[secop_dep_chart]` y `[secop_dep_analisis]` + assets

**Files:**
- Modify: `includes/class-tracking.php`
- Create: `templates/frontend/dep-chart.php`
- Create: `assets/js/dep-tracking.js`
- Modify: `assets/css/frontend.css`

- [ ] **Step 1: Registrar shortcodes y AJAX en `register_hooks()`**

```php
        add_shortcode('secop_dep_chart',    [$this, 'sc_chart']);
        add_shortcode('secop_dep_analisis', [$this, 'sc_analisis']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_secop_dep_chart_data',        [$this, 'ajax_chart_data']);
        add_action('wp_ajax_nopriv_secop_dep_chart_data', [$this, 'ajax_chart_data']);
```

- [ ] **Step 2: Implementar shortcodes y resolución de config**

```php
    private function resolve_config(array $atts): array
    {
        if (!empty($atts['card'])) {
            $cfg = get_post_meta((int) $atts['card'], '_secop_dep_card_config', true) ?: [];
        } else {
            $cfg = [];
        }
        $dimension = sanitize_text_field($atts['dimension'] ?? ($cfg['dimension'] ?? 'dependencia'));
        if (!isset(self::COMPAT[$dimension])) $dimension = 'dependencia';
        $type = sanitize_text_field($atts['tipo'] ?? ($cfg['chart_type'] ?? ''));
        if (!self::is_compatible($dimension, $type)) $type = self::default_type($dimension);
        $dep = sanitize_text_field($atts['dependencia'] ?? ($cfg['dependencia'] ?? ''));
        return ['dimension' => $dimension, 'chart_type' => $type, 'dependencia' => $dep];
    }

    public function sc_chart(array $atts): string
    {
        $atts = shortcode_atts(
            ['card' => 0, 'dimension' => '', 'tipo' => '', 'dependencia' => '', 'height' => 400],
            $atts, 'secop_dep_chart'
        );
        $cfg = $this->resolve_config($atts);
        $uid = 'ss-dep-' . wp_unique_id();
        $help = self::compat_help($cfg['dimension']);
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-chart.php';
        return ob_get_clean();
    }

    public function sc_analisis(array $atts): string
    {
        $atts = shortcode_atts(['card' => 0, 'tipo' => 'descripcion'], $atts, 'secop_dep_analisis');
        $cfg = get_post_meta((int) $atts['card'], '_secop_dep_card_config', true) ?: [];
        if (empty($cfg['dimension'])) return '';
        $tipo = in_array($atts['tipo'], ['descripcion','cualitativo','cuantitativo','prediccion'], true)
            ? $atts['tipo'] : 'descripcion';
        $ds = $this->build_dataset($cfg['dimension'], $cfg['dependencia'] ?? null);
        $m = 'analisis_' . $tipo;
        return '<p class="ss-dep-analisis ss-dep-' . esc_attr($tipo) . '">'
             . esc_html(Stats::$m($ds)) . '</p>';
    }

    public function ajax_chart_data(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        $ip_key = 'secop_dep_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        if ((int) get_transient($ip_key) > 60) wp_send_json_error(['message' => 'Demasiadas solicitudes'], 429);
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, MINUTE_IN_SECONDS);

        $dimension = sanitize_text_field($_POST['dimension'] ?? 'dependencia');
        if (!isset(self::COMPAT[$dimension])) wp_send_json_error(['message' => 'Dimensión inválida']);
        $dep  = sanitize_text_field($_POST['dependencia'] ?? '');
        $rows = $this->group_by_dimension($dimension, $dep ?: null);
        wp_send_json_success(['data' => $rows]);
    }

    public function enqueue_frontend_assets(): void
    {
        global $post;
        if (!is_a($post, 'WP_Post')) return;
        $has = false;
        foreach (['secop_dep_chart','secop_seguimiento','secop_dep_contratos','secop_consulta'] as $sc) {
            if (has_shortcode($post->post_content, $sc)) { $has = true; break; }
        }
        if (!$has) return;

        // Reutilizar las librerías d3/d3plus del Visualizer.
        Plugin::get_instance()->visualizer(); // asegura registro
        do_action('secop_suite_enqueue_chart_libs');

        wp_enqueue_style('secop-suite-frontend', SECOP_SUITE_URL . 'assets/css/frontend.css', [], SECOP_SUITE_VERSION);
        wp_enqueue_script('secop-dep-tracking', SECOP_SUITE_URL . 'assets/js/dep-tracking.js',
            ['jquery', 'd3plus'], SECOP_SUITE_VERSION, true);
        wp_localize_script('secop-dep-tracking', 'secopDep', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('secop_dep_frontend'),
        ]);
    }
```

Nota: para reutilizar las librerías d3/d3plus, en `Visualizer::register_hooks()` añadir
`add_action('secop_suite_enqueue_chart_libs', [$this, 'enqueue_chart_libraries']);` y cambiar
`enqueue_chart_libraries` a visibilidad `public`. (Cambio mínimo, sin alterar comportamiento.)

- [ ] **Step 3: Plantilla de gráfica**

`templates/frontend/dep-chart.php`:
```php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="ss-dep-chart-wrapper" id="<?php echo esc_attr($uid); ?>"
     data-dimension="<?php echo esc_attr($cfg['dimension']); ?>"
     data-type="<?php echo esc_attr($cfg['chart_type']); ?>"
     data-dependencia="<?php echo esc_attr($cfg['dependencia']); ?>"
     style="min-height:<?php echo (int) $atts['height']; ?>px">
  <div class="ss-dep-chart-target"></div>
  <p class="ss-dep-help description"><?php echo esc_html($help); ?></p>
</div>
```

- [ ] **Step 4: JS de render d3plus con tooltips**

`assets/js/dep-tracking.js`:
```javascript
(function ($) {
  'use strict';
  function fmtMoney(v) {
    return '$' + Math.round(v).toLocaleString('es-CO');
  }
  function render(el) {
    var $el = $(el), target = $el.find('.ss-dep-chart-target')[0];
    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_chart_data',
      nonce: secopDep.nonce,
      dimension: $el.data('dimension'),
      dependencia: $el.data('dependencia') || ''
    }).done(function (res) {
      if (!res.success || !res.data.length) { $(target).html('<p>No hay datos.</p>'); return; }
      var type = $el.data('type');
      var data = res.data.map(function (d) {
        return { id: d.label, valor: +d.valor, conteo: +d.conteo };
      });
      var viz = (type === 'treemap') ? new d3plus.Treemap()
              : (type === 'donut' || type === 'pie') ? new d3plus.Donut()
              : (type === 'line' || type === 'area') ? new d3plus.LinePlot()
              : new d3plus.BarChart();
      viz.select(target).data(data).groupBy('id')
        .tooltipConfig({
          title: function (d) { return d.id; },
          tbody: function (d) {
            return [['Valor ejecutado', fmtMoney(d.valor)], ['Contratos', d.conteo]];
          }
        });
      if (viz.x) { viz.x('id'); }
      if (viz.y) { viz.y('valor'); }
      viz.render();
    });
  }
  $(function () { $('.ss-dep-chart-wrapper').each(function () { render(this); }); });
  window.secopDepRender = render;
})(jQuery);
```

- [ ] **Step 5: Estilos del módulo en `frontend.css`**

```css
/* Módulo Seguimiento de Dependencias */
.ss-dep-chart-wrapper { margin: 1.5rem 0; }
.ss-dep-help { font-size: .8rem; color: #666; margin-top: .5rem; }
.ss-dep-analisis { line-height: 1.6; margin: .75rem 0; }
.ss-dep-prediccion { font-style: italic; }
```

- [ ] **Step 6: Lint y checkpoint manual**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-tracking.php`
Checkpoint: colocar `[secop_dep_chart card="N"]` y `[secop_dep_analisis card="N" tipo="prediccion"]`
en una página; verificar render de la gráfica, tooltips y los párrafos. Ajustar UI/UX.

- [ ] **Step 7: Commit**

```bash
git add includes/class-tracking.php includes/class-visualizer.php templates/frontend/dep-chart.php assets/js/dep-tracking.js assets/css/frontend.css
git commit -m "feat(tracking): shortcodes [secop_dep_chart] y [secop_dep_analisis] con d3plus y tooltips"
```

---

## Task 12: Interactividad — `[secop_seguimiento]` + `[secop_dep_contratos]`

**Files:**
- Modify: `includes/class-tracking.php`
- Create: `templates/frontend/dep-seguimiento.php`
- Create: `templates/frontend/dep-contratos.php`
- Modify: `assets/js/dep-tracking.js`

- [ ] **Step 1: Registrar shortcodes y AJAX de contratos**

En `register_hooks()`:
```php
        add_shortcode('secop_seguimiento',    [$this, 'sc_seguimiento']);
        add_shortcode('secop_dep_contratos',  [$this, 'sc_contratos']);
        add_action('wp_ajax_secop_dep_contratos',        [$this, 'ajax_contratos']);
        add_action('wp_ajax_nopriv_secop_dep_contratos', [$this, 'ajax_contratos']);
```

- [ ] **Step 2: Query y AJAX de contratos (deduplicados por contrato)**

```php
    /** Lista de contratos de una dependencia (vigencia actual), deduplicados. */
    public function contracts_by_dependency(string $dependencia, int $limit = 100): array
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        $sql = "SELECT numero_del_contrato, url_contrato, nom_raz_social_contratista,
                       fecha_inicio_ejecucion, fecha_fin_ejecucion, valor_contrato, objeto_del_proceso
                FROM `{$view}` WHERE anio = %d AND nombredependencia = %s
                GROUP BY numero_del_contrato
                ORDER BY valor_contrato DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $this->current_vigencia(), $dependencia, $limit), ARRAY_A);
        return $rows ?: [];
    }

    public function ajax_contratos(): void
    {
        check_ajax_referer('secop_dep_frontend', 'nonce');
        $dep = sanitize_text_field($_POST['dependencia'] ?? '');
        if ($dep === '') wp_send_json_error(['message' => 'Dependencia requerida']);
        wp_send_json_success(['rows' => $this->contracts_by_dependency($dep)]);
    }

    /** Dependencias disponibles en la vigencia (para el selector). */
    public function list_dependencies(): array
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT nombredependencia FROM `{$view}` WHERE anio = %d AND nombredependencia <> '' ORDER BY nombredependencia",
            $this->current_vigencia()
        )) ?: [];
    }
```

- [ ] **Step 3: Shortcodes de landing y tabla**

```php
    public function sc_contratos(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'per_page' => 50], $atts, 'secop_dep_contratos');
        $dep  = sanitize_text_field($atts['dependencia']);
        $rows = $dep ? $this->contracts_by_dependency($dep, (int) $atts['per_page']) : [];
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-contratos.php';
        return ob_get_clean();
    }

    public function sc_seguimiento(array $atts): string
    {
        $atts = shortcode_atts(['dependencia' => '', 'dimensiones' => 'dependencia,modalidad,fuente'], $atts, 'secop_seguimiento');
        $deps = $this->list_dependencies();
        $dimensiones = array_filter(array_map('trim', explode(',', $atts['dimensiones'])),
            fn($d) => isset(self::COMPAT[$d]));
        $sel = sanitize_text_field($atts['dependencia']);
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/dep-seguimiento.php';
        return ob_get_clean();
    }
```

- [ ] **Step 4: Plantilla de tabla de contratos**

`templates/frontend/dep-contratos.php`:
```php
<?php if (!defined('ABSPATH')) exit; ?>
<table class="ss-dep-contratos widefat">
  <thead><tr>
    <th><?php esc_html_e('N° Contrato', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Proveedor', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Inicio', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Fin', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Valor', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
  </tr></thead>
  <tbody>
  <?php if (!$rows) : ?>
    <tr><td colspan="6"><?php esc_html_e('Seleccione una dependencia.', 'secop-suite'); ?></td></tr>
  <?php else : foreach ($rows as $r) : ?>
    <tr>
      <td><?php if (!empty($r['url_contrato'])) : ?>
        <a href="<?php echo esc_url($r['url_contrato']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['numero_del_contrato']); ?></a>
      <?php else : echo esc_html($r['numero_del_contrato']); endif; ?></td>
      <td><?php echo esc_html($r['nom_raz_social_contratista']); ?></td>
      <td><?php echo esc_html(substr((string) $r['fecha_inicio_ejecucion'], 0, 10)); ?></td>
      <td><?php echo esc_html(substr((string) $r['fecha_fin_ejecucion'], 0, 10)); ?></td>
      <td><?php echo esc_html(\SecopSuite\Stats::money((float) $r['valor_contrato'])); ?></td>
      <td><?php echo esc_html(wp_trim_words((string) $r['objeto_del_proceso'], 20)); ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
```

- [ ] **Step 5: Plantilla de landing interactiva**

`templates/frontend/dep-seguimiento.php`:
```php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="ss-seguimiento" data-nonce="<?php echo esc_attr(wp_create_nonce('secop_dep_frontend')); ?>">
  <div class="ss-seguimiento-controls">
    <label><?php esc_html_e('Dependencia:', 'secop-suite'); ?>
      <select class="ss-dep-selector">
        <option value=""><?php esc_html_e('— Todas —', 'secop-suite'); ?></option>
        <?php foreach ($deps as $d) : ?>
          <option value="<?php echo esc_attr($d); ?>" <?php selected($sel, $d); ?>><?php echo esc_html($d); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="ss-seguimiento-charts">
    <?php foreach ($dimensiones as $dim) : ?>
      <div class="ss-dep-chart-wrapper" data-dimension="<?php echo esc_attr($dim); ?>"
           data-type="<?php echo esc_attr(\SecopSuite\Tracking::default_type($dim)); ?>"
           data-dependencia="<?php echo esc_attr($sel); ?>" style="min-height:380px">
        <h4><?php echo esc_html(ucfirst($dim)); ?></h4>
        <div class="ss-dep-chart-target"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <h3><?php esc_html_e('Contratos de la dependencia', 'secop-suite'); ?></h3>
  <div class="ss-seguimiento-contratos"><?php echo do_shortcode('[secop_dep_contratos dependencia="' . esc_attr($sel) . '"]'); ?></div>
</div>
```

- [ ] **Step 6: JS de interactividad (al cambiar dependencia → re-render + tabla)**

Añadir a `dep-tracking.js`:
```javascript
  $(function () {
    $('.ss-seguimiento').on('change', '.ss-dep-selector', function () {
      var dep = $(this).val(), $root = $(this).closest('.ss-seguimiento');
      $root.find('.ss-dep-chart-wrapper').attr('data-dependencia', dep).each(function () {
        window.secopDepRender(this);
      });
      $.post(secopDep.ajaxUrl, { action: 'secop_dep_contratos', nonce: secopDep.nonce, dependencia: dep })
        .done(function (res) {
          if (!res.success) return;
          var html = res.rows.map(function (r) {
            var num = r.url_contrato
              ? '<a href="' + r.url_contrato + '" target="_blank" rel="noopener">' + r.numero_del_contrato + '</a>'
              : r.numero_del_contrato;
            return '<tr><td>' + num + '</td><td>' + (r.nom_raz_social_contratista || '') + '</td><td>' +
              String(r.fecha_inicio_ejecucion || '').slice(0,10) + '</td><td>' +
              String(r.fecha_fin_ejecucion || '').slice(0,10) + '</td><td>$' +
              Math.round(r.valor_contrato || 0).toLocaleString('es-CO') + '</td><td>' +
              String(r.objeto_del_proceso || '').slice(0,120) + '</td></tr>';
          }).join('');
          $root.find('.ss-seguimiento-contratos table tbody').html(html || '<tr><td colspan="6">Sin contratos.</td></tr>');
        });
    });
  });
```

- [ ] **Step 7: Lint y checkpoint manual**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-tracking.php`
Checkpoint: `[secop_seguimiento]` en una página; al elegir dependencia, las gráficas y la
tabla se actualizan. Ajustar UI/UX (responsive, orden de cards).

- [ ] **Step 8: Commit**

```bash
git add includes/class-tracking.php templates/frontend/dep-seguimiento.php templates/frontend/dep-contratos.php assets/js/dep-tracking.js
git commit -m "feat(tracking): landing [secop_seguimiento] interactiva + tabla de contratos por dependencia"
```

---

## Task 13: Datos Abiertos — `[secop_consulta]` + REST + submenú

**Files:**
- Modify: `includes/class-tracking.php`
- Modify: `includes/class-rest-api.php`
- Create: `templates/frontend/consulta.php`
- Create: `templates/admin/datos-abiertos-page.php`
- Modify: `secop-suite.php` (submenú)

- [ ] **Step 1: Endpoint REST `GET /consulta`**

Revisar `includes/class-rest-api.php` (registro de rutas) y añadir, junto a las rutas
existentes en su método `register_routes()`:
```php
        register_rest_route('secop-suite/v1', '/consulta', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_consulta'],
            'permission_callback' => '__return_true',
            'args' => [
                'page'     => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 100, 'sanitize_callback' => 'absint'],
            ],
        ]);
```
Y el callback (usa el VIEW, vigencia actual):
```php
    public function get_consulta(\WP_REST_Request $req): \WP_REST_Response
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        $vig  = (int) current_time('Y');
        $per  = min(1000, max(1, (int) $req['per_page']));
        $off  = (max(1, (int) $req['page']) - 1) * $per;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT nombredependencia, numero_de_proceso, numero_del_contrato, nombretercero,
                    valordebito, valorcredito, saldoporejecutaresp, valor_contrato, anio, mes
             FROM `{$view}` WHERE anio = %d ORDER BY valordebito DESC LIMIT %d OFFSET %d",
            $vig, $per, $off
        ), ARRAY_A);
        return new \WP_REST_Response(['vigencia' => $vig, 'page' => (int) $req['page'], 'data' => $rows ?: []], 200);
    }
```
Nota: confirmar que `Rest_Api` tiene acceso a `$this->db` (Database). Si no, inyectarla.

- [ ] **Step 2: Shortcode `[secop_consulta]` (tabla/csv/txt/json)**

En `Tracking::register_hooks()`: `add_shortcode('secop_consulta', [$this, 'sc_consulta']);`
```php
    public function sc_consulta(array $atts): string
    {
        $atts = shortcode_atts(['formato' => 'tabla'], $atts, 'secop_consulta');
        $formato = in_array($atts['formato'], ['tabla','csv','txt','json'], true) ? $atts['formato'] : 'tabla';
        $rest = rest_url('secop-suite/v1/consulta');
        $rows = $this->group_by_dimension('dependencia'); // resumen para la vista tabla
        $vig  = $this->current_vigencia();
        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/consulta.php';
        return ob_get_clean();
    }
```

- [ ] **Step 3: Plantilla del visor de consulta**

`templates/frontend/consulta.php`:
```php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="ss-consulta">
  <p><?php printf(esc_html__('Datos abiertos de seguimiento — vigencia %d. Disponible como API:', 'secop-suite'), (int) $vig); ?></p>
  <p class="ss-consulta-links">
    <a class="button" href="<?php echo esc_url($rest); ?>" target="_blank" rel="noopener">JSON (API)</a>
    <a class="button" href="<?php echo esc_url(add_query_arg('_format', 'csv', $rest)); ?>" target="_blank" rel="noopener">CSV</a>
    <a class="button" href="<?php echo esc_url(add_query_arg('_format', 'txt', $rest)); ?>" target="_blank" rel="noopener">TXT</a>
  </p>
  <table class="ss-dep-contratos widefat">
    <thead><tr><th><?php esc_html_e('Dependencia', 'secop-suite'); ?></th>
      <th><?php esc_html_e('Valor ejecutado', 'secop-suite'); ?></th>
      <th><?php esc_html_e('Contratos', 'secop-suite'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r) : ?>
      <tr><td><?php echo esc_html($r['label']); ?></td>
          <td><?php echo esc_html(\SecopSuite\Stats::money($r['valor'])); ?></td>
          <td><?php echo (int) $r['conteo']; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
```
Nota: para CSV/TXT vía REST, reutilizar el mecanismo de formato existente de `[secop_export]`
en `Rest_Api` si ya soporta `_format`; si no, añadir manejo de `_format` en `get_consulta`
devolviendo `text/csv`/`text/plain`. (Verificar el patrón actual de export antes de duplicar.)

- [ ] **Step 4: Submenú "Datos Abiertos"**

En `secop-suite.php` `register_admin_menu()` añadir:
```php
        add_submenu_page('secop-suite', __('Datos Abiertos', 'secop-suite'), __('Datos Abiertos', 'secop-suite'),
            'manage_options', 'secop-suite-datos-abiertos', [$this, 'render_datos_abiertos_page']);
```
Y el método:
```php
    public function render_datos_abiertos_page(): void
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Sin permisos.', 'secop-suite'));
        include SECOP_SUITE_DIR . 'templates/admin/datos-abiertos-page.php';
    }
```

- [ ] **Step 5: Plantilla del hub admin**

`templates/admin/datos-abiertos-page.php`:
```php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1><?php esc_html_e('Datos Abiertos', 'secop-suite'); ?></h1>
  <p><?php esc_html_e('Shortcodes para publicar datos abiertos en la landing. Los usuarios podrán verlos o descargarlos en CSV, TXT o JSON (API).', 'secop-suite'); ?></p>
  <table class="widefat">
    <thead><tr><th><?php esc_html_e('Shortcode', 'secop-suite'); ?></th>
      <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th></tr></thead>
    <tbody>
      <tr><td><code>[secop_export]</code></td>
          <td><?php esc_html_e('Todos los contratos de la entidad. Formatos: CSV, TXT, JSON.', 'secop-suite'); ?></td></tr>
      <tr><td><code>[secop_consulta formato="tabla"]</code></td>
          <td><?php esc_html_e('Seguimiento de ejecución por dependencia (vigencia actual). Formatos: tabla, csv, txt, json.', 'secop-suite'); ?></td></tr>
    </tbody>
  </table>
</div>
```

- [ ] **Step 6: Lint y checkpoint manual**

Run: `/c/Users/Usuario/php-portable/php.exe -l includes/class-tracking.php && /c/Users/Usuario/php-portable/php.exe -l includes/class-rest-api.php && /c/Users/Usuario/php-portable/php.exe -l secop-suite.php`
Checkpoint: visitar el endpoint REST, probar `[secop_consulta]` y la página admin "Datos Abiertos".

- [ ] **Step 7: Commit**

```bash
git add includes/class-tracking.php includes/class-rest-api.php templates/frontend/consulta.php templates/admin/datos-abiertos-page.php secop-suite.php
git commit -m "feat: módulo Datos Abiertos — [secop_consulta], endpoint REST /consulta y submenú admin"
```

---

## Task 14: Pulido (UI/UX + seguridad) y entrega

**Files:**
- Modify: varios (según hallazgos)
- Modify: `README.md`

- [ ] **Step 1: Pase de seguridad**

Revisar manualmente (checklist del spec §10): nonces en todos los AJAX/forms, escape de
salida en todas las plantillas nuevas (`esc_html/esc_url/esc_attr`), `prepare()` en todas
las queries, `permission_callback` en REST, rate-limiting en endpoints públicos, validación
de dimensión/tipo/columnas contra whitelist. Ejecutar:
Run: `/code-review` (o revisión manual) sobre el diff de la rama.

- [ ] **Step 2: Ejecutar toda la batería de tests del núcleo**

Run: `/c/Users/Usuario/php-portable/php.exe tests/run.php`
Expected: `N passed, 0 failed`.

- [ ] **Step 3: Lint de todos los archivos PHP modificados**

Run: `for f in secop-suite.php includes/class-stats.php includes/class-tracking.php includes/class-database.php includes/class-rest-api.php; do /c/Users/Usuario/php-portable/php.exe -l "$f"; done`
Expected: `No syntax errors detected` en todos.

- [ ] **Step 4: Checkpoint manual integral (UI/UX)**

En el WordPress del usuario: recorrer la landing completa, responsividad, tooltips,
consistencia visual con el resto del plugin, cambio de vigencia (simular año), y aviso
si faltan tablas Sysman.

- [ ] **Step 5: README final + verificación de versión**

Confirmar `Version: 5.1.0` y changelog completo. Documentar los nuevos shortcodes en README.

- [ ] **Step 6: Commit y preparación del PR**

```bash
git add -A
git commit -m "docs: documentación de shortcodes del módulo + pulido final v5.1.0"
```

- [ ] **Step 7: Push y PR (requiere autenticación GitHub)**

```bash
git push -u origin claude/dependency-tracking-module
```
Crear PR a `GobernaciondeNarino/secop-suite` con resumen de los commits organizados por
cambio lógico. ⚠️ Requiere PAT/credenciales del usuario (no hay `gh`). Si falla la auth,
solicitar al usuario que configure el credential helper o provea un token.

---

## Notas de cierre

- **DRY/YAGNI:** se reutiliza el motor d3/d3plus existente; no se duplica el sistema de
  whitelist (el VIEW `dat_*` entra solo).
- **TDD:** todo el núcleo estadístico y de formato está cubierto por tests ejecutables.
- **Seguridad:** identificadores SQL solo desde `$wpdb->prefix`/whitelist; valores siempre
  por `prepare()`; nonces y rate-limiting en endpoints públicos.
- **Vigencia:** `current_time('Y')` server-side; cachés implícitas por año.
