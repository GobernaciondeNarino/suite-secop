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
