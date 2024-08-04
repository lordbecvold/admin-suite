<?php

namespace App\Tests\Manager;

use ReflectionClass;
use App\Entity\User;
use App\Util\AppUtil;
use App\Util\CacheUtil;
use App\Util\CookieUtil;
use App\Util\SessionUtil;
use App\Util\SecurityUtil;
use App\Manager\LogManager;
use App\Manager\AuthManager;
use App\Manager\UserManager;
use App\Manager\EmailManager;
use App\Util\VisitorInfoUtil;
use App\Manager\ErrorManager;
use PHPUnit\Framework\TestCase;
use App\Repository\UserRepository;
use Symfony\Component\Cache\CacheItem;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Class AuthManagerTest
 *
 * Test the authentication manager
 *
 * @package App\Tests\Manager
 */
class AuthManagerTest extends TestCase
{
    /** @var ErrorManager */
    private ErrorManager $errorManagerMock;

    /** @var AppUtil|MockObject */
    private AppUtil|MockObject $appUtilMock;

    /** @var CacheUtil|MockObject */
    private CacheUtil|MockObject $cacheUtilMock;

    /** @var LogManager|MockObject */
    private LogManager|MockObject $logManagerMock;

    /** @var CookieUtil|MockObject */
    private CookieUtil|MockObject $cookieUtilMock;

    /** @var SessionUtil|MockObject */
    private SessionUtil|MockObject $sessionUtilMock;

    /** @var UserManager|MockObject */
    private UserManager|MockObject $userManagerMock;

    /** @var EmailManager|MockObject */
    private EmailManager|MockObject $emailManagerMock;

    /** @var SecurityUtil|MockObject */
    private SecurityUtil|MockObject $securityUtilMock;

    /** @var VisitorInfoUtil|MockObject */
    private VisitorInfoUtil|MockObject $visitorInfoUtilMock;

    /** @var EntityManagerInterface|MockObject */
    private EntityManagerInterface|MockObject $entityManagerMock;

    /** @var AuthManager */
    private AuthManager $authManager;

    protected function setUp(): void
    {
        $this->appUtilMock = $this->createMock(AppUtil::class);
        $this->cacheUtilMock = $this->createMock(CacheUtil::class);
        $this->logManagerMock = $this->createMock(LogManager::class);
        $this->cookieUtilMock = $this->createMock(CookieUtil::class);
        $this->sessionUtilMock = $this->createMock(SessionUtil::class);
        $this->userManagerMock = $this->createMock(UserManager::class);
        $this->emailManagerMock = $this->createMock(EmailManager::class);
        $this->errorManagerMock = $this->createMock(ErrorManager::class);
        $this->securityUtilMock = $this->createMock(SecurityUtil::class);
        $this->visitorInfoUtilMock = $this->createMock(VisitorInfoUtil::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->authManager = new AuthManager(
            $this->appUtilMock,
            $this->cacheUtilMock,
            $this->logManagerMock,
            $this->cookieUtilMock,
            $this->sessionUtilMock,
            $this->userManagerMock,
            $this->emailManagerMock,
            $this->errorManagerMock,
            $this->securityUtilMock,
            $this->visitorInfoUtilMock,
            $this->entityManagerMock
        );
    }

    /**
     * Test if the user can be logged in
     *
     * @return void
     */
    public function testRegisterUser(): void
    {
        // mock the user manager
        $this->userManagerMock->method('checkIfUserExist')->willReturn(false);
        $this->userManagerMock->method('getUserRepository')->willReturn(null);

        // mock the security util
        $this->securityUtilMock->method('generateHash')->willReturn('hashed_password');

        // mock the visitor info util
        $this->visitorInfoUtilMock->method('getIP')->willReturn('127.0.0.1');
        $this->visitorInfoUtilMock->method('getUserAgent')->willReturn('Test User Agent');

        // mock the entity manager
        $this->entityManagerMock->expects($this->once())->method('persist');
        $this->entityManagerMock->expects($this->once())->method('flush');

        // mock the log manager
        $this->logManagerMock->expects($this->once())->method('log');

        // call register the user
        $this->authManager->registerUser('test_user', 'test_password');
    }

    /**
     * Test is user logged in
     *
     * @return void
     */
    public function testIsUserLogedin(): void
    {
        // mock the user manager
        $this->sessionUtilMock->method('checkSession')->willReturn(true);
        $this->sessionUtilMock->method('getSessionValue')->willReturn('test_token');

        // mock the security util
        $this->userManagerMock->method('getUserRepository')->willReturn(new User());

        // get status
        $result = $this->authManager->isUserLogedin();

        // check if the result is true
        $this->assertTrue($result);
    }

    /**
     * Test if the user can be logged in
     *
     * @return void
     */
    public function testCanLogin(): void
    {
        // mock the user manager
        $user = new User();
        $user->setPassword('hashed_password');

        // mock the security util
        $this->userManagerMock->method('getUserRepository')->willReturn($user);

        // mock the security util
        $this->securityUtilMock->method('verifyPassword')->willReturn(true);

        // get status
        $result = $this->authManager->canLogin('test_user', 'test_password');

        // check if the result is true
        $this->assertTrue($result);
    }

    /**
     * Test user login process
     *
     * @return void
     */
    public function testLogin(): void
    {
        // mock the user
        $user = $this->createMock(User::class);
        $user->method('getToken')->willReturn('test_token');
        $user->method('getId')->willReturn(123); // mock getId to return a valid ID

        // mock the user manager
        $this->userManagerMock->method('getUserRepository')->willReturn($user);

        // mock the session util
        $this->sessionUtilMock->expects($this->exactly(2))->method('setSession');

        // mock the cookie util
        $this->cookieUtilMock->expects($this->once())
            ->method('set')
            ->with('user-token', 'test_token', $this->anything());

        // mock the entity manager
        $this->entityManagerMock->expects($this->once())->method('flush');

        // mock the log manager
        $this->logManagerMock->expects($this->once())->method('log');

        // call login the user
        $this->authManager->login('test_user', true);
    }

    /**
     * Test update user data on login
     *
     * @return void
     */
    public function testUpdateDataOnLogin(): void
    {
        // mock the user manager
        $user = new User();
        $this->userManagerMock->method('getUserRepository')->willReturn($user);

        // mock the visitor info util
        $this->visitorInfoUtilMock->method('getIP')->willReturn('127.0.0.1');
        $this->visitorInfoUtilMock->method('getUserAgent')->willReturn('Test User Agent');

        // mock the entity manager
        $this->entityManagerMock->expects($this->once())->method('flush');

        // mock the log manager
        $this->authManager->updateDataOnLogin('test_token');
    }

    /**
     * Test get logged user id
     *
     * @return void
     */
    public function testGetLoggedUserId(): void
    {
        // mock the user entity
        $user = new User();
        $reflection = new ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, 1);

        // mock the session util
        $this->sessionUtilMock->method('checkSession')->willReturn(true);
        $this->sessionUtilMock->method('getSessionValue')->willReturn('test_token');

        // mock the user manager
        $this->userManagerMock->method('getUserRepository')->willReturn($user);

        // get user id
        $result = $this->authManager->getLoggedUserId();

        // check if the result is true
        $this->assertEquals(1, $result);
    }

