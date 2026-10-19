<?php

declare(strict_types=1);

/*
 * The bundle builds on Akeneo PIM classes, so the tests run with the
 * autoloader of a PIM project that requires the bundle. Point
 * PIM_AUTOLOAD_PATH at its vendor/autoload.php, or run the tests from a PIM
 * project where the bundle is installed (the default path below).
 */
$candidates = array_filter([
    getenv('PIM_AUTOLOAD_PATH') ?: null,
    __DIR__ . '/../vendor/autoload.php',
    '/srv/pim/vendor/autoload.php',
]);

foreach ($candidates as $autoload) {
    if (is_file($autoload)) {
        $loader = require $autoload;
        $loader->addPsr4('DanMyers\\CustomEntityBundle\\', __DIR__ . '/../src/');
        $loader->addPsr4('DanMyers\\CustomEntityBundle\\Tests\\', __DIR__ . '/');
        $loader->addPsr4('Acme\\Bundle\\CustomEntityDemoBundle\\', __DIR__ . '/../examples/AcmeCustomEntityDemoBundle/');

        return;
    }
}

fwrite(STDERR, "Could not find an Akeneo PIM autoloader. Set PIM_AUTOLOAD_PATH to a PIM project's vendor/autoload.php.\n");
exit(1);
