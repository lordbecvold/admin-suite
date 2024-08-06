<?php

namespace App\Command;

use App\Util\AppUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ToggleMaintenanceCommand
 *
 * The command to enable/disable maintenance mode
 *
 * @package App\Command
 */
#[AsCommand(name: 'app:toggle:maintenance', description: 'Enable/disable maintenance mode')]
class ToggleMaintenanceCommand extends Command
{
    private AppUtil $appUtil;

    public function __construct(AppUtil $appUtil)
    {
        $this->appUtil = $appUtil;
        parent::__construct();
    }

    /**
     * Execute the maintenance mode toggle command
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // get base .env file
        $mainEnvFile = $this->appUtil->getAppRootDir() . '/.env';

        // chec if .env file exists
        if (!file_exists($mainEnvFile)) {
            $io->error('.env file not found');
            return Command::FAILURE;
        }

        // load base .env file content
        $mainEnvContent = file_get_contents($mainEnvFile);
        if ($mainEnvContent === false) {
            $io->error('Failed to read .env file');
            return Command::FAILURE;
        }

        // load current environment name
        if (preg_match('/^APP_ENV=(\w+)$/m', $mainEnvContent, $matches)) {
            $env = $matches[1];
        } else {
            $io->error('APP_ENV not found in .env file');
            return Command::FAILURE;
        }

        // get current environment file
        $envFile = $this->appUtil->getAppRootDir() . '/.env.' . $env;

        // check if current environment file exists
        if (!file_exists($envFile)) {
            $io->error(".env.$env file not found");
            return Command::FAILURE;
        }

        // get current environment content
        $envContent = file_get_contents($envFile);

        // check if current environment loaded correctly
        if ($envContent === false) {
            $io->error("Failed to read .env.$env file");
            return Command::FAILURE;
        }

        try {
            // toggle maintenance mode
            if (preg_match('/^MAINTENANCE_MODE=(true|false)$/m', $envContent, $matches)) {
                $currentValue = $matches[1];
                $newValue = $currentValue === 'true' ? 'false' : 'true';
                $newEnvContent = preg_replace('/^MAINTENANCE_MODE=(true|false)$/m', "MAINTENANCE_MODE=$newValue", $envContent);

                // write new content to the environment file
                if (file_put_contents($envFile, $newEnvContent) === false) {
                    $io->error("Failed to write to .env.$env file");
                    return Command::FAILURE;
                }

                // success message
                $io->success("MAINTENANCE_MODE in .env.$env has been set to $newValue");
                return Command::SUCCESS;
            } else {
                $io->error('MAINTENANCE_MODE not found in .env file');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error to toggle maintenance mode: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
