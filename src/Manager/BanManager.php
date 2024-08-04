<?php

namespace App\Manager;

use App\Entity\Banned;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BanManager
 *
 * The BanManager is responsible for managing user ban system
 *
 * @package App\Manager
 */
class BanManager
{
    private LogManager $logManager;
    private UserManager $userManager;
    private AuthManager $authManager;
    private ErrorManager $errorManager;
    private EntityManagerInterface $entityManager;

    public function __construct(
        LogManager $logManager,
        UserManager $userManager,
        AuthManager $authManager,
        ErrorManager $errorManager,
        EntityManagerInterface $entityManager
    ) {
        $this->logManager = $logManager;
        $this->userManager = $userManager;
        $this->authManager = $authManager;
        $this->errorManager = $errorManager;
        $this->entityManager = $entityManager;
    }

    /**
     * Ban a user
     *
     * @param int $userId The id of the user to ban
     * @param string $reason The reason for banning the user
     *
     * @return void
     */
    public function banUser(int $userId, string $reason = 'no-reason'): void
    {
        // check if user is already banned
        if ($this->isUserBanned($userId)) {
            return;
        }

        // set banned data
        $banned = new Banned();
        $banned->setReason($reason)
            ->setStatus('active')
            ->setTime(new \DateTime())
            ->setBannedById($this->authManager->getLoggedUserId())
            ->setBannedUserId($userId);

        // ban user
        try {
            $this->entityManager->persist($banned);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to ban user: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // log action
        $this->logManager->log(
            name: 'ban-manager',
            message: 'user: ' . $userId . ' has been banned',
            level: LogManager::LEVEL_WARNING
        );
    }

    /**
     * Get banned user repository
     *
     * @param array<mixed> $search An array of search parameters
     *
     * @return Banned|null The banned user repository, or null if ban does not exist
     */
    public function getBanRepository(array $search): ?Banned
    {
        return $this->entityManager->getRepository(Banned::class)->findOneBy($search);
    }

    /**
     * Check if user is banned
     *
     * @param int $userId The id of the user
     *
     * @return bool The banned status of the user
     */
    public function isUserBanned(int $userId): bool
    {
        // check if user is banned
        return $this->getBanRepository(['banned_user_id' => $userId, 'status' => 'active']) != null;
    }

    /**
     * Get ban reason
     *
     * @param int $userId The id of the user
     *
     * @return string|null The reason for banning the user, or null if the user
     */
    public function getBanReason(int $userId): ?string
    {
        // get banned repository
        $banned = $this->getBanRepository(['banned_user_id' => $userId, 'status' => 'active']);

        // check if banned repository exists
        if ($banned != null) {
            return $banned->getReason();
        }

        return null;
    }

    /**
     * Unban a user
     *
     * @param int $userId The id of the user to unban
     *
     * @return void
     */
    public function unBanUser(int $userId): void
    {
        // get banned repository
        $banned = $this->getBanRepository(['banned_user_id' => $userId, 'status' => 'active']);

        // check if banned repository exists
        if ($banned != null) {
            // unban user
            try {
                // set banned status to inactive
                $banned->setStatus('inactive');

                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to unban user: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }

            // log action
            $this->logManager->log(
                name: 'ban-manager',
                message: 'user: ' . $userId . ' is unbanned',
                level: LogManager::LEVEL_WARNING
            );
        }
    }

    /**
     * Get banned users list
     *
     * @return array<mixed> The list of banned users
     */
    public function getBannedUsers(): array
    {
        $banned = [];

        /** @var \App\Entity\User $users all users list */
        $users = $this->userManager->getAllUsersRepository();

        // check if $users is iterable
        if (is_iterable($users)) {
            foreach ($users as $user) {
                // check if user is banned
                if ($this->isUserBanned($user->getId())) {
                    $banned[] = $user;
                }
            }
        }

        return $banned;
    }

    /**
     * Get the count of banned users
     *
     * @return int The count of banned users
     */
    public function getBannedCount(): int
    {
        $repository = $this->entityManager->getRepository(Banned::class);

        // get banned count
        $count =  $repository->count(['status' => 'active']);

        return $count;
    }
}
