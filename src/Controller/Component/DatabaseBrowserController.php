<?php

namespace App\Controller\Component;

use App\Util\AppUtil;
use App\Manager\AuthManager;
use App\Manager\ErrorManager;
use App\Manager\DatabaseManager;
use App\Annotation\Authorization;
use App\Form\Database\QueryConsoleFormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class DatabaseBrowserController
 *
 * This controller is responsible for rendering the database browser page
 *
 * @package App\Controller\Component
 */
class DatabaseBrowserController extends AbstractController
{
    private AppUtil $appUtil;
    private AuthManager $authManager;
    private ErrorManager $errorManager;
    private DatabaseManager $databaseManager;

    public function __construct(
        AppUtil $appUtil,
        AuthManager $authManager,
        ErrorManager $errorManager,
        DatabaseManager $databaseManager
    ) {
        $this->appUtil = $appUtil;
        $this->authManager = $authManager;
        $this->errorManager = $errorManager;
        $this->databaseManager = $databaseManager;
    }

    /**
     * Renders the database browser page
     *
     * @param Request $request The request object
     *
     * @return Response The rendered database browser page
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database', methods:['GET'], name: 'app_manager_database')]
    public function databaseBrowser(Request $request): Response
    {
        // get database name from query parameter
        $databaseName = (string) $request->query->get('database', '');

        // check if database name set
        if ($databaseName == '') {
            // get the list of databases
            $databases = $this->databaseManager->getDatabasesList();

            // disable table browsing
            $tables = null;
        } else {
            // get the list of databases
            $databases = null;

            // disable table browsing
            $tables = $this->databaseManager->getTablesList($databaseName);
        }

        // render the database browser page
        return $this->render('component/database-browser/database-browser.twig', [
            'isAdmin' => true,
            'userData' => $this->authManager->getLoggedUserRepository(),

            // database browser data
            'tables' => $tables,
            'databases' => $databases,
            'databaseName' => $databaseName
        ]);
    }

    /**
     * Renders the table data browser page
     *
     * @param Request $request The request object
     *
     * @return Response The rendered table data browser page
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/table', methods:['GET'], name: 'app_manager_database_table_browser')]
    public function databaseTableBrowser(Request $request): Response
    {
        // get request parameters
        $page = (int) $request->query->get('page', '1');
        $tableName = (string) $request->query->get('table');
        $databaseName = (string) $request->query->get('database');

        // check if table name and database name set
        if ($tableName == '' || $databaseName == '') {
            $this->errorManager->handleError(
                message: 'table name and database name are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // get page limiter
        $limitPerPage = $this->appUtil->getPageLimiter();

        // get the data from the table
        $tableData = $this->databaseManager->getTableData($databaseName, $tableName, $page);

        // get the number of rows in the table
        $tableDataCount = $this->databaseManager->getTableRowCount($databaseName, $tableName);

        // render the table browser page
        return $this->render('component/database-browser/table-browser.twig', [
            'isAdmin' => true,
            'userData' => $this->authManager->getLoggedUserRepository(),

            // filter data
            'currentPage' => $page,
            'tableName' => $tableName,
            'databaseName' => $databaseName,
            'limitPerPage' => $limitPerPage,

            // table data
            'tableData' => $tableData,
            'tableDataCount' => $tableDataCount
        ]);
    }

    /**
     * Renders the add row form for a specific table in a specific database
     *
     * @param Request $request The request object
     *
     * @return Response The rendered add row form
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/add', methods: ['GET', 'POST'], name: 'app_manager_database_add')]
    public function databaseAddRow(Request $request): Response
    {
        // get request parameters
        $tableName = (string) $request->query->get('table');
        $databaseName = (string) $request->query->get('database');

        // check if table name and database name are set
        if (empty($tableName) || empty($databaseName)) {
            $this->errorManager->handleError(
                message: 'Table name and database name are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if table exists
        if (!$this->databaseManager->isTableExists($databaseName, $tableName)) {
            $this->errorManager->handleError(
                message: "Table '{$tableName}' not found in database '{$databaseName}'",
                code: Response::HTTP_NOT_FOUND
            );
        }

        // get columns to generate form
        $columns = $this->databaseManager->getColumnsList($databaseName, $tableName);

        // prepare form data
        $errors = [];
        $formData = [];

        // check if form is submitted with POST method
        if ($request->isMethod('POST')) {
            /** @var array<mixed> $formData */
            $formData = $request->request->all();

