<?php

namespace Yiendos\MySitesIde\Preprocessors\Php\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Preprocessors\Php\Traits\InteractsWithPhp;

class PhpStartCommand extends Command
{
    use InteractsWithPhp;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('preprocessors:php-start')
            ->setDescription('Start the fpm and cli containers - or recreate them if their config changed')
        ;
    }

    /**
     * Always runs `up -d`, even when they're already running: it's a no-op
     * for up-to-date containers, and recreates ones whose compose config has
     * changed - e.g. new PHP_* values in .env, or containers created before
     * PHP became a plugin.
     *
     * A recreated fpm gets a new address on the network, and nginx keeps the
     * one it looked up when it started (the sites' vhosts name fpm:9000
     * directly) - so a running nginx is reloaded, or every site gives a 502.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $fpmBefore = $this->containerId('fpm');

        if ($this->compose($output, 'up -d ' . implode(' ', $this->phpServices)) !== 0) {
            $io->error('PHP did not start - see above.');
            return Command::FAILURE;
        }

        if ($this->containerId('fpm') !== $fpmBefore && $this->phpRunning('nginx') && $this->getApplication()?->has('servers:nginx-reload')) {
            $this->getApplication()->doRun(new ArrayInput(['command' => 'servers:nginx-reload']), $output);
        }

        $io->success('fpm and cli started.');

        return Command::SUCCESS;
    }
}
