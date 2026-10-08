<?php

/**
 * Names each site's OpenTelemetry service after its folder in Repos/, so
 * fpm (serving every site) and cli (artisan, queues) report each one
 * separately rather than as unknown_service:php.
 *
 * Runs before the site's own code (auto_prepend_file in otel.ini) because the
 * OpenTelemetry SDK reads OTEL_SERVICE_NAME when the site's autoloader starts
 * it - before Laravel loads the site's .env. OTEL_SERVICE_NAME in the IDE's
 * .env (passed in as PHP_OTEL_SERVICE_NAME) names every site the same instead.
 *
 * Set on every request: putenv() outlives the request in an fpm worker, which
 * serves the next site too.
 */
if (getenv('OTEL_PHP_AUTOLOAD_ENABLED') !== 'true') {
    return;
}

(static function (): void {
    $name = getenv('PHP_OTEL_SERVICE_NAME');

    if ($name === false || $name === '') {
        // fpm: /opt/repos/<site>/deploy/public/index.php; cli: wherever artisan is run from
        $path = PHP_SAPI === 'cli' ? (string) getcwd() : (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $name = preg_match('#^/opt/repos/([^/]+)#', $path, $match) === 1 ? $match[1] : '';
    }

    putenv($name === '' ? 'OTEL_SERVICE_NAME' : "OTEL_SERVICE_NAME={$name}");
})();
