<?php

namespace Tests\Unit\Util;

use App\Util\AppUtil;
use App\Util\ServerUtil;
use App\Manager\ErrorManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ServerUtilTest
 *
 * Test cases for ServerUtil
 *
 * @package Tests\Unit\Util
 */
class ServerUtilTest extends TestCase
{
    /** @var ServerUtil */
    private ServerUtil $serverUtil;

    /** @var ErrorManager */
    private ErrorManager $errorManager;

    /** @var AppUtil|MockObject */
    private AppUtil|MockObject $appUtilMock;

    protected function setUp(): void
    {
        // create mock error manager
        $this->appUtilMock = $this->createMock(AppUtil::class);
        $this->errorManager = $this->createMock(ErrorManager::class);

        // create instance of ServerUtil
        $this->serverUtil = new ServerUtil($this->appUtilMock, $this->errorManager);
        parent::setUp();
    }

    /**
     * Test get host uptime
     *
     * @return void
     */
    public function testGetHostUptime(): void
    {
        // call the method being tested
        $hostUptime = $this->serverUtil->getHostUptime();

        // assert that the result is a non-empty string
        $this->assertIsString($hostUptime);
        $this->assertNotEmpty($hostUptime);
    }

    /**
     * Test get cpu load average
     *
     * @return void
     */
    public function testGetCpuUsage(): void
    {
        // call the method being tested
        $cpuUsage = $this->serverUtil->getCpuUsage();

        // assert that the result is a float between 0 and 100
        $this->assertIsFloat($cpuUsage);
        $this->assertGreaterThanOrEqual(0, $cpuUsage);
        $this->assertLessThanOrEqual(100, $cpuUsage);
    }

    /**
     * Test get ram usage
     *
     * @return void
     */
    public function testGetRamUsage(): void
    {
        // call the method being tested
        $ramUsage = $this->serverUtil->getRamUsage();

        // assert that the result is an array with keys 'used', 'free', and 'total'
        $this->assertIsArray($ramUsage);
        $this->assertArrayHasKey('used', $ramUsage);
        $this->assertArrayHasKey('free', $ramUsage);
        $this->assertArrayHasKey('total', $ramUsage);
    }

    /**
     * Test get ram usage percentage
     *
     * @return void
     */
    public function testGetRamUsagePercentage(): void
    {
        // call the method being tested
        $ramUsagePercentage = $this->serverUtil->getRamUsagePercentage();

        // assert that the result is an integer between 0 and 100
        $this->assertIsInt($ramUsagePercentage);
        $this->assertGreaterThanOrEqual(0, $ramUsagePercentage);
        $this->assertLessThanOrEqual(100, $ramUsagePercentage);
    }

    /**
     * Test get software info
     *
     * @return void
     */
    public function testGetSoftwareInfo(): void
    {
        // call the method being tested
        $softwareInfo = $this->serverUtil->getSystemInfo();

        // assert that the result is an array with keys 'packages' and 'distro'
        $this->assertIsArray($softwareInfo);
    }

    /**
     * Test check is web user sudo
     *
     * @return void
     */
    public function testIsWebUserSudo(): void
    {
        // call the method being tested
        $isSudo = $this->serverUtil->isWebUserSudo();

        // assert that the result is a boolean value
        $this->assertIsBool($isSudo);
    }

    /**
     * Test get drive usage percentage
     *
     * @return void
     */
    public function testGetDriveUsagePercentage(): void
    {
        // call the method being tested
        $driveUsagePercentage = $this->serverUtil->getDriveUsagePercentage();

        // assert that the result is a string
        $this->assertIsString($driveUsagePercentage);
    }

    /**
     * Test get disk usage
     *
     * @return void
     */
    public function testGetDiskUsage(): void
    {
        // call the method being tested
        $diskUsage = $this->serverUtil->getDiskUsage();

        // assert that the result is an integer
        $this->assertIsInt($diskUsage);
    }

    /**
     * Test check is system linux
     *
     * @return void
     */
    public function testIsSystemLinux(): void
    {
        // call the method being tested
        $isLinux = $this->serverUtil->isSystemLinux();

        // assert that the result is a boolean value
        $this->assertIsBool($isLinux);
    }

    /**
     * Test get process list
     *
     * @return void
     */
    public function testGetProcessList(): void
    {
        // call the method being tested
        $processList = $this->serverUtil->getProcessList();

        // assert that the result is an array
        $this->assertIsArray($processList);
    }

    /**
     * Test get diagnostic data
     *
     * @return void
     */
    public function testGetDiagnosticData(): void
    {
        // get diagnostic data
        $diagnosticData = $this->serverUtil->getDiagnosticData();

        // assert that the result is an array
        $this->assertIsArray($diagnosticData);

        // assert that the result contains the expected keys
        $this->assertArrayHasKey('isSSL', $diagnosticData);
        $this->assertArrayHasKey('cpuUsage', $diagnosticData);
        $this->assertArrayHasKey('ramUsage', $diagnosticData);
        $this->assertArrayHasKey('isDevMode', $diagnosticData);
        $this->assertArrayHasKey('driveSpace', $diagnosticData);
        $this->assertArrayHasKey('webUsername', $diagnosticData);
        $this->assertArrayHasKey('isWebUserSudo', $diagnosticData);
        $this->assertArrayHasKey('notInstalledRequirements', $diagnosticData);
    }
}
