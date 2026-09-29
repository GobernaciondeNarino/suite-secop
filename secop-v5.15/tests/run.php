<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
// Cargar la clase bajo prueba sin arrancar WordPress.
require dirname(__DIR__) . '/includes/class-stats.php';
foreach (glob(__DIR__ . '/test-*.php') as $f) { require $f; }
finish();
