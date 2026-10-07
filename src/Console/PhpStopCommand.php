<?php

namespace Yiendos\MySitesIde\Preprocessors\Php\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Preprocessors\Php\Traits\InteractsWithPhp;

class PhpStopCommand extends Command
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
            ->setName('preprocessors:php-stop')
            ->setDescription('Stop the fpm and cli containers, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so preprocessors:php-start brings back
     * the same containers. The next ide:spark starts them again (autostart).
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $running = array_values(array_filter($this->phpServices, fn (string $service): bool => $this->phpRunning($service)));

        if ($running === []) {
            $io->writeln('fpm and cli are not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop ' . implode(' ', $running)) !== 0) {
            $io->error('PHP did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success(implode(' and ', $running) . ' stopped.');

        return Command::SUCCESS;
    }
}
