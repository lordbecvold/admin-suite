<?php

namespace App\Manager;

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
                level: 3
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
                level: 3
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
                level: 3
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
                level: 3
            );
        } catch (\Exception $e) {
            $this->errorManager->handleError(
                message: 'error adding row: ' . $e->getMessage() . ' to table: ' . $tableName,
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
        $sql = "DELETE FROM {$dbName}.{$tableName} WHERE id = :id";

        try {
            // execute the delete query
            $this->connection->executeStatement($sql, [
                'id' => $id
            ]);

            // log the event
            $this->logManager->log(
                name: 'database-manager',
                message: "Deleted row with ID: $id from table: $tableName in database: $dbName",
                level: 3
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
}
