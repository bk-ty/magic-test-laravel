<?php

namespace MagicTest\MagicTest\Commands;

use Laravel\Dusk\Console\DuskCommand;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

class DuskServeCommand extends DuskCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dusk:serve
        {--browse : Open a browser instead of using headless mode}
        {--without-tty : Disable output to TTY}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application and run Dusk tests';

    /**
     * @var Process
     */
    protected $serve;

    public function handle()
    {
        $this->purgeScreenshots();

        $this->purgeConsoleLogs();

        $this->purgeSourceLogs();

        $options = collect($_SERVER['argv'])
            ->slice(2)
            ->diff([
                '--browse',
                '--without-tty',
                '--quiet',
                '-q',
                '--verbose',
                '-v',
                '-vv',
                '-vvv',
                '--no-interaction',
                '-n',
            ])
            ->values()
            ->all();

        return $this->withDuskEnvironment(function () use ($options) {


            $stdEnv = [];
            $stdEnv['DB_HOST'] = '127.0.0.1';

            $port = \Illuminate\Support\Facades\Process::run(
                "docker container ls --format \"table {{.Ports}}\" -a | grep 3306 | head -n 1 | awk -F '->' '{print $1}' | awk -F ':' '{print $2}'",
            );

            if ($port->successful()) {
                $stdEnv['DB_PORT'] = preg_replace(
                    '/\s+/',
                    '',
                    $port->output(),
                );
            }

            $this->serve($stdEnv);

            $process = (new Process(array_merge(
                $this->binary(),
                $this->phpunitArguments($options),
            ), null, array_merge($stdEnv, $this->env())))->setTimeout(timeout: null);

            try {
                $process->setTty(!$this->option('without-tty'));
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: ' . $e->getMessage());
            }

            try {
                return $process->run(function ($type, $line) {
                    $this->output->write($line);
                });
            } catch (ProcessSignaledException $e) {
                if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                    throw $e;
                }
            }
        });
    }

    /**
     * Build a process to run php artisan serve
     *
     * @return Process
     */
    protected function serve($env)
    {
        // Compatibility with Windows and Linux environment
        $arguments = [PHP_BINARY, 'artisan', 'serve'];

        $serve = new Process($arguments, null, $env);
        $serve->setTimeout(null);

        return tap($serve, function ($serve) {
            $serve->start(function ($type, $line) {
                $this->info('artisan:serve started');
            });
        });
    }
}
