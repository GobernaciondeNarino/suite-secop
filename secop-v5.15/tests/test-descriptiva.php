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
