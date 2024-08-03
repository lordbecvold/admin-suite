<?php

namespace App\Tests;

use App\Entity\User;
use App\Manager\AuthManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class CustomTestCase
 *
 * Custom test case class
 *
 * @package App\Tests
 */
class CustomTestCase extends WebTestCase
{
    /**
     * Simulate a user login
     *
     * @param KernelBrowser $client The KernelBrowser instance
     *
     * @return void
     */
    public function simulateLogin(KernelBrowser $client): void
    {
        // create a mock user
        $mockUser = new User();
        $mockUser->setUsername('test');
        $mockUser->setPassword('$argon2id$v=19$m=16384,t=6,p=4$Q0ZSLlBtVmZMR0JxdThGUg$MRBG4L4FyD853oBxOYs3+W3S9MNecP9kACc0zZuZR5k');
        $mockUser->setRole('OWNER');
        $mockUser->setIpAddress('172.19.0.1');
        $mockUser->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
        $mockUser->setRegisterTime(new \DateTime());
        $mockUser->setLastLoginTime(new \DateTime());
        $mockUser->setToken('fba6eb31278954ce68feb303cbd34bfe');
        $mockUser->setProfilePic('default_pic');

        // create a mock of AuthManager
        $authManager = $this->createMock(AuthManager::class);

        // configure the mock to return true for isUserLogedin
        $authManager->method('isUserLogedin')->willReturn(true);

        // configure the mock to return true for isUserLogedin
        $authManager->method('isLoggedInUserAdmin')->willReturn(true);

        // configure the mock to return the mock user for getLoggedUserRepository
        $authManager->method('getLoggedUserRepository')->willReturn($mockUser);

        // replace the actual AuthManager service with the mock
        $client->getContainer()->set('App\Manager\AuthManager', $authManager);
    }

    /**
     * The get random user id from database
     *
     * @param EntityManagerInterface $entityManager The entity manager
     *
     * @return int The user id
     */
    protected function getRandomUserId(EntityManagerInterface $entityManager): int
    {
        $userRepository = $entityManager->getRepository(User::class);

        /** @var array<int, array{id: int}> $userIds */
        $userIds = $userRepository->createQueryBuilder('u')
            ->select('u.id')
            ->getQuery()
            ->getArrayResult();

        // check if no users found in the database
        if (count($userIds) === 0) {
            throw new \Exception('No users found in the database.');
        }

        // return a random user id from the array of user ids
        return $userIds[array_rand($userIds)]['id'];
    }
}