            // form data validation
            foreach ($columns as $column) {
                /** @var string $columnName */
                $columnName = $column['COLUMN_NAME'];

                /** @var string $isNullable */
                $isNullable = $column['IS_NULLABLE'] === 'YES';

                /** @var string $columnType */
                $columnType = $column['COLUMN_TYPE'];

                // check if value is present for non-nullable fields
                if (!$isNullable && empty($formData[$columnName])) {
                    $errors[] = 'The field ' . $columnName . ' is required and cannot be empty.';
                }

                // check if value is valid for specific column types
                if (!empty($formData[$columnName])) {
                    /** @var string $value */
                    $value = $formData[$columnName];
                    if (strpos($columnType, 'int') !== false && !is_numeric($value)) {
                        $errors[] = 'The field ' . $columnName . ' must be a number.';
                    }
                    if (strpos($columnType, 'varchar') !== false) {
                        $maxLength = (int) filter_var($columnType, FILTER_SANITIZE_NUMBER_INT);
                        if (strlen($value) > $maxLength) {
                            $errors[] = 'The field ' . $columnName . ' must not exceed {$maxLength} characters.';
                        }
                    }
                }
            }

            $rowId = 0;
            if (isset($formData['id'])) {
                /** @var int $rowId */
                $rowId = $formData['id'];
            }

            // check if record already exists
            if ($rowId != 0) {
                if ($this->databaseManager->doesRecordExist($databaseName, $tableName, $rowId)) {
                    $errors[] = 'Record with ID ' . $rowId . ' already exists.';
                }
            }

