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
