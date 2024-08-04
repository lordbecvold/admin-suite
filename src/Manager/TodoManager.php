<?php

namespace App\Manager;

use App\Entity\Todo;
use App\Util\SecurityUtil;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TodoManager
 *
 * Manager for todo entity operations
 *
 * @package App\Manager
 */
class TodoManager
{
    private LogManager $logManager;
    private AuthManager $authManager;
    private SecurityUtil $securityUtil;
    private ErrorManager $errorManager;
    private EntityManagerInterface $entityManagerInterface;

    public function __construct(
        LogManager $logManager,
        AuthManager $authManager,
        SecurityUtil $securityUtil,
        ErrorManager $errorManager,
        EntityManagerInterface $entityManagerInterface
    ) {
        $this->logManager = $logManager;
        $this->authManager = $authManager;
        $this->securityUtil = $securityUtil;
        $this->errorManager = $errorManager;
        $this->entityManagerInterface = $entityManagerInterface;
    }

    /**
     * Get all todos
     *
     * @return array<mixed> The todo list
     */
    public function getTodos(string $filter = 'open'): array
    {
        // init the plain todo list
        $plainTodos = [];

        // get the todo list
        $todos = $this->entityManagerInterface->getRepository(Todo::class)->findBy(
            [
                'user_id' => $this->authManager->getLoggedUserId(),
                'status' => $filter
            ],
            ['id' => 'DESC']
        );

        // decrypt the todo texts
        foreach ($todos as $todo) {
            $plainTodos[] = [
                'id' => $todo->getId(),
                'todoText' => $this->securityUtil->decryptAes((string) $todo->getTodoText()),
                'addedTime' => $todo->getAddedTime(),
                'completedTime' => $todo->getCompletedTime(),
                'status' => $todo->getStatus(),
                'userId' => $todo->getUserId()
            ];
        }

        // return the plain todo list
        return $plainTodos;
    }

    /**
     * Get todo status
     *
     * @param int $todoId The todo id
     *
     * @return string|null The todo status
     */
    public function getTodoStatus(int $todoId): ?string
    {
        /** @var Todo $todo */
        $todo = $this->entityManagerInterface->getRepository(Todo::class)->find($todoId);

        // check if todo found
        if ($todo == null) {
            $this->errorManager->handleError(
                message: 'todo not found',
                code: Response::HTTP_NOT_FOUND
            );
            return null;
        }

        // get todo status
        $status = $todo->getStatus();

        return $status;
    }

    /**
     * Create a new todo
     *
     * @param string $todoText The todo text
     *
     * @return void
     */
    public function createTodo(string $todoText): void
    {
        // create the todo entity
        $todo = new Todo();

        // encrypt the todo text
        $todoText = $this->securityUtil->encryptAes($todoText);

        try {
            // set todo properties
            $todo->setTodoText($todoText)
                ->setAddedTime(new \DateTime())
                ->setCompletedTime(null)
                ->setStatus('open')
                ->setUserId($this->authManager->getLoggedUserId());

            // save the todo entity
            $this->entityManagerInterface->persist($todo);
            $this->entityManagerInterface->flush();

            // log the todo creation
            $this->logManager->log(
                name: 'todo-manager',
                message: 'new todo created',
                level: LogManager::LEVEL_INFO
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to create todo: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Edit a todo
     *
     * @param int $todoId The todo id
     * @param string $todoText The todo text
     *
     * @return void
     */
    public function editTodo(int $todoId, string $todoText): void
    {
        /** @var Todo $todo */
        $todo = $this->entityManagerInterface->getRepository(Todo::class)->find($todoId);

        // check if the todo is not null
        if ($todo === null) {
            $this->errorManager->handleError(
                message: 'todo: ' . $todoId . ' not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        // check if the user is the owner of the todo
        if ($todo->getUserId() !== $this->authManager->getLoggedUserId()) {
            $this->errorManager->handleError(
                message: 'you are not the owner of the todo: ' . $todoId,
                code: Response::HTTP_FORBIDDEN
            );
        }

        // check if the todo is closed
        if ($todo->getStatus() !== 'open') {
            $this->errorManager->handleError(
                message: 'todo: ' . $todoId . ' is closed',
                code: Response::HTTP_FORBIDDEN
            );
        }

        // encrypt the todo text
        $todoText = $this->securityUtil->encryptAes($todoText);

        try {
            // set todo properties
            $todo->setTodoText($todoText);
            $todo->setCompletedTime(null);

            // save the todo entity
            $this->entityManagerInterface->persist($todo);
            $this->entityManagerInterface->flush();

            // log the todo creation
            $this->logManager->log(
                name: 'todo-manager',
                message: 'todo edited',
                level: LogManager::LEVEL_INFO
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to edit todo: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Close a todo
     *
     * @param int $todoId The todo id
     *
     * @return void
     */
    public function closeTodo(int $todoId): void
    {
        /** @var Todo $todo */
        $todo = $this->entityManagerInterface->getRepository(Todo::class)->find($todoId);

        // check if the todo is not null
        if ($todo === null) {
            $this->errorManager->handleError(
                message: 'todo: ' . $todoId . ' not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        // check if the user is the owner of the todo
        if ($todo->getUserId() !== $this->authManager->getLoggedUserId()) {
            $this->errorManager->handleError(
                message: 'you are not the owner of the todo: ' . $todoId,
                code: Response::HTTP_FORBIDDEN
            );
        }

        // check if the todo is closed
        if ($todo->getStatus() !== 'open') {
            $this->errorManager->handleError(
                message: 'todo: ' . $todoId . ' is already closed',
                code: Response::HTTP_FORBIDDEN
            );
        }

        try {
            // set the todo status to closed
            $todo->setStatus('closed');
            $todo->setCompletedTime(new \DateTime());

            // save the todo entity
            $this->entityManagerInterface->persist($todo);
            $this->entityManagerInterface->flush();

            // log the todo creation
            $this->logManager->log(
                name: 'todo-manager',
                message: 'todo closed',
                level: LogManager::LEVEL_INFO
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to close todo: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a todo
     *
     * @param int $todoId The todo id
     *
     * @return void
     */
    public function deleteTodo(int $todoId): void
    {
        try {
            // get the todo entity
            $todo = $this->entityManagerInterface->getRepository(Todo::class)->find($todoId);

            // check if the todo entity is found
            if ($todo == null) {
                $this->errorManager->handleError(
                    message: 'todo not found',
                    code: Response::HTTP_NOT_FOUND
                );
            } else {
                // check if the user is the owner of the todo
                if ($todo->getUserId() !== $this->authManager->getLoggedUserId()) {
                    $this->errorManager->handleError(
                        message: 'you are not the owner of the todo: ' . $todoId,
                        code: Response::HTTP_FORBIDDEN
                    );
                }

                // check if the todo is closed
                if ($todo->getStatus() != 'closed') {
                    $this->errorManager->handleError(
                        message: 'todo: ' . $todoId . ' is not closed',
                        code: Response::HTTP_FORBIDDEN
                    );
                }

                // delete the todo entity
                $this->entityManagerInterface->remove($todo);
                $this->entityManagerInterface->flush();

                // log the todo deletion
                $this->logManager->log(
                    name: 'todo-manager',
                    message: 'todo deleted',
                    level: LogManager::LEVEL_INFO
                );
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to delete todo: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
