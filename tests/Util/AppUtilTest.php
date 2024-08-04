<?php

namespace App\Tests\Util;

use App\Util\AppUtil;
use App\Util\JsonUtil;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class AppUtilTest
 *
 * Test the app util
 *
 * @package App\Tests\Util
 */
class AppUtilTest extends TestCase
{
    /** @var AppUtil */
    private AppUtil $appUtil;

    /** @var KernelInterface */
    private KernelInterface $kernelInterface;

    /** @var JsonUtil|MockObject */
    private JsonUtil|MockObject $jsonUtilMock;

    protected function setUp(): void
    {
        $this->jsonUtilMock = $this->createMock(JsonUtil::class);
        $this->kernelInterface = $this->createMock(KernelInterface::class);

        $this->appUtil = new AppUtil($this->jsonUtilMock, $this->kernelInterface);
    }

    /**
     * Test check is SSL
     *
     * @return void
     */
    public function testIsSsl(): void
    {
        $_SERVER['HTTPS'] = 1;
        $this->assertTrue($this->appUtil->isSsl());

        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue($this->appUtil->isSsl());

        $_SERVER['HTTPS'] = 0;
        $this->assertFalse($this->appUtil->isSsl());

        unset($_SERVER['HTTPS']);
        $this->assertFalse($this->appUtil->isSsl());
    }

    /**
     * Test assets exist
     *
     * @return void
     */
    public function testIsAssetsExist(): void
    {
        $this->assertIsBool($this->appUtil->isAssetsExist());
    }

    /**
     * Test dev mode check
     *
     * @return void
     */
    public function testIsDevMode(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $this->assertTrue($this->appUtil->isDevMode());

        $_ENV['APP_ENV'] = 'test';
        $this->assertTrue($this->appUtil->isDevMode());

        $_ENV['APP_ENV'] = 'prod';
        $this->assertFalse($this->appUtil->isDevMode());
    }

    /**
     * Test SSl only check
     *
     * @return void
     */
    public function testIsSslOnly(): void
    {
        $_ENV['SSL_ONLY'] = 'true';
        $this->assertTrue($this->appUtil->isSSLOnly());

        $_ENV['SSL_ONLY'] = 'false';
        $this->assertFalse($this->appUtil->isSSLOnly());
    }

    /**
     * Test maintenance check
     *
     * @return void
     */
    public function testIsMaintenance(): void
    {
        $_ENV['MAINTENANCE_MODE'] = 'true';
        $this->assertTrue($this->appUtil->isMaintenance());

        $_ENV['MAINTENANCE_MODE'] = 'false';
        $this->assertFalse($this->appUtil->isMaintenance());
    }

    /**
     * Test loging enabled check
     *
     * @return void
     */
    public function testIsDatabaseLoggingEnabled(): void
    {
        $_ENV['DATABASE_LOGGING'] = 'true';
        $this->assertTrue($this->appUtil->isDatabaseLoggingEnabled());

        $_ENV['DATABASE_LOGGING'] = 'false';
        $this->assertFalse($this->appUtil->isDatabaseLoggingEnabled());
    }

    /**
     * Test get log level
     *
     * @return void
     */
    public function testGetLogLevel(): void
    {
        $_ENV['LOG_LEVEL'] = '1';
        $this->assertSame(1, $this->appUtil->getLogLevel());

        $_ENV['LOG_LEVEL'] = '2';
        $this->assertSame(2, $this->appUtil->getLogLevel());
    }

    /**
     * Test get hasher config
     *
     * @return void
     */
    public function testPageLimiter(): void
    {
        // get page limitter
        $limit = $this->appUtil->getPageLimiter();

        // check if limit is valid
        $this->assertSame($limit, (int) $_ENV['LIMIT_CONTENT_PER_PAGE']);
    }

    /**
     * Test calculate max pages
     *
     * @return void
     */
    public function testCalculateMaxPages(): void
    {
        // calculate max pages
        $maxPages = (int) $this->appUtil->calculateMaxPages(100, 10);

        // check if max pages is valid
        $this->assertSame($maxPages, 10);
    }

    /**
     * Test get admin contact email
     *
     * @return void
     */
    public function testGetAdminContactEmail(): void
    {
        $_ENV['ADMIN_CONTACT'] = 'admin@example.com';
        $this->assertSame('admin@example.com', $this->appUtil->getAdminContactEmail());

        $_ENV['ADMIN_CONTACT'] = 'support@example.com';
        $this->assertSame('support@example.com', $this->appUtil->getAdminContactEmail());
    }

    /**
     * Test get system logs directory
     *
     * @return void
     */
    public function getGetSystemLogsDirectory(): void
    {
        $this->assertSame('/var/log', $this->appUtil->getSystemLogsDirectory());
    }

    /**
     * Test get anti log token
     *
     * @return void
     */
    public function testGetAntiLogToken(): void
    {
        $_ENV['ANTI_LOG_TOKEN'] = 'secret-token';
        $this->assertSame('secret-token', $this->appUtil->getAntiLogToken());

        $_ENV['ANTI_LOG_TOKEN'] = 'another-token';
        $this->assertSame('another-token', $this->appUtil->getAntiLogToken());
    }

    /**
     * Test get hasher config
     *
     * @return void
     */
    public function testGetHasherConfig(): void
    {
        $_ENV['MEMORY_COST'] = '1024';
        $_ENV['TIME_COST'] = '2';
        $_ENV['THREADS'] = '1';

        // expected config
        $expectedConfig = [
            'memory_cost' => 1024,
            'time_cost' => 2,
            'threads' => 1,
        ];

        // assert that the config is correct
        $this->assertSame($expectedConfig, $this->appUtil->getHasherConfig());

        $_ENV['MEMORY_COST'] = '2048';
        $_ENV['TIME_COST'] = '4';
        $_ENV['THREADS'] = '2';

        // expected config
        $expectedConfig = [
            'memory_cost' => 2048,
            'time_cost' => 4,
            'threads' => 2,
        ];

        // assert that the config is correct
        $this->assertSame($expectedConfig, $this->appUtil->getHasherConfig());
    }

    /**
     * Test get monitoring interval
     *
     * @return void
     */
    public function testGetMonitoringInterval(): void
    {
        $this->assertSame(5, $this->appUtil->getMonitoringInterval());
    }
}
