<?php

namespace App\Manager;

use App\Entity\User;
use App\Util\AppUtil;
use App\Util\CacheUtil;
use App\Util\CookieUtil;
use App\Util\SessionUtil;
use App\Util\SecurityUtil;
use App\Util\VisitorInfoUtil;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\String\ByteString;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AuthManager
 *
 * User authentication and authorization logic
 *
 * @package App\Manager
 */
class AuthManager
{
    private AppUtil $appUtil;
    private CacheUtil $cacheUtil;
    private LogManager $logManager;
    private CookieUtil $cookieUtil;
    private SessionUtil $sessionUtil;
    private UserManager $userManager;
    private EmailManager $emailManager;
    private ErrorManager $errorManager;
    private SecurityUtil $securityUtil;
    private VisitorInfoUtil $visitorInfoUtil;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AppUtil $appUtil,
        CacheUtil $cacheUtil,
        LogManager $logManager,
        CookieUtil $cookieUtil,
        SessionUtil $sessionUtil,
        UserManager $userManager,
        EmailManager $emailManager,
        ErrorManager $errorManager,
        SecurityUtil $securityUtil,
        VisitorInfoUtil $visitorInfoUtil,
        EntityManagerInterface $entityManager
    ) {
        $this->appUtil = $appUtil;
        $this->cacheUtil = $cacheUtil;
        $this->logManager = $logManager;
        $this->cookieUtil = $cookieUtil;
        $this->sessionUtil = $sessionUtil;
        $this->userManager = $userManager;
        $this->emailManager = $emailManager;
        $this->errorManager = $errorManager;
        $this->securityUtil = $securityUtil;
        $this->entityManager = $entityManager;
        $this->visitorInfoUtil = $visitorInfoUtil;
    }

    /**
     * Check if a username is blocked
     *
     * @param string $username The username to check
     *
     * @return bool True if the username is blocked, false otherwise
     */
    public function isUsernameBlocked(string $username): bool
    {
        // get blocked usernames config file
        $blockedUsernames = $this->appUtil->loadConfig('blocked-usernames.json');

        // check if blocked usernames config file found
        if ($blockedUsernames == null) {
            return false;
        }

        // check if username is blocked
        $result = in_array($username, $blockedUsernames);

        return $result;
    }

    /**
     * Register a new user to database
     *
     * @param string $username The username of the new user
     * @param string $password The password of the new user
     *
     * @throws \Exception If there is an error while registering the new user
     *
     * @return void
     */
    public function registerUser(string $username, string $password): void
    {
        // check if username is system
        if ($this->isUsernameBlocked($username)) {
            $this->errorManager->handleError(
                'error to register new user: username is system',
                Response::HTTP_FORBIDDEN
            );
        }

        // check if user already exist
        if ($this->userManager->checkIfUserExist($username)) {
            return;
        }

        // generate entity token
        $token = $this->generateUserToken();

        // hash password
        $password = $this->securityUtil->generateHash($password);

        // get current time
        $time = new \DateTime();

        // get ip address
        $ip_address = $this->visitorInfoUtil->getIP();

        // get user agent
        $user_agent = $this->visitorInfoUtil->getUserAgent();

        // check if ip address is null
        if ($ip_address == null) {
            $ip_address = 'Unknown';
        }

        // check if ip address is null
        if ($user_agent == null) {
            $user_agent = 'Unknown';
        }

        // check if user exist
        if ($this->userManager->getUserRepository(['username' => $username]) == null) {
            try {
                // init user entity
                $user = new User();

                $user->setUsername($username)
                    ->setPassword($password)
                    ->setRole('USER')
                    ->setIpAddress($ip_address)
                    ->setUserAgent($user_agent)
                    ->setToken($token)
                    ->setProfilePic('default_pic')
                    ->setRegisterTime($time)
                    ->setLastLoginTime($time);

                // flush user to database
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // log action
                $this->logManager->log(
                    name: 'authenticator',
                    message: 'new registration user: ' . $username,
                    level: LogManager::LEVEL_CRITICAL
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to register new user: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }

    /**
     * Get current user logged user repository
     *
     * @return User|null The user object if found, null otherwise
     */
    public function getLoggedUserRepository(): ?User
    {
        // check if user logged in
        if (!$this->isUserLogedin()) {
            return null;
        }

        // get logged user repository
        $repository = $this->userManager->getUserRepository(
            ['token' => $this->sessionUtil->getSessionValue('user-token')]
        );

        return $repository;
    }

    /**
     * Check if current logged user is admin
     *
     * @return bool The is user admin or not
     */
    public function isLoggedInUserAdmin(): bool
    {
        // check if user logged in
        if (!$this->isUserLogedin()) {
            return false;
        }

        // get logged user
        $user = $this->getLoggedUserRepository();

        // check if user exist
        if ($user == null) {
            return false;
        }

        // check if user is admin
        if ($this->userManager->isUserAdmin((int) $user->getId())) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is logged in
     *
     * @return bool The user is logged in or not
     */
    public function isUserLogedin(): bool
    {
        // check if session exist
        if (!$this->sessionUtil->checkSession('user-token')) {
            return false;
        }

        // get login token form session
        $loginToken = $this->sessionUtil->getSessionValue('user-token');

        // check if token exist in database
        if ($this->userManager->getUserRepository(['token' => $loginToken]) != null) {
            return true;
        }

        return false;
    }

    /**
     * Check if a user can login
     *
     * @param string $username The username of the user
     * @param string $password The password of the user
     *
     * @return bool True if the user can login, otherwise false
     */
    public function canLogin(string $username, string $password): bool
    {
        // get user repo
        $repo = $this->userManager->getUserRepository(['username' => $username]);

        // check if user exist
        if ($repo != null) {
            // check if password is correct
            if ($this->securityUtil->verifyPassword($password, (string) $repo->getPassword())) {
                return true;
            }
        } else {
            // log invalid credentials
            $this->logManager->log(
                name: 'authenticator',
                message: 'invalid login user: ' . $username . ':' . $password,
                level: LogManager::LEVEL_CRITICAL
            );
        }

        return false;
    }

    /**
     * Login a user to the system
     *
     * @param string $username The username of the user
     * @param bool $remember Whether to remember the user
     *
     * @return void
     */
    public function login(string $username, bool $remember): void
    {
        // get user repository
        $repo = $this->userManager->getUserRepository(['username' => $username]);

        // check if user exist
        if ($repo != null) {
            // get user token
            $token = (string) $repo->getToken();

            try {
                // set user token session
                $this->sessionUtil->setSession('user-token', $token);

                // save user identifier in session
                $this->sessionUtil->setSession('user-identifier', (string) $repo->getId());

                // set user token cookie
                if ($remember) {
                    $this->cookieUtil->set('user-token', $token, time() + (60 * 60 * 24 * 7 * 365));
                }

                // update user data
                $this->updateDataOnLogin($token);

                // send email alert
                if (!$this->logManager->isAntiLogEnabled()) {
                    $this->emailManager->sendDefaultEmail(
                        recipient: $this->appUtil->getEnvValue('ADMIN_CONTACT'),
                        subject: 'LOGIN ALERT',
                        message: 'User ' . $username . ' has logged to admin-suite dashboard, login log has been saved in database.'
                    );
                }

                // log action
                $this->logManager->log(
                    name: 'authenticator',
                    message: 'login user: ' . $username,
                    level: LogManager::LEVEL_CRITICAL
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to login user: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }

    /**
     * Update user data on login
     *
     * @param string $token The token of the user
     *
     * @throws \Exception If there is an error while updating the user data
     *
     * @return void
     */
    public function updateDataOnLogin(string $token): void
    {
        // get user repository
        $repo = $this->userManager->getUserRepository(['token' => $token]);

        // check if repo found
        if ($repo == null) {
            $this->errorManager->handleError(
                message: 'error to update user data: user not found',
                code: Response::HTTP_NOT_FOUND
            );
            return;
        }

        // update user data
        $repo->setLastLoginTime(new \DateTime())
            ->setIpAddress($this->visitorInfoUtil->getIP() ?? 'Unknown')
            ->setUserAgent($this->visitorInfoUtil->getUserAgent() ?? 'Unknown');

        // flush updated user data
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to update user data: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get the id of the logged user
     *
     * @return int The id of the logged in user
     */
    public function getLoggedUserId(): int
    {
        $userId = 0;

        // check if user logged in
        if ($this->isUserLogedin()) {
            // get user token
            $token = $this->getLoggedUserToken();

            // get user repository
            $user = $this->userManager->getUserRepository(['token' => $token]);

            // check if user exist
            if ($user != null) {
                // get user id
                $userId = (int) $user->getId();
            }
        }

        return $userId;
    }

    /**
     * Get the login token for the current user session
     *
     * @return mixed The login token or null if not found or invalid.
     */
    public function getLoggedUserToken(): mixed
    {
        // check if session exist
        if (!$this->sessionUtil->checkSession('user-token')) {
            return null;
        }

        // get login token form session
        $loginToken = $this->sessionUtil->getSessionValue('user-token');

        // check if token exist in database
        if ($this->userManager->getUserRepository(['token' => $loginToken]) != null) {
            return $loginToken;
        }

        return null;
    }

    /**
     * Get the username of the logged user
     *
     * @return string|null The username of the logged user or null if not found or invalid.
     */
    public function getLoggedUsername(): ?string
    {
        $userRepo = $this->userManager->getUserRepository([
            'token' => $this->getLoggedUserToken()
        ]);

        // check if user exist
        if ($userRepo == null) {
            $this->errorManager->handleError(
                message: 'error to get logged user username',
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return null;
        }

        return $userRepo->getUsername();
    }

    /**
     * User logout action
     *
     * @return void
     */
    public function logout(): void
    {
        // check if user logged in
        if ($this->isUserLogedin()) {
            // init user
            $user = $this->userManager->getUserRepository([
                'token' => $this->getLoggedUserToken()
            ]);

            // check if repo found
            if ($user == null) {
                $this->errorManager->handleError(
                    message: 'error to update user data: user not found',
                    code: Response::HTTP_NOT_FOUND
                );
                return;
            }

            // log logout event
            $this->logManager->log(
                name: 'authenticator',
                message: 'logout user: ' . $user->getUsername(),
                level: LogManager::LEVEL_CRITICAL
            );

            // unset login cookie
            $this->cookieUtil->unset('user-token');

            // unset login session
            $this->sessionUtil->destroySession();
        }
    }

    /**
     * Reset user password
     *
     * @param string $username The username of the user
     *
     * @return string|null The new password or null on error
     */
    public function resetUserPassword(string $username): ?string
    {
        /** @var \App\Entity\User $user */
        $user = $this->userManager->getUserRepository([
            'username' => $username
        ]);

        // check if user exist
        if ($user != null) {
            try {
                // generate new password
                $newPassword = ByteString::fromRandom(32)->toString();

                // hash new password
                $newPasswordHash = $this->securityUtil->generateHash($newPassword);

                // genetate new token
                $newToken = $this->generateUserToken();

                // set new password
                $user->setPassword($newPasswordHash);
                $user->setToken($newToken);

                // flush update to database
                $this->entityManager->flush();

                // log password reset
                $this->logManager->log(
                    name: 'authenticator',
                    message: 'user: ' . $username . ' password reset is success',
                    level: LogManager::LEVEL_CRITICAL
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to reset user password: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
                return null;
            }

            // return new password
            return $newPassword;
        }

        // return non existing user
        return null;
    }

    /**
     * Regenerate tokens for all users in the database
     *
     * This method regenerates tokens for all users in the database, ensuring uniqueness for each token
     *
     * @return array<bool|null|string> An array containing the status of the operation and any relevant message
     * - The 'status' key indicates whether the operation was successful (true) or not (false)
     * - The 'message' key contains any relevant error message if the operation failed, otherwise it is null
     */
    public function regenerateUsersTokens(): array
    {
        $state = [
            'status' => true,
            'message' => null
        ];

        // get all users in database
        $userRepo = $this->entityManager->getRepository(User::class)->findAll();

        // regenerate all users tokens
        foreach ($userRepo as $user) {
            // regenerate new token
            $newToken = $this->generateUserToken();

            // set new token
            $user->setToken($newToken);

            // flush data
            try {
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $state = [
                    'status' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        // log action
        $this->logManager->log(
            name: 'authenticator',
            message: 'regenerate all users tokens',
            level: LogManager::LEVEL_WARNING
        );

        // return process state output
        return $state;
    }

    /**
     * Generate a unique token for a user
     *
     * @return string The generated user token
     */
    public function generateUserToken(): string
    {
        // generate user token
        $token = ByteString::fromRandom(32)->toString();

        // get users repository
        $userRepo = $this->entityManager->getRepository(User::class);

        // check if user token is not already used
        if ($userRepo->findOneBy(['token' => $token]) != null) {
            $this->generateUserToken();
        }

        return $token;
    }

    /**
     * Store online user id in cache
     *
     * @param int $userId The id of the user to store
     *
     * @return void
     */
    public function cacheOnlineUser(int $userId): void
    {
        // cache online visitor
        $this->cacheUtil->setValue('online_user_' . $userId, 'online', 300);
    }

    /**
     * Get online users list
     *
     * @return array<mixed> The list of online users
     */
    public function getOnlineUsersList(): array
    {
        $onlineVisitors = [];

        // get all users list
        $users = $this->userManager->getAllUsersRepository();

        // Check if $users is iterable
        if (!is_iterable($users)) {
            // handle the case where $users is not iterable.
            return $onlineVisitors;
        }

        // check all users status
        foreach ($users as $user) {
            // check if $user is an object with getId() method
            if (is_object($user) && method_exists($user, 'getId')) {
                // get visitor status
                $status = $this->getUserStatus($user->getId());

                // check visitor status
                if ($status == 'online') {
                    array_push($onlineVisitors, $user);
                }
            }
        }

        return $onlineVisitors;
    }

    /**
     * Get user status
     *
     * @param int $userId The id of the user
     *
     * @return string The status of the user
     */
    public function getUserStatus(int $userId): string
    {
        $userCacheKey = 'online_user_' . $userId;

        // get the cache item
        $cacheItem = $this->cacheUtil->getValue($userCacheKey);

        // check if cache item exists and is not expired
        if ($cacheItem instanceof CacheItemInterface) {
            // retrieve the value from the cache item
            $status = $cacheItem->get();

            // check if status found
            if (is_string($status) && $status !== null && $status !== '') {
                return $status;
            }
        }

        return 'offline';
    }

    /**
     * Get online users list
     *
     * @return array<mixed> The list of online users
     */
    public function getOnlineUsers(): array
    {
        $onlineVisitors = [];

        // get all users list
        $users = $this->userManager->getAllUsersRepository();

        // check if $users is iterable
        if (!is_iterable($users)) {
            return $onlineVisitors;
        }

        // check all users status
        foreach ($users as $user) {
            // check if $user is an object with getId() method
            if (is_object($user) && method_exists($user, 'getId')) {
                // get visitor status
                $status = $this->getUserStatus($user->getId());

                // check visitor status
                if ($status == 'online') {
                    array_push($onlineVisitors, $user);
                }
            }
        }

        return $onlineVisitors;
    }
}
