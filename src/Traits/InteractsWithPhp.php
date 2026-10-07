<?php

namespace Yiendos\MySitesIde\Preprocessors\Php\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the fpm and cli compose services from the host. Every
 * `docker compose` call runs from the IDE root, as the my-sites-ide CLI is
 * run there.
 */
trait InteractsWithPhp
{
    /**
     * The compose services this plugin owns
     *
     * @var array<int, string>
     */
    protected array $phpServices = ['fpm', 'cli'];

    /**
     * Whether a service's container is up
     *
     * @param string $service
     * @return bool
     */
    protected function phpRunning(string $service): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running ' . escapeshellarg($service) . ' 2>/dev/null')) !== '';
    }

    /**
     * A service's container ID, running or not - empty when it has none
     *
     * @param string $service
     * @return string
     */
    protected function containerId(string $service): string
    {
        return trim((string) shell_exec('docker compose ps -aq ' . escapeshellarg($service) . ' 2>/dev/null'));
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }
}