            // check errors
            if (empty($errors)) {
                // add row to table
                $this->databaseManager->addRowToTable($formData, $databaseName, $tableName);

                // get the last page number
                $lastPageNumber = $this->databaseManager->getLastPageNumber($databaseName, $tableName);

                // redirect to table browser
                return $this->redirectToRoute('app_manager_database_table_browser', [
                    'database' => $databaseName,
                    'table' => $tableName,
                    'page' => $lastPageNumber
                ], Response::HTTP_FOUND);
            }
        }

        // render the add row form
        return $this->render('component/database-browser/form/add-row.twig', [
            'isAdmin' => true,
            'userData' => $this->authManager->getLoggedUserRepository(),

            // filter data
            'tableName' => $tableName,
            'databaseName' => $databaseName,

            // form data
            'errors' => $errors,
            'columns' => $columns,
            'formData' => $formData
        ]);
    }

    /**
     * Renders the edit row form for a specific table in a specific database
     *
     * @param Request $request The request object
     *
     * @return Response The rendered edit row form
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/edit', methods: ['GET', 'POST'], name: 'app_manager_database_edit')]
    public function databaseEditRow(Request $request): Response
    {
        // get request parameters
        $id = (int) $request->query->get('id');
        $page = (int) $request->query->get('page', '1');
        $tableName = (string) $request->query->get('table');
        $databaseName = (string) $request->query->get('database');

        // check if table name and database name are set
        if (empty($tableName) || empty($databaseName) || empty($id)) {
            $this->errorManager->handleError(
                message: 'table name/database name and row id are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if table exists
        if (!$this->databaseManager->isTableExists($databaseName, $tableName)) {
            $this->errorManager->handleError(
                message: 'table ' . $tableName . ' not found in database ' . $databaseName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // check if record exists
        if (!$this->databaseManager->doesRecordExist($databaseName, $tableName, $id)) {
            $this->errorManager->handleError(
                message: 'record with ID ' . $id . ' not found in table ' . $tableName . ' in database ' . $databaseName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // get columns to generate form
        $columns = $this->databaseManager->getColumnsList($databaseName, $tableName);

        // fetch existing row data for pre-filling the form
        $existingData = $this->databaseManager->getRowById($databaseName, $tableName, $id);

        // initialize form data with existing values or empty if not found
        $formData = $existingData ?: [];

        // unset id column
        if ($columns[0]['COLUMN_NAME'] == 'id') {
            unset($columns[0]);
        }

        // prepare form data
        $errors = [];

        // check if form is submitted with POST method
        if ($request->isMethod('POST')) {
            /** @var array<mixed> $formData */
            $formData = $request->request->all();

            // form data validation
            foreach ($columns as $column) {
                /** @var string $columnName */
                $columnName = $column['COLUMN_NAME'];

                /** @var string $isNullable */
                $isNullable = $column['IS_NULLABLE'] === 'YES';

                /** @var string $columnType */
                $columnType = $column['COLUMN_TYPE'];

                // check if value is present for non-nullable fields
                if (!$isNullable && empty($formData[$columnName])) {
                    $errors[] = 'The field ' . $columnName . ' is required and cannot be empty.';
                }

                // check if value is valid for specific column types
                if (!empty($formData[$columnName])) {
                    /** @var string $value */
                    $value = $formData[$columnName];
                    if (strpos($columnType, 'int') !== false && !is_numeric($value)) {
                        $errors[] = 'The field ' . $columnName . ' must be a number.';
                    }
                    if (strpos($columnType, 'varchar') !== false) {
                        $maxLength = (int) filter_var($columnType, FILTER_SANITIZE_NUMBER_INT);
                        if (strlen($value) > $maxLength) {
                            $errors[] = 'The field ' . $columnName . ' must not exceed {$maxLength} characters.';
                        }
                    }
                }
            }

            // set row id
            $formData['id'] = $id;

            // check errors
            if (empty($errors)) {
                // update row in table
                $this->databaseManager->updateRowById($formData, $databaseName, $tableName, $id);

                // redirect to table browser
                return $this->redirectToRoute('app_manager_database_table_browser', [
                    'database' => $databaseName,
                    'table' => $tableName,
                    'page' => $page
                ]);
            }
        }

        // render the edit row form
        return $this->render('component/database-browser/form/edit-row.twig', [
            'isAdmin' => true,
            'userData' => $this->authManager->getLoggedUserRepository(),

            // filter data
            'id' => $id,
            'page' => $page,
            'tableName' => $tableName,
            'databaseName' => $databaseName,

            // form data
            'errors' => $errors,
            'columns' => $columns,
            'formData' => $formData
        ]);
    }

    /**
     * Renders the delete row form for a specific table in a specific database
     *
     * @param Request $request The request object
     *
     * @return Response The rendered delete row form
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/delete', methods: ['GET'], name: 'app_manager_database_delete')]
    public function databaseDeleteRow(Request $request): Response
    {
        // get request parameters
        $id = (int) $request->query->get('id');
        $page = (int) $request->query->get('page', '1');
        $tableName = (string) $request->query->get('table');
        $databaseName = (string) $request->query->get('database');

        // check if table name and database name are set
        if (empty($tableName) || empty($databaseName) || empty($id)) {
            $this->errorManager->handleError(
                message: 'table name/database name and row id are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if table exists
        if (!$this->databaseManager->isTableExists($databaseName, $tableName)) {
            $this->errorManager->handleError(
                message: 'table ' . $tableName . ' not found in database ' . $databaseName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // check if record exists
        if (!$this->databaseManager->doesRecordExist($databaseName, $tableName, $id)) {
            $this->errorManager->handleError(
                message: 'record with ID ' . $id . ' not found in table ' . $tableName . ' in database ' . $databaseName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // delete row from table
        $this->databaseManager->deleteRowById($databaseName, $tableName, $id);

        // redirect to table browser
        return $this->redirectToRoute('app_manager_database_table_browser', [
            'page' => $page,
            'table' => $tableName,
            'database' => $databaseName
        ]);
    }

    /**
     * Renders the truncate table form for a specific table in a specific database
     *
     * @param Request $request The request object
     *
     * @return Response The rendered truncate table form
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/truncate', methods: ['GET'], name: 'app_manager_database_truncate')]
    public function databaseTruncateTable(Request $request): Response
    {
        // get request parameters
        $confirm = (string) $request->query->get('confirm', 'no');
        $tableName = (string) $request->query->get('table');
        $databaseName = (string) $request->query->get('database');

        // check confirmation
        if ($confirm !== 'yes') {
            return $this->render('component/database-browser/form/truncate-confirmation.twig', [
                'isAdmin' => true,
                'userData' => $this->authManager->getLoggedUserRepository(),

                // confirmation data
                'databaseName' => $databaseName,
                'tableName' => $tableName
            ]);
        }

        // check if table name and database name are set
        if (empty($tableName) || empty($databaseName)) {
            $this->errorManager->handleError(
                message: 'table name and database name are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if table exists
        if (!$this->databaseManager->isTableExists($databaseName, $tableName)) {
            $this->errorManager->handleError(
                message: 'table ' . $tableName . ' not found in database ' . $databaseName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // truncate table
        $this->databaseManager->tableTruncate($databaseName, $tableName);

        // redirect to table browser
        return $this->redirectToRoute('app_manager_database_table_browser', [
            'page' => 1,
            'table' => $tableName,
            'database' => $databaseName
        ]);
    }

    /**
     * Dump database to file
     *
     * @param Request $request The request object
     *
     * @return Response The rendered database dump page
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/dump', methods: ['GET'], name: 'app_manager_database_dump')]
    public function databaseDump(Request $request): Response
    {
        // get request parameters
        $plain = (string) $request->query->get('plain', 'no');
        $select = (string) $request->query->get('select', 'yes');
        $databaseName = (string) $request->query->get('database');

        // check if dump mode is select
        if ($select === 'yes') {
            // get databases list
            $databases = $this->databaseManager->getDatabasesList();

            return $this->render('component/database-browser/database-dump.twig', [
                'isAdmin' => true,
                'userData' => $this->authManager->getLoggedUserRepository(),

                // database dump data
                'databases' => $databases,
            ]);
        }

        // check if database name and plain flag are set
        if (empty($databaseName) || empty($plain)) {
            $this->errorManager->handleError(
                message: 'database name and plain flag are required',
                code: Response::HTTP_BAD_REQUEST
            );
        }

        // check if database exists
        if (!$this->databaseManager->isDatabaseExists($databaseName)) {
            $this->errorManager->handleError(
                message: 'database ' . $databaseName . ' not found',
                code: Response::HTTP_NOT_FOUND
            );
        }

        // check if plain flag is set
        if ($plain === 'yes') {
            $plain = true;
        } else {
            $plain = false;
        }

        // get database dump
        $databaseDump = $this->databaseManager->getDatabaseDump($databaseName, $plain);

        // return the database dump
        return new Response(
            content: $databaseDump,
            status: Response::HTTP_OK,
            headers:[
                'Content-Type' => 'application/sql',
                'Content-Disposition' => 'attachment; filename="' . $databaseName . '_dump.sql' . '"',
            ]
        );
    }

    /**
     * Renders the database console page
     *
     * @param Request $request The request object
     *
     * @return Response The rendered database console page
     */
    #[Authorization(authorization: 'ADMIN')]
    #[Route('/manager/database/console', methods: ['GET', 'POST'], name: 'app_manager_database_console')]
    public function databaseConsole(Request $request): Response
    {
        // create query console form
        $queryForm = $this->createForm(QueryConsoleFormType::class);
        $queryForm->handleRequest($request);

        // default output
        $output = null;

        // check if form is submitted
        if ($queryForm->isSubmitted() && $queryForm->isValid()) {
            /** @var string $query sql query input */
            $query = $queryForm->get('query')->getData();

            // execute query
            $output = $this->databaseManager->executeQuery($query);
        }

        return $this->render('component/database-browser/database-console.twig', [
            'isAdmin' => true,
            'userData' => $this->authManager->getLoggedUserRepository(),

            // query console form
            'output' => $output,
            'queryForm' => $queryForm->createView()
        ]);
    }
}
