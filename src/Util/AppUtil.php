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
     * Get admin contact email address
     *
     * @return string The admin contact email address
     */
    public function getAdminContactEmail(): string
    {
        return $_ENV['ADMIN_CONTACT'];
    }

    /**
     * Get system log directory path
     *
     * @return string The system log directory
     */
    public function getSystemLogsDirectory(): string
    {
        return $_ENV['SYSTEM_LOGS_DIR'];
    }

    /**
     * Check if the application is in development mode
     *
     * @return bool True if the application is in development mode, false otherwise
     */
    public function isDevMode(): bool
    {
        if ($_ENV['APP_ENV'] == 'dev' || $_ENV['APP_ENV'] == 'test') {
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
        return $_ENV['SSL_ONLY'] === 'true';
    }

    /**
     * Check if the application is in maintenance mode
     *
     * @return bool True if the application is in maintenance mode, false otherwise
     */
    public function isMaintenance(): bool
    {
        return $_ENV['MAINTENANCE_MODE'] === 'true';
    }

    /**
     * Check if the database logging is enabled
     *
     * @return bool True if the database logging is enabled, false otherwise
     */
    public function isDatabaseLoggingEnabled(): bool
    {
        return $_ENV['DATABASE_LOGGING'] === 'true';
    }

    /**
     * Get the log level
     *
     * @return int The log level
     */
    public function getLogLevel(): int
    {
        return (int) $_ENV['LOG_LEVEL'];
    }

    /**
     * Get the page limitter
     *
     * @return int The page limitter
     */
    public function getPageLimiter(): int
    {
        return (int) $_ENV['LIMIT_CONTENT_PER_PAGE'];
    }

    /** Get monitoring wait interval
     *
     * @return int The monitoring wait interval
     */
    public function getMonitoringInterval(): int
    {
        return (int) $_ENV['MONITORING_INTERVAL'];
    }

    /**
     * Get the anti-log token
     *
     * @return string The anti-log token
     */
    public function getAntiLogToken(): string
    {
        return $_ENV['ANTI_LOG_TOKEN'];
    }

    /** Get the main database name
     *
     * @return string The main database name
     */
    public function getMainDatabaseName(): string
    {
        return $_ENV['DATABASE_NAME'];
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
