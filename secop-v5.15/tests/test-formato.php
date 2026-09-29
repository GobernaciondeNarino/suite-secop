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
it('clamp recorta a 564 incluso sin espacios (caso patológico)', function () {
    $s = str_repeat('A', 700); // 700 chars, ningún espacio
    $out = SecopSuite\Stats::clamp564($s);
    assert_true(mb_strlen($out) <= 564, 'longitud <= 564 sin espacios, obtenida ' . mb_strlen($out));
});
