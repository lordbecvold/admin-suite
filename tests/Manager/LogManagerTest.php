<?php

namespace App\Tests\Manager;

use App\Entity\Log;
use App\Util\AppUtil;
use App\Util\CookieUtil;
use App\Util\SessionUtil;
use App\Manager\LogManager;
use App\Util\VisitorInfoUtil;
use App\Manager\ErrorManager;
use PHPUnit\Framework\TestCase;
use App\Repository\LogRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LogManagerTest
 *
 * Test the log manager
 *
 * @package App\Tests\Manager
 */
class LogManagerTest extends TestCase
{
    /** @var LogManager */
    private LogManager $logManager;

    /** @var AppUtil|MockObject */
    private AppUtil|MockObject $appUtilMock;

    /** @var CookieUtil|MockObject */
    private CookieUtil|MockObject $cookieUtilMock;

    /** @var SessionUtil|MockObject */
    private SessionUtil|MockObject $sessionUtilMock;

    /** @var LogRepository|MockObject */
    private LogRepository|MockObject $repositoryMock;

    /** @var ErrorManager|MockObject */
    private ErrorManager|MockObject $errorManagerMock;

    /** @var VisitorInfoUtil|MockObject */
    private VisitorInfoUtil|MockObject $visitorInfoUtilMock;

    /** @var EntityManagerInterface|MockObject */
    private EntityManagerInterface|MockObject $entityManagerMock;

    protected function setUp(): void
    {
        $this->appUtilMock = $this->createMock(AppUtil::class);
        $this->cookieUtilMock = $this->createMock(CookieUtil::class);
        $this->sessionUtilMock = $this->createMock(SessionUtil::class);
        $this->repositoryMock = $this->createMock(LogRepository::class);
        $this->errorManagerMock = $this->createMock(ErrorManager::class);
        $this->visitorInfoUtilMock = $this->createMock(VisitorInfoUtil::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->logManager = new LogManager(
            $this->appUtilMock,
            $this->cookieUtilMock,
            $this->sessionUtilMock,
            $this->errorManagerMock,
            $this->visitorInfoUtilMock,
            $this->entityManagerMock
        );
    }

    /**
     * Test set anti-log token
     *
     * @return void
     */
    public function testSetAntiLog(): void
    {
        $this->appUtilMock->method('getEnvValue')->willReturn('test-token');

        // expect set method to be called
        $this->cookieUtilMock->expects($this->once())->method('set')
            ->with('anti-log', 'test-token', $this->greaterThan(time()));

        // call the setAntiLog method
        $this->logManager->setAntiLog();
    }

    /**
     * Test unset anti-log token
     *
     * @return void
     */
    public function testUnSetAntiLog(): void
    {
        // expect unset method to be called
        $this->cookieUtilMock->expects($this->once())->method('unset')
            ->with('anti-log');

        // call the unSetAntiLog method
        $this->logManager->unSetAntiLog();
    }

    /**
     * Test is anti-log enabled
     *
     * @return void
     */
    public function testIsAntiLogEnabled(): void
    {
        $this->cookieUtilMock->method('isCookieSet')->willReturn(true);
        $this->cookieUtilMock->method('get')->willReturn('test-token');
        $this->appUtilMock->method('getEnvValue')->willReturn('test-token');

        // call the isAntiLogEnabled method and assert true
        $this->assertTrue($this->logManager->isAntiLogEnabled());
    }

    /**
     * Test is anti-log enabled when cookie is not set
     *
     * @return void
     */
    public function testIsAntiLogEnabledWhenCookieNotSet(): void
    {
        $this->cookieUtilMock->method('isCookieSet')->willReturn(false);

        // call the isAntiLogEnabled method and assert false
        $this->assertFalse($this->logManager->isAntiLogEnabled());
    }

    /**
     * Test isAntiLogEnabled when token is invalid
     *
     * @return void
     */
    public function testIsAntiLogEnabledWhenTokenInvalid(): void
    {
        $this->cookieUtilMock->method('isCookieSet')->willReturn(true);
        $this->cookieUtilMock->method('get')->willReturn('invalid-token');
        $this->appUtilMock->method('getEnvValue')->willReturn('test-token');

        // call the isAntiLogEnabled method and assert false
        $this->assertFalse($this->logManager->isAntiLogEnabled());
    }

    /**
     * Test get logs count
     *
     * @return void
     */
    public function testGetLogsCount(): void
    {
        $count = $this->logManager->getLogsCountWhereStatus();

        $this->assertIsInt($count);
    }

    /**
     * Test get auth logs count
     *
     * @return void
     */
    public function testGetAuthLogsCount(): void
    {
        $count = $this->logManager->getAuthLogsCount();

        $this->assertIsInt($count);
    }

    /**
     * Test get logs
     *
     * @return void
     */
    public function testGetLogs(): void
    {
        $log1 = new Log();

        $this->repositoryMock->method('findBy')
            ->with(['status' => 'unread'])
            ->willReturn([$log1]);

        // call get logs method
        $logs = $this->logManager->getLogsWhereStatus();

        // assert method response
        $this->assertIsArray($logs);
    }

    /**
     * Test set all logs to readed
     *
     * @return void
     */
    public function testSetAllLogsToReaded(): void
    {
        // mock repository to return logs with 'UNREADED' status
        $mockLog1 = new Log();
        $mockLog1->setStatus('UNREADED');

        $mockLog2 = new Log();
        $mockLog2->setStatus('UNREADED');

        $logs = [$mockLog1, $mockLog2];

        $this->entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->repositoryMock);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'UNREADED'])
            ->willReturn($logs);

        // expect flush to be called once
        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        // call the method under test
        $this->logManager->setAllLogsToReaded();
    }

    /**
     * Test updateLogStatusById when log is found and status is updated
     *
     * @return void
     */
    public function testUpdateLogStatusByIdSuccess(): void
    {
        $logId = 1;
        $newStatus = 'READED';

        $logMock = $this->createMock(Log::class);
        $logMock->expects($this->once())
            ->method('setStatus')
            ->with($newStatus);

        $repositoryMock = $this->createMock(LogRepository::class);
        $repositoryMock->expects($this->once())
            ->method('find')
            ->with($logId)
            ->willReturn($logMock);

        $this->entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(Log::class)
            ->willReturn($repositoryMock);

        $this->entityManagerMock->expects($this->once())
            ->method('flush');

        // call method
        $this->logManager->updateLogStatusById($logId, $newStatus);
    }

    /**
     * Test updateLogStatusById when an exception is thrown during flush
     *
     * @return void
     */
    public function testUpdateLogStatusByIdFlushException(): void
    {
        $logId = 1;
        $newStatus = 'READED';

        $logMock = $this->createMock(Log::class);
        $logMock->expects($this->once())
            ->method('setStatus')
            ->with($newStatus);

        $repositoryMock = $this->createMock(LogRepository::class);
        $repositoryMock->expects($this->once())
            ->method('find')
            ->with($logId)
            ->willReturn($logMock);

        $this->entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(Log::class)
            ->willReturn($repositoryMock);

        $this->entityManagerMock->expects($this->once())
            ->method('flush')
            ->willThrowException(new \Exception('Test Exception'));

        $this->errorManagerMock->expects($this->once())->method('handleError')
            ->with(
                'error to update log status: Test Exception',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

        // call method
        $this->logManager->updateLogStatusById($logId, $newStatus);
    }
}