    /**
     * Test get logged user token
     *
     * @return void
     */
    public function testGetLoggedUserToken(): void
    {
        // mock the session util
        $this->sessionUtilMock->method('checkSession')->willReturn(true);
        $this->sessionUtilMock->method('getSessionValue')->willReturn('test_token');

        // mock the user manager
        $this->userManagerMock->method('getUserRepository')->willReturn(new User());

        // get user token
        $result = $this->authManager->getLoggedUserToken();

        // check if the result is true
        $this->assertEquals('test_token', $result);
    }

    /**
     * Test user logout process
     *
     * @return void
     */
    public function testLogout(): void
    {
        // mock the session util
        $this->sessionUtilMock->method('checkSession')->willReturn(true);
        $this->sessionUtilMock->method('getSessionValue')->willReturn('test_token');
        $this->sessionUtilMock->expects($this->once())->method('destroySession');

        // mock the user manager
        $this->userManagerMock->method('getUserRepository')->willReturn(new User());

        // mock the visitor info util
        $this->cookieUtilMock->expects($this->once())->method('unset');

        // mock the entity manager
        $this->logManagerMock->expects($this->once())->method('log');

        // call logout the user
        $this->authManager->logout();
    }

    /**
     * Test regenerate users tokens
     *
     * @return void
     */
    public function testRegenerateUsersTokens(): void
    {
        // create a list of users
        $users = [new User(), new User()];

        // mock the repository to return the list of users
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->method('findAll')->willReturn($users);
        $this->entityManagerMock->method('getRepository')->willReturn($userRepoMock);

        // expect the flush method to be called twice (once for each user)
        $this->entityManagerMock->expects($this->exactly(count($users)))->method('flush');

        // call the method
        $result = $this->authManager->regenerateUsersTokens();

        // check if the result status is true
        $this->assertTrue($result['status']);
    }

    /**
     * Test generate user token
     *
     * @return void
     */
    public function testGenerateUserToken(): void
    {
        // mock the repository to return null (token not found)
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->method('findOneBy')->willReturn(null);
        $this->entityManagerMock->method('getRepository')->willReturn($userRepoMock);

        // call the method
        $token = $this->authManager->generateUserToken();

        // check if the token is a non-empty string
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    /**
     * Test cacheOnlineUser method
     *
     * @return void
     */
    public function testCacheOnlineUser(): void
    {
        $userId = 1;

        // Expect the cache manager's setValue method to be called once
        $this->cacheUtilMock->expects($this->once())
            ->method('setValue')
            ->with('online_user_' . $userId, 'online', 300);

        // Call the method
        $this->authManager->cacheOnlineUser($userId);
    }

    /**
     * Test getUserStatus method
     *
     * @return void
     */
    public function testGetUserStatus(): void
    {
        // mock the cache manager to return null status
        $this->cacheUtilMock->method('getValue')
            ->with('online_user_1')
            ->willReturn(new CacheItem());

        // call the method again
        $status = $this->authManager->getUserStatus(1);

        // assert the status is string
        $this->assertIsString($status);
    }

    /**
     * Test getting online users
     *
     * @return void
     */
    public function testGetOnlineUsers(): void
    {
        // get online list
        $result = $this->authManager->getOnlineUsers();

        // assert result
        $this->assertIsArray($result);
    }
}
