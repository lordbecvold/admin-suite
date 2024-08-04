<?php

namespace App\Command\Process;

use App\Util\AppUtil;
use App\Manager\DatabaseManager;
use App\Manager\MonitoringManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MonitoringProcessCommand
 *
 * Command to monitor services process (run in infinite loop as a service)
 *
 * @package App\Command\Process
 */
#[AsCommand(name: 'app:process:monitoring', description: 'Service monitoring process loop')]
class MonitoringProcessCommand extends Command
{
    private AppUtil $appUtil;
    private DatabaseManager $databaseManager;
    private MonitoringManager $monitoringManager;

    public function __construct(
        AppUtil $appUtil,
        DatabaseManager $databaseManager,
        MonitoringManager $monitoringManager
    ) {
        $this->appUtil = $appUtil;
        $this->databaseManager = $databaseManager;
        $this->monitoringManager = $monitoringManager;
        parent::__construct();
    }

    /**
     * Executes the command to monitoring services process
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int The exit code of the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // set up environment for CLI
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'console';

        // handle monitoring
        $this->monitor($io);

        return Command::SUCCESS;
    }

    /**
     * Monitor the services
     *
     * @param SymfonyStyle $io The io interface
     *
     * @return void
     */
    private function monitor(SymfonyStyle $io): void
    {
        // wait to database to be up
        while ($this->databaseManager->isDatabaseDown()) {
            $io->writeln('<fg=yellow>Waiting to ensure that the database is up...</>');
            sleep(10);
        }

        // flag to handle is database down
        $dbDownFlag = false;

        // infinite monitoring loop
        while (true) {
            // check if database is down
            if ($this->databaseManager->isDatabaseDown()) {
                // check if database down handled before
                if (!$dbDownFlag) {
                    // handle database down situation
                    $this->monitoringManager->handleDatabaseDown($io, $dbDownFlag);
                    $dbDownFlag = true;
                }

                // sleep to ensure that the database is up
                $io->writeln('<fg=yellow>Waiting to ensure that the database is up...</>');
                sleep(10);
            } else {
                if ($dbDownFlag) {
                    // reset the flag if the database is back up
                    $dbDownFlag = false;
                }

                // initialize monitoring process
                $this->monitoringManager->monitorInit($io);

                // sleep for the monitoring interval
                sleep($this->appUtil->getMonitoringInterval() * 60);
            }
        }
    }
}
