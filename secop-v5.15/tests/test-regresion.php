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
