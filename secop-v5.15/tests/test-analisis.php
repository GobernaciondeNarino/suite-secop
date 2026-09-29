<?php
use SecopSuite\Stats;

/** Dataset de prueba (forma que produce build_dataset). $over sobreescribe claves. */
function ds(array $over = []): array {
    $cats = [
        ['label' => 'Salud',     'valor' => 900.0, 'conteo' => 12, 'pct' => 0.60],
        ['label' => 'Educación', 'valor' => 400.0, 'conteo' => 10, 'pct' => 0.2667],
        ['label' => 'Vías',      'valor' => 200.0, 'conteo' => 8,  'pct' => 0.1333],
    ];
    $base = [
        'dimension' => 'dependencia', 'vigencia' => 2026, 'meses' => 6,
        'limit' => 50, 'total_categorias' => 3,
        'categorias' => $cats,
        'resto' => ['count' => 0, 'valor' => 0.0, 'conteo' => 0, 'pct' => 0.0],
        'lider' => $cats[0],
        'share1' => 0.60, 'share3' => 1.0, 'hhi' => 0.42, 'media' => 500.0, 'mediana' => 400.0,
        'serie_mensual' => [[1,100.0],[2,250.0],[3,500.0],[4,800.0],[5,1200.0],[6,1500.0]],
        'serie_lider'   => [[1,80.0],[2,200.0],[3,360.0],[4,560.0],[5,760.0],[6,900.0]],
        'total_valor' => 1500.0, 'total_conteo' => 30, 'ejecutado' => 900.0, 'saldo' => 600.0,
        'metric' => 'valor_contrato', 'metric_label' => 'Valor del contrato',
        'metric_is_money' => true, 'metric_unit' => 'contratos',
    ];
    return array_merge($base, $over);
}

/** Variante: contratistas × Nº de contratos, con límite (top 2 de 320). */
function ds_conteo(): array {
    $cats = [
        ['label' => 'Juan Pérez', 'valor' => 18.0, 'conteo' => 18, 'pct' => 0.15],
        ['label' => 'ACME SAS',   'valor' => 12.0, 'conteo' => 12, 'pct' => 0.10],
    ];
    return ds([
        'dimension' => 'tercero', 'metric' => 'contratos', 'metric_label' => 'Nº de contratos',
        'metric_is_money' => false, 'metric_unit' => 'contratos',
        'limit' => 2, 'total_categorias' => 320, 'categorias' => $cats, 'lider' => $cats[0],
        'resto' => ['count' => 318, 'valor' => 90.0, 'conteo' => 90, 'pct' => 0.75],
        'share1' => 0.15, 'share3' => 0.25, 'hhi' => 0.05, 'media' => 0.4, 'mediana' => 0.0,
        'total_valor' => 120.0, 'total_conteo' => 120,
        'serie_lider' => [[1,3.0],[2,7.0],[3,10.0],[4,13.0],[5,16.0],[6,18.0]],
    ]);
}

it('descripcion nombra la categoria lider con su valor y porcentaje', function () {
    $t = Stats::analisis_descripcion(ds());
    assert_true(str_contains($t, 'Salud'));
    assert_true(str_contains($t, '60%'));
    assert_true(str_contains($t, '2026'));
    assert_true(mb_strlen($t) <= 564);
});

it('descripcion con limite menciona el alcance y el resto agregado', function () {
    $t = Stats::analisis_descripcion(ds_conteo());
    assert_true(str_contains($t, 'top 2 de 320'));
    assert_true(str_contains(mb_strtolower($t), 'el resto'));
    assert_true(str_contains($t, 'Juan Pérez'));
    assert_true(mb_strlen($t) <= 564);
});

it('dos cards distintos (dimension/metrica/datos) producen descripciones distintas', function () {
    assert_true(Stats::analisis_descripcion(ds()) !== Stats::analisis_descripcion(ds_conteo()));
});

it('descripcion de conteo NO formatea la magnitud principal como dinero', function () {
    $t = Stats::analisis_descripcion(ds_conteo());
    // El líder tiene 18 contratos: debe aparecer "18" sin símbolo "$18".
    assert_true(str_contains($t, '18'));
    assert_true(!str_contains($t, '$18'));
});

it('cualitativo nombra al lider, da % y califica la concentracion', function () {
    $t = Stats::analisis_cualitativo(ds());
    assert_true(str_contains($t, 'Salud'));
    assert_true(str_contains($t, '60%'));
    assert_true(str_contains(mb_strtolower($t), 'desigual') || str_contains(mb_strtolower($t), 'equilibrad'));
    assert_true(mb_strlen($t) <= 564);
});

it('cuantitativo trae total, promedio, mediana y % de ejecucion (dinero)', function () {
    $t = Stats::analisis_cuantitativo(ds());
    assert_true(str_contains($t, '$'));
    assert_true(str_contains(mb_strtolower($t), 'promedio'));
    assert_true(str_contains(mb_strtolower($t), 'mediana'));
    assert_true(str_contains(mb_strtolower($t), 'ejecutado'));
    assert_true(mb_strlen($t) <= 564);
});

it('cuantitativo de conteo no incluye avance presupuestal ($)', function () {
    $t = Stats::analisis_cuantitativo(ds_conteo());
    assert_true(!str_contains(mb_strtolower($t), 'presupuesto'));
    assert_true(mb_strlen($t) <= 564);
});

it('prediccion categorica proyecta la categoria lider', function () {
    $t = Stats::analisis_prediccion(ds());
    assert_true(str_contains($t, 'Salud'));
    assert_true(str_contains(mb_strtolower($t), 'cerrar') || str_contains(mb_strtolower($t), 'ritmo'));
    assert_true(mb_strlen($t) <= 564);
});

it('prediccion mensual proyecta el cierre de toda la entidad', function () {
    $t = Stats::analisis_prediccion(ds(['dimension' => 'mensual']));
    assert_true(str_contains(mb_strtolower($t), 'entidad'));
    assert_true(str_contains($t, '2026'));
    assert_true(mb_strlen($t) <= 564);
});

it('prediccion con serie insuficiente lo advierte', function () {
    $t = Stats::analisis_prediccion(ds(['serie_lider' => [[1, 80.0]]]));
    assert_true(str_contains(mb_strtolower($t), 'suficiente') || str_contains(mb_strtolower($t), 'no es posible'));
});

it('sin categorias, los textos degradan con gracia (no fatal)', function () {
    $d = ds(['categorias' => [], 'lider' => null]);
    assert_true(mb_strlen(Stats::analisis_descripcion($d)) > 0);
    assert_true(mb_strlen(Stats::analisis_cualitativo($d)) > 0);
    assert_true(mb_strlen(Stats::analisis_cuantitativo($d)) > 0);
});

it('todas las descripciones respetan el limite de 564', function () {
    foreach ([ds(), ds_conteo(), ds(['dimension' => 'mensual'])] as $d) {
        foreach (['descripcion','cualitativo','cuantitativo','prediccion'] as $tipo) {
            $fn = 'analisis_' . $tipo;
            assert_true(mb_strlen(Stats::$fn($d)) <= 564);
        }
    }
});
