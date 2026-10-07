<?php

namespace Yiendos\MySitesIde\Preprocessors\Php\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Preprocessors\Php\Traits\InteractsWithPhp;

class PhpArtisanCommand extends Command
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
            ->setName('preprocessors:php-artisan')
            ->setDescription("Run artisan for a site in the cli container, e.g. preprocessors:php-artisan mysite -- migrate")
            ->addArgument('site', InputArgument::REQUIRED, 'Which site, as in Repos/<site>')
            ->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::REQUIRED, "artisan's own arguments - put them after -- so their options reach artisan")
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, "The Laravel app inside Repos/<site>, for a site that keeps it somewhere other than the IDE's IDE_APP_DIR (e.g. --dir=Sites, or . for the repository root)")
        ;
    }

    /**
     * In the site's application folder - Repos/<site>/<IDE_APP_DIR>, or
     * --dir for a site laid out differently.
     *
     * In cli rather than fpm: cli has its own, by default empty,
     * disable_functions list, so commands that fork or shell out work.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');
        // --dir, else the IDE's IDE_APP_DIR (deploy on an IDE that predates it)
        $app = trim((string) ($input->getOption('dir') ?? (getenv('IDE_APP_DIR') ?: 'deploy')), '/');
        $path = $app === '.' || $app === '' ? $site : "{$site}/{$app}";

        if (!is_file((getenv('IDE_ROOT') ?: getcwd()) . "/Repos/{$path}/artisan")) {
            $io->error("Repos/{$path}/artisan doesn't exist - is {$site} a Laravel site? If its app is in another folder, say which with --dir.");
            return Command::FAILURE;
        }

        if (!$this->phpRunning('cli')) {
            $io->error('The cli container is not running: php my-sites-ide preprocessors:php-start');
            return Command::FAILURE;
        }

        // -T when there's no terminal, so it works from scripts too
        $tty = stream_isatty(STDOUT) ? '' : '-T ';
        $arguments = implode(' ', array_map('escapeshellarg', $input->getArgument('arguments')));

        return $this->compose($output, "exec {$tty}-w " . escapeshellarg("/opt/repos/{$path}") . " cli php artisan {$arguments}") === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
