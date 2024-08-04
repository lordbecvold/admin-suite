<?php

namespace App\Manager;

use App\Entity\User;
use App\Util\AppUtil;
use App\Util\SecurityUtil;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserManager
 *
 * Contains methods to manage user data
 *
 * @package App\Manager
 */
class UserManager
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private SecurityUtil $securityUtil;
    private ErrorManager $errorManager;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        SecurityUtil $securityUtil,
        ErrorManager $errorManager,
        EntityManagerInterface $entityManager
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->errorManager = $errorManager;
        $this->securityUtil = $securityUtil;
        $this->entityManager = $entityManager;
    }

    /**
     * Retrieve a user from the repository based on search criteria
     *
     * @param array<mixed> $search The search criteria
     *
     * @return User|null The user object if found, null otherwise
     */
    public function getUserRepository(array $search): ?User
    {
        // get user repo
        return $this->entityManager->getRepository(User::class)->findOneBy($search);
    }

    /**
     * Retrieve all users from the repository
     *
     * @return array<mixed> The user object if found, null otherwise
     */
    public function getAllUsersRepository(): ?array
    {
        return $this->entityManager->getRepository(User::class)->findAll();
    }

    /**
     * Retrieve all users count from the repository
     *
     * @return int|null The user object if found, null otherwise
     */
    public function getUsersCount(): ?int
    {
        return $this->entityManager->getRepository(User::class)->count([]);
    }

    /**
     * Retrieve all users from the repository by page
     *
     * @param int $page The users list page number
     *
     * @return array<mixed> The user object if found, null otherwise
     */
    public function getUsersByPage(int $page = 1): ?array
    {
        // get page limitter
        $perPage = $this->appUtil->getPageLimiter();

        // calculate offset
        $offset = ($page - 1) * $perPage;

        // get user repo
        return $this->entityManager->getRepository(User::class)->findBy(
            [],
            null,
            $perPage,
            $offset
        );
    }

    /**
     * Check if a user exists
     *
     * @param string $username The username to check
     *
     * @return bool True if the user exists, otherwise false
     */
    public function checkIfUserExist(string $username): bool
    {
        return $this->getUserRepository(['username' => $username]) != null;
    }

    /**
     * Check if a user exists by ID
     *
     * @param int $userId The id of the user to check
     *
     * @return bool True if the user exists, otherwise false
     */
    public function checkIfUserExistById(int $userId): bool
    {
        return $this->getUserRepository(['id' => $userId]) != null;
    }

    /**
     * Get the username of a user
     *
     * @param int $userId The id of the user to get the username
     *
     * @return string The username of the user
     */
    public function getUsernameById(int $userId): ?string
    {
        $repo = $this->getUserRepository(['id' => $userId]);

        // check if user exist
        if ($repo != null) {
            return $repo->getUsername();
        }

        return null;
    }

    /**
     * Get the role of a user
     *
     * @param int $userId The id of the user to get the role
     *
     * @return string The role of the user
     */
    public function getUserRoleById(int $userId): ?string
    {
        $repo = $this->getUserRepository(['id' => $userId]);

        // check if user exist
        if ($repo != null) {
            return $repo->getRole();
        }

        return null;
    }

    /**
     * Checks if the specified user has the admin role
     *
     * @param int $userId The id of the user to check the admin role
     *
     * @return bool True if the user has the admin role, otherwise false
     */
    public function isUserAdmin(int $userId): bool
    {
        $role = $this->getUserRoleById($userId);

        // check if user has admin role
        if ($role == 'ADMIN' || $role == 'DEVELOPER' || $role == 'OWNER') {
            return true;
        }

        return false;
    }

    /**
     * Update the role of a user
     *
     * @param int $userId The id of the user to add the admin role
     * @param string $role The role to add
     *
     * @throws \Exception If there is an error while adding the admin role
     *
     * @return void
     */
    public function updateUserRole(int $userId, string $role): void
    {
        // get user repo
        $repo = $this->getUserRepository(['id' => $userId]);

        // convert new user role to uppercase
        $role = strtoupper($role);

        // check if user exist
        if ($repo != null) {
            try {
                // update role
                $repo->setRole(strtoupper($role));

                // flush updated user data
                $this->entityManager->flush();

                // log action
                $this->logManager->log(
                    name: 'user-manager',
                    message: 'update role (' . $role . ') for user: ' . $repo->getUsername(),
                    level: LogManager::LEVEL_WARNING
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to grant admin permissions: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }

    /**
     * Checks if the user repository is empty
     *
     * @return bool True if the user repository is empty, false otherwise
     */
    public function isUsersEmpty(): bool
    {
        $repository = $this->entityManager->getRepository(User::class);

        // get users count
        $count = $repository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // check if count is zero
        if ($count == 0) {
            return true;
        }

        return false;
    }

    /**
     * Delete a user
     *
     * @param int $userId The id of the user to delete
     *
     * @throws \Exception If there is an error while deleting the user
     *
     * @return void
     */
    public function deleteUser(int $userId): void
    {
        // get user repo
        $repo = $this->getUserRepository(['id' => $userId]);

        // check if user exist
        if ($repo != null) {
            try {
                // delete user
                $this->entityManager->remove($repo);
                $this->entityManager->flush();

                // log action
                $this->logManager->log(
                    name: 'user-manager',
                    message: 'user: ' . $repo->getUsername() . ' deleted',
                    level: LogManager::LEVEL_WARNING
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to delete user: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }

    /**
     * Update the user username
     *
     * @param int $userId The id of the user to update the username
     * @param string $newUsername The new username
     *
     * @throws \Exception If there is an error while updating the username.
     *
     * @return void
     */
    public function updateUsername(int $userId, string $newUsername): void
    {
        // get user repo
        $repo = $this->getUserRepository(['id' => $userId]);

        // check if user exist
        if ($repo != null) {
            try {
                // get old username
                $oldUsername = $repo->getUsername();

                // update username
                $repo->setUsername($newUsername);

                // flush updated user data
                $this->entityManager->flush();

                // log action
                $this->logManager->log(
                    name: 'account-settings',
                    message: 'update username (' . $newUsername . ') for user: ' . $oldUsername,
                    level: LogManager::LEVEL_INFO
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to update username: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }

    /**
     * Update the user password
     *
     * @param int $userId The id of the user to update the password
     * @param string $newPassword The new password
     *
     * @throws \Exception If there is an error while updating the password.
     *
     * @return void
     */
    public function updatePassword(int $userId, string $newPassword): void
    {
        // get user repo
        $repo = $this->getUserRepository(['id' => $userId]);

        // check if user exist
        if ($repo != null) {
            try {
                // hash new password
                $passwordHash = $this->securityUtil->generateHash($newPassword);

                // update password
                $repo->setPassword($passwordHash);

                // flush updated user data
                $this->entityManager->flush();

                // log action
                $this->logManager->log(
                    name: 'account-settings',
                    message: 'update password for user: ' . $repo->getUsername(),
                    level: LogManager::LEVEL_INFO
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to update password: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }

    /**
     * Update the user profile picture
     *
     * @param int $userId The id of the user to update the profile picture
     * @param string $newProfilePicture The new profile picture
     *
     * @throws \Exception If there is an error while updating the profile picture.
     *
     * @return void
     */
    public function updateProfilePicture(int $userId, string $newProfilePicture): void
    {
        // get user repo
        $repo = $this->getUserRepository(['id' => $userId]);

        // check if user exist
        if ($repo != null) {
            try {
                // update profile picture
                $repo->setProfilePic($newProfilePicture);

                // flush updated user data
                $this->entityManager->flush();

                // log action
                $this->logManager->log(
                    name: 'account-settings',
                    message: 'update profile picture for user: ' . $repo->getUsername(),
                    level: LogManager::LEVEL_INFO
                );
            } catch (\Exception $e) {
                $this->errorManager->handleError(
                    message: 'error to update profile picture: ' . $e->getMessage(),
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }
    }
}
