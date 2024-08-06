<?php

namespace App\Controller\Component;

use App\Util\AppUtil;
use App\Manager\BanManager;
use App\Manager\AuthManager;
use App\Manager\UserManager;
use App\Util\VisitorInfoUtil;
use App\Manager\ErrorManager;
use App\Annotation\Authorization;
use App\Form\Auth\RegistrationFormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class UsersManagerController
 *
 * The controller handle users-manager component
 *
 * @package App\Controller
 */
class UsersManagerController extends AbstractController
{
    private AppUtil $appUtil;
    private BanManager $banManager;
    private UserManager $userManager;
    private AuthManager $authManager;
    private ErrorManager $errorManager;
    private VisitorInfoUtil $visitorInfoUtil;

    public function __construct(
        AppUtil $appUtil,
        BanManager $banManager,
        UserManager $userManager,
        AuthManager $authManager,
        ErrorManager $errorManager,
        VisitorInfoUtil $visitorInfoUtil
    ) {
        $this->appUtil = $appUtil;
        $this->banManager = $banManager;
        $this->userManager = $userManager;
        $this->authManager = $authManager;
        $this->errorManager = $errorManager;
        $this->visitorInfoUtil = $visitorInfoUtil;
    }

    /**
     * Handle the users manager component
     *
     * @param Request $request The request object
     *
     * @return Response The users manager table view
     */
    #[Route('/manager/users', methods:['GET'], name: 'app_manager_users')]
    public function usersTable(Request $request): Response
    {
        // get current page from request query params
        $page = (int) $request->query->get('page', '1');

        // get page limit from config
        $pageLimit = $this->appUtil->getEnvValue('LIMIT_CONTENT_PER_PAGE');

        // get filter from request query params
        $filter = $request->query->get('filter', '');

        // get total users count from database
        $usersCount = $this->userManager->getUsersCount();

        // get users data from database
        $usersData = $this->userManager->getUsersByPage($page);

        // get online users list
        $onlineList = $this->authManager->getOnlineUsersList();

        // get admin suite database name
        $mainDatabase = $this->appUtil->getEnvValue('DATABASE_NAME');

        // get current visitor ip (for highlight current user)
        $currentVisitorIp = $this->visitorInfoUtil->getIp();

        // get users data from database based on filter
        switch ($filter) {
            case 'online':
                $usersData = $this->authManager->getOnlineUsers();
                break;
            case 'banned':
                $usersData = $this->banManager->getBannedUsers();
                break;
            default:
                $usersData = $this->userManager->getUsersByPage($page);
                break;
        }

        // render users manager table view
        return $this->render('component/users-manager/users-table.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // instances for users manager view
            'banManager' => $this->banManager,
            'userManager' => $this->userManager,
            'visitorInfoUtil' => $this->visitorInfoUtil,

            // database name
            'mainDatabase' => $mainDatabase,

            // users manager data
            'users' => $usersData,
            'onlineList' => $onlineList,

            // filter helpers
            'filter' => $filter,
            'currentIp' => $currentVisitorIp,

            // pagination data
            'currentPage' => $page,
            'limitPerPage' => $pageLimit,
            'totalUsersCount' => $usersCount
        ]);
    }

    /**
     * Handle the users manager profile viewer component
     *
     * @param Request $request The request object
     *
     * @return Response The users manager profile view
     */
    #[Route('/manager/users/profile', methods:['GET'], name: 'app_manager_users_profile')]
    public function userProfile(Request $request): Response
    {
        // get user id
        $userId = (int) $request->query->get('id', '0');

        // check if user id is empty
        if ($userId == 0) {
            $this->errorManager->handleError(
                message: 'error "id" parameter is empty',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // get online users list
        $onlineList = $this->authManager->getOnlineUsersList();

        // get user data from database
        $userRepository = $this->userManager->getUserRepository(['id' => $userId]);

        // check if user found
        if ($userRepository == null) {
            $this->errorManager->handleError(
                message: 'error to get user data: user not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        // render profile view
        return $this->render('component/users-manager/user-profile.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // visitor info util instance
            'banManager' => $this->banManager,
            'visitorInfoUtil' => $this->visitorInfoUtil,

            // users manager data
            'onlineList' => $onlineList,

            // user data
            'userRepository' => $userRepository
        ]);
    }

    /**
     * Handle the users manager register component
     *
     * @param Request $request The request object
     *
     * @return Response The users manager register view
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/users/register', methods:['GET', 'POST'], name: 'app_manager_users_register')]
    public function userRegister(Request $request): Response
    {
        // get page limit from config
        $pageLimit = (int) $this->appUtil->getEnvValue('LIMIT_CONTENT_PER_PAGE');

        // get total users count from database
        $usersCount = $this->userManager->getUsersCount();

        // create the registration form
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        // check if the form is submitted and valid
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $data get the form data */
            $data = $form->getData();

            // get the username and password
            $username = (string) $data->getUsername();
            $password = (string) $data->getPassword();

            // check if the username is already taken
            if ($this->userManager->checkIfUserExist($username)) {
                $this->addFlash('error', 'Username is already taken.');
            } else {
                try {
                    // register the user
                    $this->authManager->registerUser($username, $password);

                    // redirect back to the users table page
                    return $this->redirectToRoute('app_manager_users', [
                        'page' => $this->appUtil->calculateMaxPages($usersCount, $pageLimit)
                    ]);
                } catch (\Exception $e) {
                    if ($this->appUtil->isDevMode()) {
                        $this->errorManager->handleError(
                            message: 'create user error: ' . $e->getMessage(),
                            code: Response::HTTP_INTERNAL_SERVER_ERROR
                        );
                    } else {
                        $this->addFlash('error', 'An error occurred while registering the new user.');
                    }
                }
            }
        }

        // render users manager register form view
        return $this->render('component/users-manager/form/user-register-form.twig', [
            'isAdmin' => $this->authManager->isLoggedInUserAdmin(),
            'userData' => $this->authManager->getLoggedUserRepository(),

            // registration form
            'registrationForm' => $form->createView()
        ]);
    }

    /**
     * Handle the users manager update role component
     *
     * @param Request $request The request object
     *
     * @return Response The users manager table redirect
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/users/role/update', methods:['POST'], name: 'app_manager_users_role_update')]
    public function userRoleUpdate(Request $request): Response
    {
        // get user id to delete
        $userId = (int) $request->query->get('id');

        // get current page from request query params
        $page = (int) $request->query->get('page', '1');

        // get new user role to update
        $newRole = (string) $request->query->get('role');

        // check if user id is valid
        if ($userId == null) {
            $this->errorManager->handleError(
                message: 'invalid request user "id" parameter not found in query',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if new user role is valid
        if ($newRole == null) {
            $this->errorManager->handleError(
                message: 'invalid request user "role" parameter not found in query',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if user id is valid
        if (!$this->userManager->checkIfUserExistById($userId)) {
            $this->errorManager->handleError(
                message: 'invalid request user "id" parameter not found in database',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // convert new user role to uppercase
        $newRole = strtoupper($newRole);

        // get user role from database
        $currentRole = $this->userManager->getUserRoleById($userId);

        // check if user role is the same
        if ($currentRole == $newRole) {
            $this->errorManager->handleError(
                message: 'invalid user "role" parameter is same with current user role',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // update the user role
        $this->userManager->updateUserRole($userId, $newRole);

        // redirect back to the users table page
        return $this->redirectToRoute('app_manager_users', [
            'page' => $page
        ]);
    }

    /**
     * Handle the users manager delete component
     *
     * @param Request $request The request object
     *
     * @return Response The users manager redirect
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/users/delete', methods:['GET'], name: 'app_manager_users_delete')]
    public function userDelete(Request $request): Response
    {
        // get user id to delete
        $userId = (int) $request->query->get('id');

        // get referer page
        $refererPage = $request->query->get('page', '1');

        // check if user id is valid
        if ($userId == null) {
            $this->errorManager->handleError(
                message: 'invalid request user "id" parameter not found in query',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if user id is valid
        if (!$this->userManager->checkIfUserExistById($userId)) {
            $this->errorManager->handleError(
                message: 'invalid request user "id" parameter not found in database',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // delete the user
        $this->userManager->deleteUser((int) $userId);

        // unban the user if user is banned
        if ($this->banManager->isUserBanned((int) $userId)) {
            $this->banManager->unbanUser((int) $userId);
        }

        // redirect back to the users table page
        return $this->redirectToRoute('app_manager_users', [
            'page' => $refererPage
        ]);
    }

    /**
     * Handle the users manager ban component
     *
     * @param Request $request The request object
     *
     * @return Response The users manager redirect
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/users/ban', methods:['GET'], name: 'app_manager_users_ban')]
    public function banUser(Request $request): Response
    {
        // get request data
        $userId = (int) $request->query->get('id');
        $page = (int) $request->query->get('page', '1');
        $status = (string) $request->query->get('status');
        $reason = (string) $request->query->get('reason');

        // validate user id & status
        if ($userId == 0 || $status == null) {
            $this->errorManager->handleError(
                message: 'invalid request user "id" or "status" parameter not found in query',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if status is valid
        if ($status != 'active' && $status !== 'inactive') {
            $this->errorManager->handleError(
                message: 'invalid request user "status" parameter accept only active or inactive',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if user id is self ban
        if ($userId == $this->authManager->getLoggedUserId()) {
            $this->errorManager->handleError(
                message: 'you cannot ban yourself',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if reason is set
        if ($status == 'active' && $reason == null) {
            $reason = 'no-reason';
        }

        // check if user not exist in database
        if (!$this->userManager->checkIfUserExistById($userId)) {
            $this->errorManager->handleError(
                message: 'invalid request user "id" not found in database',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if banned is active
        if ($status == 'active') {
            // check if user already banned
            if ($this->banManager->isUserBanned($userId)) {
                $this->errorManager->handleError(
                    message: 'invalid request user "id" is already banned',
                    code: Response::HTTP_BAD_REQUEST
                );
            }

            // ban user
            $this->banManager->banUser($userId, $reason);
        } else {
            // unban user
            $this->banManager->unbanUser($userId);
        }

        // redirect back to the users table page
        return $this->redirectToRoute('app_manager_users', [
            'page' => $page
        ]);
    }
}
