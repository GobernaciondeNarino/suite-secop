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
