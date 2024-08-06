<?php

namespace App\Util;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class AppUtil
 *
 * The utility class for the application
 *
 * @package App\Util
 */
class AppUtil
{
    private JsonUtil $jsonUtil;
    private KernelInterface $kernelInterface;

    public function __construct(
        JsonUtil $jsonUtil,
        KernelInterface $kernelInterface
    ) {
        $this->jsonUtil = $jsonUtil;
        $this->kernelInterface = $kernelInterface;
    }

    /** Get the application root directory
     *
     * @return string The application root directory
     */
    public function getAppRootDir(): string
    {
        return $this->kernelInterface->getProjectDir();
    }

    /**
     * Check if the request is SSL
     *
     * @return bool True if the request is SSL, false otherwise
     */
    public function isSsl(): bool
    {
        // check if HTTPS header is set and its value is either 1 or 'on'
        return isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 1 || strtolower($_SERVER['HTTPS']) === 'on');
    }

    /**
     * Check if the assets exist
     *
     * @return bool True if the assets exist, false otherwise
     */
    public function isAssetsExist(): bool
    {
        return file_exists($this->getAppRootDir() . '/public/build/');
    }

    /**
     * Check if the application is in development mode
     *
     * @return bool True if the application is in development mode, false otherwise
     */
    public function isDevMode(): bool
    {
        $envName = $this->getEnvValue('APP_ENV');

        if ($envName == 'dev' || $envName == 'test') {
            return true;
        }

        return false;
    }

    /**
     * Check if the application is in production mode
     *
     * @return bool True if the application is in production mode, false otherwise
     */
    public function isSSLOnly(): bool
    {
        return $this->getEnvValue('SSL_ONLY') === 'true';
    }

    /**
     * Check if the application is in maintenance mode
     *
     * @return bool True if the application is in maintenance mode, false otherwise
     */
    public function isMaintenance(): bool
    {
        return $this->getEnvValue('MAINTENANCE_MODE') === 'true';
    }

    /**
     * Check if the database logging is enabled
     *
     * @return bool True if the database logging is enabled, false otherwise
     */
    public function isDatabaseLoggingEnabled(): bool
    {
        return $this->getEnvValue('DATABASE_LOGGING') === 'true';
    }

    /**
     * Get the environment variable value
     *
     * @param string $key The environment variable key
     *
     * @return string The environment variable value
     */
    public function getEnvValue(string $key): string
    {
        return $_ENV[$key];
    }

    /**
     * Get the hasher configuration
     *
     * @return array<int> The hasher configuration
     */
    public function getHasherConfig(): array
    {
        return [
            'memory_cost' => (int) $_ENV['MEMORY_COST'],
            'time_cost' => (int) $_ENV['TIME_COST'],
            'threads' => (int) $_ENV['THREADS']
        ];
    }

    /**
     * Load config file
     *
     * @param string $configFile The config file to load
     *
     * @return array<mixed>|null The config file content, null if the file does not exist
     */
    public function loadConfig(string $configFile): ?array
    {
        // default example config path
        $configPath = $this->getAppRootDir() . '/config/suite/' . $configFile;

        // set config path
        if (file_exists($this->getAppRootDir() . '/' . $configFile)) {
            $configPath = $this->getAppRootDir() . '/' . $configFile;
        }

        // load config file
        $config = $this->jsonUtil->getJson($configPath);

        return $config;
    }

    /**
     * Calculate the maximum number of pages
     *
     * @param ?int $totalItems The total number of items
     * @param ?int $itemsPerPage The number of items per page
     *
     * @return int|float The maximum number of pages
     */
    public function calculateMaxPages(?int $totalItems, ?int $itemsPerPage): int|float
    {
        // validate the inputs to make sure they are positive integers
        if ($totalItems <= 0 || $itemsPerPage <= 0) {
            return 0;
        }

        // calculate the maximum number of pages
        $maxPages = ceil($totalItems / $itemsPerPage);

        // return the maximum number of pages
        return $maxPages;
    }
}
