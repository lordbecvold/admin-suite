<?php

namespace App\Tests\Manager;

use App\Util\AppUtil;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use App\Manager\ErrorManager;
use PHPUnit\Framework\TestCase;
use App\Manager\ServiceManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class ServiceManagerTest
 *
 * Test for the ServiceManager functionality
 *
 * @package App\Tests\Manager
 */
class ServiceManagerTest extends TestCase
{
    /** @var AppUtil|MockObject */
    private AppUtil|MockObject $appUtilMock;

    /** @var ServiceManager */
    private ServiceManager $serviceManager;

    /** @var LogManager|MockObject */
    private LogManager|MockObject $logManager;

    /** @var AuthManager|MockObject */
    private AuthManager|MockObject $authManager;

    /** @var ErrorManager|MockObject */
    private ErrorManager|MockObject $errorManager;

    protected function setUp(): void
    {
        $this->appUtilMock = $this->createMock(AppUtil::class);
        $this->logManager = $this->createMock(LogManager::class);
        $this->authManager = $this->createMock(AuthManager::class);
        $this->errorManager = $this->createMock(ErrorManager::class);

        $this->serviceManager = new ServiceManager(
            $this->appUtilMock,
            $this->logManager,
            $this->authManager,
            $this->errorManager
        );
    }

    /**
     * Test check is service running
     *
     * @return void
     */
    public function testIsServiceRunning(): void
    {
        $this->assertIsBool($this->serviceManager->isServiceRunning('example_service'));
    }

    /**
     * Test check is sockt open
     *
     * @return void
     */
    public function testIsSocktOpen(): void
    {
        // socket is closed
        $this->assertEquals('Offline', $this->serviceManager->isSocktOpen('127.0.0.1', 81));
    }

    /**
     * Test check is process running
     *
     * @return void
     */
    public function testIsProcessRunning(): void
    {
        exec('pgrep test_process', $output);
        $this->assertEquals([], $output);
        $this->assertFalse($this->serviceManager->isProcessRunning('test_process'));
    }

    /**
     * Test check is ufw firewall running
     *
     * @return void
     */
    public function testIsUfwRunning(): void
    {
        $this->assertIsBool($this->serviceManager->isUfwRunning());
    }

    /**
     * Test check is services list exist
     *
     * @return void
     */
    public function testIsServicesListExist(): void
    {
        $this->assertIsBool($this->serviceManager->isServicesListExist());
    }
}
