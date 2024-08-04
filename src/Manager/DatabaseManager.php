<?php

namespace App\Manager;

use PDO;
use PDOException;
use App\Util\AppUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DatabaseManager
 *
 * The manager for database connection
 *
 * @package App\Manager
 */
class DatabaseManager
{
    private AppUtil $appUtil;
    private LogManager $logManager;
    private Connection $connection;
    private ErrorManager $errorManager;

    public function __construct(
        AppUtil $appUtil,
        LogManager $logManager,
        Connection $connection,
        ErrorManager $errorManager
    ) {
        $this->appUtil = $appUtil;
        $this->logManager = $logManager;
        $this->connection = $connection;
        $this->errorManager = $errorManager;
    }

    /**
     * Get database connection
     *
     * @return Connection|null The database connection
     */
    public function getDatabaseConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * Check if database is down
     *
     * @return bool True if database is down, false otherwise
     */
    public function isDatabaseDown(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Exception) {
            return true;
        }

        return false;
    }

    /**
     * Get the list of databases
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return array<int,array<string,mixed>> The list of databases
     */
    public function getDatabasesList(): array
    {
        $databaseInfo = [];
        $sql = 'SHOW DATABASES';

        try {
            $stmt = $this->connection->executeQuery($sql);
            $databases = $stmt->fetchAllAssociative();

            foreach ($databases as $db) {
                $dbName = $db['Database'];

                // get the number of tables
                $sqlTables = "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = :dbName";
                $stmtTables = $this->connection->executeQuery($sqlTables, ['dbName' => $dbName]);
                $tableCount = $stmtTables->fetchOne();

                // get the size of the database
                $sqlSize = "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb 
                            FROM information_schema.tables 
                            WHERE table_schema = :dbName";
                $stmtSize = $this->connection->executeQuery($sqlSize, ['dbName' => $dbName]);
                $sizeMb = $stmtSize->fetchOne();

                $databaseInfo[] = [
                    'name' => $dbName,
                    'table_count' => $tableCount,
                    'size_mb' => $sizeMb
                ];
            }

            // log the action
            $this->logManager->log(
                name: 'database-manager',
                message: 'get databases list',
                level: LogManager::LEVEL_NOTICE
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to get databases list: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $databaseInfo;
    }

    /**
     * Check if a database exists
     *
     * @param string $dbName The name of the database
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return bool True if the database exists, false otherwise
     */
    public function isDatabaseExists(string $dbName): bool
    {
        $sql = 'SHOW DATABASES LIKE :dbName';

        try {
            $stmt = $this->connection->executeQuery($sql, ['dbName' => $dbName]);
            $count = $stmt->fetchOne();

            return $count > 0;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error checking if database exists: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return false;
    }

    /**
     * Get the list of tables in a database
     *
     * @param string $dbName The database name
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return array<int,array<string,mixed>>|null The list of tables
     */
    public function getTablesList(string $dbName): ?array
    {
        try {
            // get table names and sizes
            $sql = "SELECT 
                        table_name AS name, 
                        COALESCE(data_length + index_length, 0) / 1024 / 1024 AS size_mb
                    FROM 
                        information_schema.tables 
                    WHERE 
                        table_schema = :dbName";
            $stmt = $this->connection->executeQuery($sql, ['dbName' => $dbName]);
            $tables = $stmt->fetchAllAssociative();

            // get row counts
            $sqlRows = "SELECT 
                            table_name AS name, 
                            table_rows AS row_count
                        FROM 
                            information_schema.tables 
                        WHERE 
                            table_schema = :dbName";
            $stmtRows = $this->connection->executeQuery($sqlRows, ['dbName' => $dbName]);
            $rows = $stmtRows->fetchAllAssociative();

            // merge results
            $tablesWithRows = [];
            foreach ($tables as $table) {
                $tableName = $table['name'];
                $table['row_count'] = 0;
                foreach ($rows as $row) {
                    if ($row['name'] === $tableName) {
                        $table['row_count'] = $row['row_count'];
                        break;
                    }
                }
                $tablesWithRows[] = $table;
            }

            // log the action
            $this->logManager->log(
                name: 'database-manager',
                message: 'get tables list',
                level: LogManager::LEVEL_NOTICE
            );

            return $tablesWithRows;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error to get tables list: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return null;
    }

    /**
     * Check if a table exists in a specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return bool True if the table exists, false otherwise
     */
    public function isTableExists(string $dbName, string $tableName): bool
    {
        $sql = "SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = :dbName 
                  AND table_name = :tableName";

        try {
            $stmt = $this->connection->executeQuery($sql, [
                'dbName' => $dbName,
                'tableName' => $tableName,
            ]);
            $count = $stmt->fetchOne();

            return $count > 0;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error checking if table exists: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return false;
    }

    /**
     * Get the number of rows in a specific table in a specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return int The number of rows in the table
     */
    public function getTableRowCount(string $dbName, string $tableName): int
    {
        if (!$this->isTableExists($dbName, $tableName)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$dbName}.{$tableName}";

        try {
            $stmt = $this->connection->executeQuery($sql);
            $rowCount = $stmt->fetchOne();

            // ensure $rowCount is of a valid type for intval()
            if (is_int($rowCount) || is_float($rowCount) || is_string($rowCount) || is_bool($rowCount) || is_null($rowCount)) {
                return intval($rowCount);
            } else {
                $this->errorManager->handleError(
                    message: 'error retrieving row count from table: invalid type',
                    code: Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error retrieving row count from table: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return 0;
    }

    /**
     * Get data from a specific table in a specific database with pagination
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     * @param int $page The page number (1-based index)
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return array<mixed> The data from the table for the specified page
     */
    public function getTableData(string $dbName, string $tableName, int $page = 1): ?array
    {
        if (!$this->isTableExists($dbName, $tableName)) {
            $this->errorManager->handleError(
                message: 'table. ' . $tableName . ' not found in database: ' . $dbName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // get the number of rows in the table
        $pageLimit = $this->appUtil->getPageLimiter();

        // calculate the offset for pagination
        $offset = ($page - 1) * $pageLimit;

        // ensure the offset is non-negative
        $offset = max($offset, 0);

        // build the SQL query
        $sql = "SELECT * FROM {$dbName}.{$tableName} LIMIT :offset, :pageSize";

        try {
            $stmt = $this->connection->executeQuery($sql, [
                'offset' => $offset,
                'pageSize' => $pageLimit
            ], [
                'offset' => \Doctrine\DBAL\ParameterType::INTEGER,
                'pageSize' => \Doctrine\DBAL\ParameterType::INTEGER
            ]);

            // log the action
            $this->logManager->log(
                name: 'database-manager',
                message: 'get table: ' . $tableName . ' data',
                level: LogManager::LEVEL_NOTICE
            );

            return $stmt->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error retrieving data from table: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return null;
    }

    /**
     * Get the last page number for a specific table in a specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return int The last page number
     */
    public function getLastPageNumber(string $dbName, string $tableName): int
    {
        if (!$this->isTableExists($dbName, $tableName)) {
            $this->errorManager->handleError(
                message: 'table ' . $tableName . ' not found in database: ' . $dbName,
                code: Response::HTTP_NOT_FOUND
            );
        }

        // get the number of rows in the table
        $pageLimit = $this->appUtil->getPageLimiter();

        // build the SQL query to get the total number of rows
        $sql = "SELECT COUNT(*) AS total_rows FROM {$dbName}.{$tableName}";

        try {
            $stmt = $this->connection->executeQuery($sql);
            $result = $stmt->fetchAssociative();

            if (!$result || !isset($result['total_rows'])) {
                throw new \RuntimeException('Failed to retrieve the total row count.');
            }

            $totalRows = $result['total_rows'];

            // calculate the total number of pages
            $totalPages = (int) ceil($totalRows / $pageLimit);

            // return the last page number
            return max($totalPages, 1);
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error retrieving the total row count: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return 1; // default to page 1 if there is an error
    }

    /**
     * Get the list of columns in a specific table in a specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return array<int,array<string,mixed>> The list of columns
     */
    public function getColumnsList(string $dbName, string $tableName): array
    {
        $sql = "SELECT 
                    COLUMN_NAME, 
                    COLUMN_TYPE, 
                    IS_NULLABLE, 
                    COLUMN_KEY, 
                    COLUMN_DEFAULT, 
                    EXTRA 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = :dbName AND TABLE_NAME = :tableName
                ORDER BY ORDINAL_POSITION";

        try {
            $stmt = $this->connection->executeQuery($sql, [
                'dbName' => $dbName,
                'tableName' => $tableName,
            ]);
            $columns = $stmt->fetchAllAssociative();
            return $columns;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'Error retrieving columns from table: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return [];
    }

    /**
     * Get a row from a specific table in a specific database by ID
     *
     * @param string $databaseName The name of the database
     * @param string $tableName The name of the table
     * @param int $id The ID of the row to retrieve
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return array<mixed>|null The row data or null if not found
     */
    public function getRowById(string $databaseName, string $tableName, int $id): ?array
    {
        $sql = 'SELECT * FROM ' . $databaseName . '.' . $tableName . ' WHERE id = :id';

        try {
            $stmt = $this->connection->executeQuery($sql, ['id' => $id]);
            $row = $stmt->fetchAssociative();

            // check if the row exists
            if (!$row) {
                $this->errorManager->handleError(
                    message: 'error retrieving row id: ' . $id . ' in table: ' . $tableName . ' in database: ' . $databaseName . ' row not found',
                    code: Response::HTTP_NOT_FOUND
                );
            }

            return $row ?: null;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error retrieving row: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return null;
    }

    /**
     * Check if a record with the given ID exists in the specified table and database.
     *
     * @param string $databaseName The name of the database.
     * @param string $tableName The name of the table.
     * @param int|string $id The ID to check for.
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return bool True if the record exists, false otherwise.
     */
    public function doesRecordExist(string $databaseName, string $tableName, $id): bool
    {
        $sql = sprintf(
            "SELECT COUNT(*) AS count FROM %s.%s WHERE id = :id",
            $this->connection->quoteIdentifier($databaseName),
            $this->connection->quoteIdentifier($tableName)
        );

        try {
            /** @var array<string,int> $result */
            $result = $this->connection->fetchAssociative($sql, ['id' => $id]);

            return (int) $result['count'] > 0;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error checking if record exists: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return false;
    }

    /**
     * Add row to table
     *
     * @param array<mixed> $formData The submitted form data
     * @param string $databaseName The name of the database
     * @param string $tableName The name of the table
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return void
     */
    public function addRowToTable(array $formData, string $databaseName, string $tableName): void
    {
        // unset database and table name from form data
        unset($formData['database']);
        unset($formData['table']);

        try {
            $columnsList = array_keys($formData);
            $placeholders = array_map(fn($column) => ':' . $column, $columnsList);
            $sql = sprintf(
                'INSERT INTO %s.%s (%s) VALUES (%s)',
                $databaseName,
                $tableName,
                implode(',', $columnsList),
                implode(',', $placeholders)
            );

            // execute the query
            $this->connection->executeQuery($sql, $formData);

            // log the action
            $this->logManager->log(
                name: 'database-manager',
                message: 'add row to table: ' . $tableName,
                level: LogManager::LEVEL_NOTICE
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error adding row: ' . $e->getMessage() . ' to table: ' . $tableName,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update a row in a specific table in a specific database
     *
     * @param array<mixed> $formData The submitted form data
     * @param string $databaseName The name of the database
     * @param string $tableName The name of the table
     * @param int $id The ID of the row to update
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return void
     */
    public function updateRowById(array $formData, string $databaseName, string $tableName, int $id): void
    {
        // unset unnecessary keys from form data
        unset($formData['database'], $formData['table'], $formData['page']);

        try {
            // Create the list of column placeholders for the update query
            $columnsList = array_keys($formData);
            $setClause = implode(', ', array_map(fn($column) => "$column = :$column", $columnsList));

            // Build the SQL query for updating the row
            $sql = sprintf(
                'UPDATE %s.%s SET %s WHERE id = :id',
                $databaseName,
                $tableName,
                $setClause
            );

            // Execute the query with the data
            $formData['id'] = $id;
            $this->connection->executeQuery($sql, $formData);

            // Log the update action
            $this->logManager->log(
                name: 'database-manager',
                message: "Updated row with ID: $id in table: $tableName in database: $databaseName",
                level: LogManager::LEVEL_NOTICE
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: "Error updating row: " . $e->getMessage() . " in table: $tableName in database: $databaseName",
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a row from a specific table in a specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     * @param int $id The ID of the row to delete
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return bool True if the row was deleted successfully, false otherwise
     */
    public function deleteRowById(string $dbName, string $tableName, int $id): bool
    {
        // sql query to delete a row with the specific ID
        $sql = 'DELETE FROM ' . $dbName . '.' . $tableName . ' WHERE id = :id';

        try {
            // execute the delete query
            $this->connection->executeStatement($sql, [
                'id' => $id
            ]);

            // log the event
            $this->logManager->log(
                name: 'database-manager',
                message: "deleted row with ID: $id from table: $tableName in database: $dbName",
                level: LogManager::LEVEL_NOTICE
            );

            return true;
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error deleting row: ' . $e->getMessage() . ' from table: ' . $tableName . ' in database: ' . $dbName,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );

            return false;
        }
    }

    /**
     * Truncate a table in a specific database
     *
     * @param string $dbName The name of the database
     * @param string $tableName The name of the table
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return void
     */
    public function tableTruncate(string $dbName, string $tableName): void
    {
        $sql = 'TRUNCATE TABLE ' . $dbName . '.' . $tableName;

        try {
            $this->connection->executeStatement($sql);

            // log the event
            $this->logManager->log(
                name: 'database-manager',
                message: "truncated table: $tableName in database: $dbName",
                level: LogManager::LEVEL_CRITICAL
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error truncating table: ' . $e->getMessage() . ' in database: ' . $dbName,
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get database dump
     *
     * @param string $dbName The name of the database
     * @param bool $plain Whether to return the dump in plain text format
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return string The database dump
     */
    public function getDatabaseDump(string $dbName, bool $plain = false): string
    {
        $tables = $this->connection->fetchAllAssociative('SHOW TABLES FROM ' . $this->connection->quoteIdentifier($dbName));

        $dump = '';

        // dump header
        $dump .= '-- Database: ' . $dbName . " dumped at: " . date('Y-m-d H:i:s') . " with admin-suite\n\n";

        // set character
        $dump .= 'SET NAMES utf8mb4;' . "\n\n";

        // drop and create database
        $dump .= 'DROP DATABASE IF EXISTS ' . $dbName . ";\n";
        $dump .= 'CREATE DATABASE ' . $dbName . ";\n";
        $dump .= 'USE ' . $dbName . ";\n\n";

        try {
            foreach ($tables as $table) {
                /** @var string $tableName */
                $tableName = $table['Tables_in_' . $dbName];
                $createTableStmt = $this->connection->fetchAssociative('SHOW CREATE TABLE ' . $dbName . '.' . $this->connection->quoteIdentifier($tableName));

                if (!$createTableStmt) {
                    $this->errorManager->handleError(
                        message: 'error dumping database: table not found',
                        code: Response::HTTP_INTERNAL_SERVER_ERROR
                    );
                    return '';
                }

                $dump .= $createTableStmt['Create Table'] . ";\n\n";

                if (!$plain) {
                    $rows = $this->connection->fetchAllAssociative('SELECT * FROM ' . $dbName . '.' . $this->connection->quoteIdentifier($tableName));
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $value) {

                            /** @var string $value */
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = $this->connection->quote($value);
                            }
                        }
                        $values = implode(', ', $values);
                        $dump .= 'INSERT INTO ' . $this->connection->quoteIdentifier($tableName) . ' VALUES (' . $values . ");\n";
                    }
                    $dump .= "\n";
                }
            }

            // log the action
            $this->logManager->log(
                name: 'database-manager',
                message: 'get database dump',
                level: LogManager::LEVEL_CRITICAL
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error dumping database: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $dump;
    }

    /**
     * Execute a query
     *
     * @param string $query The query to execute
     *
     * @throws \Exception If an error occurs while executing the query
     *
     * @return string The output of the query
     */
    public function executeQuery(string $query): string
    {
        try {
            // create a new PDO instance
            $pdo = new PDO(
                'mysql:host=' . $_ENV['DATABASE_HOST'] . ';dbname=' . $_ENV['DATABASE_NAME'],
                $_ENV['DATABASE_USERNAME'],
                $_ENV['DATABASE_PASSWORD'],
                [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // execute multiple queries
            $result = '';
            $queries = $this->splitQueries($query); // split the query

            foreach ($queries as $q) {
                $q = trim($q);
                if (!empty($q)) {
                    // execute the query
                    $stmt = $pdo->query($q);

                    // check statement
                    if ($stmt == false) {
                        $this->errorManager->handleError(
                            message: 'error executing query statement: ' . $q,
                            code: Response::HTTP_INTERNAL_SERVER_ERROR
                        );

                        return '';
                    }

                    // fetch all results
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // format results as a string
                    if ($data) {
                        foreach ($data as $row) {
                            $result .= implode("\t", $row) . "\n";
                        }
                    }
                }
            }

            // default output
            if (empty($result)) {
                $result = 'Query executed successfully';
            }

            // log the action
            $this->logManager->log(
                name: 'database-manager',
                message: 'Executed query',
                level: LogManager::LEVEL_NOTICE
            );

            return $result;
        } catch (PDOException $e) {
            // return the error message
            return $e->getMessage();
        }
    }

    /**
     * Split a SQL query into multiple queries
     *
     * @param string $sql The SQL query to split
     *
     * @return array<string> The array of queries
     */
    public function splitQueries(string $sql): array
    {
        $queries = [];
        $currentQuery = '';
        $insideString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if ($char === '\\') {
                $escaped = !$escaped;
            } elseif ($char === '\'' || $char === '"') {
                if (!$escaped) {
                    $insideString = !$insideString;
                }
                $escaped = false;
            } elseif ($char === ';' && !$insideString) {
                $queries[] = $currentQuery;
                $currentQuery = '';
                continue;
            }

            $currentQuery .= $char;
        }

        // add the last query if exists
        if (!empty($currentQuery)) {
            $queries[] = $currentQuery;
        }

        return $queries;
    }
}
