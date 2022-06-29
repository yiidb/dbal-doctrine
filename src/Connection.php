<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\TransactionIsolationLevel;
use YiiDb\DBAL\BaseConnection;
use YiiDb\DBAL\Contracts\Query\ConditionBuilderInterface;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Doctrine\Parameter\ParamCollection;
use YiiDb\DBAL\Doctrine\Query\Query;
use YiiDb\DBAL\Exceptions\EmptyWhereNotAllowedException;
use YiiDb\DBAL\Exceptions\InvalidArgumentException;
use YiiDb\DBAL\Query\Expressions\Expression;
use YiiDb\DBAL\Query\TableName;

use function count;
use function is_array;

class Connection extends BaseConnection
{
    public readonly DoctrineConnection $dConn;

    public function __construct(
        DoctrineConnection $dConn,
        bool $useNamedParams = false,
        bool $inlineParams = false,
        bool $realtimeCondBuilding = false,
        ConditionBuilderInterface $condBuilder = null
    ) {
        $this->dConn = $dConn;

        parent::__construct($useNamedParams, $inlineParams, $realtimeCondBuilding, $condBuilder);
    }

    /**
     * @throws DoctrineException
     */
    public function batchInsert(string|TableName $table, array $columns, array $rows): int|string
    {
        if (empty($rows)) {
            return 0;
        }

        $table = $table instanceof TableName ? TableName::getQuoted($table, $this) : $this->wrap($table);
        $columnCount = count($columns);
        $columnsPart = implode(',', array_map(
            static fn (string|int $v): string => $this->wrapSingle((string)$v),
            $columns
        ));

        $placeholders = rtrim(str_repeat('?,', $columnCount), ',');
        $valuesPart = rtrim(str_repeat("($placeholders),", count($rows)), ',');

        $params = new ParamCollection();
        foreach ($rows as $rowId => $row) {
            if (count($row) !== $columnCount) {
                throw new InvalidArgumentException(
                    sprintf('The number of values in row "%s" is different from the number of columns.', $rowId)
                );
            }
            $params->addIndexValues($row);
        }

        $sql = "INSERT INTO $table ($columnsPart) VALUES $valuesPart";

        return $this->dConn->executeStatement($sql, $params->getParams(), $params->getTypes());
    }

    /**
     * @throws DoctrineException
     */
    public function beginTransaction(): void
    {
        $this->dConn->beginTransaction();
    }

    /**
     * @throws DoctrineException
     */
    public function commit(): void
    {
        $this->dConn->commit();
    }

    public function createCommand(string|ExpressionInterface $sql): Command
    {
        return new Command($sql, $this);
    }

    /**
     * @throws DoctrineException
     */
    public function createSavepoint(string $savepoint): void
    {
        $this->dConn->createSavepoint($savepoint);
    }

    /**
     * @throws DoctrineException
     */
    public function createSchemaManager(): AbstractSchemaManager
    {
        return $this->dConn->createSchemaManager();
    }

    /**
     * @throws DoctrineException
     */
    public function delete(string|TableName $table, array $criteria, bool $allowEmptyCriteria = false): int|string
    {
        $table = $table instanceof TableName ? TableName::getQuoted($table, $this) : $this->wrap($table);

        if (empty($criteria)) {
            !$allowEmptyCriteria && throw new EmptyWhereNotAllowedException();

            $sql = "DELETE FROM $table";

            return $this->dConn->executeStatement($sql);
        }

        $params = new ParamCollection();

        $wherePart = $this->prepareConditions($criteria, $params);

        $sql = "DELETE FROM $table WHERE $wherePart";

        return $this->dConn->executeStatement($sql, $params->getParams(), $params->getTypes());
    }

    /**
     * @throws DoctrineException
     */
    public function executeQuery(string $sql, array $params = null): Result
    {
        if (empty($params)) {
            return new Result($this->dConn->executeQuery($sql));
        }

        $dParams = (new ParamCollection())->setValues($params);

        return new Result(
            $this->dConn->executeQuery($sql, $dParams->getParams(), $dParams->getTypes())
        );
    }

    /**
     * @throws DoctrineException
     */
    public function executeStatement(string $sql, array $params = null): int|string
    {
        if (empty($params)) {
            return $this->dConn->executeStatement($sql);
        }

        $dParams = (new ParamCollection())->setValues($params);

        return $this->dConn->executeStatement($sql, $dParams->getParams(), $dParams->getTypes());
    }

    /**
     * @throws DoctrineException
     */
    public function getDatabase(): ?string
    {
        return $this->dConn->getDatabase();
    }

    /**
     * @throws DoctrineException
     */
    public function getLastInsertId(): int|string
    {
        return $this->dConn->lastInsertId();
    }

    /**
     * @throws DoctrineException
     */
    public function getTransactionIsolation(): int
    {
        return $this->dConn->getTransactionIsolation();
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->dConn->getTransactionNestingLevel();
    }

    /**
     * @throws DoctrineException
     */
    public function insert(string|TableName $table, array $values): int|string
    {
        $table = $table instanceof TableName ? TableName::getQuoted($table, $this) : $this->wrap($table);

        if (empty($values)) {
            return $this->dConn->executeStatement("INSERT INTO $table () VALUES ()");
        }

        $columnsPart = implode(',', array_map(
            static fn (string|int $v): string => $this->wrapSingle((string)$v),
            array_keys($values)
        ));
        $params = (new ParamCollection())->addIndexValues($values);
        $valuesPart = rtrim(str_repeat('?,', count($values)), ',');

        $sql = "INSERT INTO $table ($columnsPart) VALUES ($valuesPart)";

        return $this->dConn->executeStatement($sql, $params->getParams(), $params->getTypes());
    }

    public function isAutoCommit(): bool
    {
        return $this->dConn->isAutoCommit();
    }

    /**
     * @throws ConnectionException
     */
    public function isRollbackOnly(): bool
    {
        return $this->dConn->isRollbackOnly();
    }

    public function isTransactionActive(): bool
    {
        return $this->dConn->isTransactionActive();
    }

    public function query(bool $useNamedParams = null, bool $inlineParams = null): Query
    {
        return new Query($this, $useNamedParams, $inlineParams);
    }

    public function quote(string $value): string
    {
        return $this->dConn->quote($value);
    }

    /**
     * @throws DoctrineException
     */
    public function releaseSavepoint(string $savepoint): void
    {
        $this->dConn->releaseSavepoint($savepoint);
    }

    /**
     * @throws DoctrineException
     */
    public function rollBack(): void
    {
        $this->dConn->rollBack();
    }

    /**
     * @throws DoctrineException
     */
    public function rollbackSavepoint(string $savepoint): void
    {
        $this->dConn->rollbackSavepoint($savepoint);
    }

    /**
     * @throws DoctrineException
     */
    public function select(string $sql, array $params = null): Result
    {
        return $this->executeQuery($this->wrapSql($sql), $params);
    }

    /**
     * @throws ConnectionException
     * @throws DriverException
     */
    public function setAutoCommit(bool $autoCommit): void
    {
        $this->dConn->setAutoCommit($autoCommit);
    }

    /**
     * @throws ConnectionException
     */
    public function setRollbackOnly(): void
    {
        $this->dConn->setRollbackOnly();
    }

    /**
     * @psalm-param TransactionIsolationLevel::* $level The level to set.
     *
     * @throws DoctrineException
     */
    public function setTransactionIsolation(int $level): void
    {
        $this->dConn->setTransactionIsolation($level);
    }

    /**
     * @throws DoctrineException
     */
    public function update(
        string|TableName $table,
        array $values,
        array $criteria,
        bool $allowEmptyCriteria = false
    ): int|string {
        if (empty($values)) {
            return 0;
        }

        (empty($criteria) && !$allowEmptyCriteria) && throw new EmptyWhereNotAllowedException();

        $table = $table instanceof TableName ? TableName::getQuoted($table, $this) : $this->wrap($table);
        $columns = array_map(
            static fn (string|int $v): string => $this->wrapSingle((string)$v),
            array_keys($values)
        );
        $setPart = implode(' = ?, ', $columns) . ' = ?';

        $params = (new ParamCollection())->addIndexValues($values);

        $sql = "UPDATE $table SET $setPart";
        if ($wherePart = $this->prepareConditions($criteria, $params)) {
            $sql .= " WHERE $wherePart";
        }

        return $this->dConn->executeStatement($sql, $params->getParams(), $params->getTypes());
    }

    public function wrap(string $value): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->dConn->getDatabasePlatform()->quoteIdentifier($value);
    }

    public function wrapSingle(string $value): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->dConn->getDatabasePlatform()->quoteSingleIdentifier($value);
    }

    protected function prepareConditions(array $criteria, ParamCollection $params): string
    {
        $conditions = [];
        foreach ($criteria as $columnName => $value) {
            if ($value instanceof Expression) {
                $conditions[] = (string)$value;
                $params->addIndexValues($value->getParams());
                continue;
            }

            $columnName = $this->wrapSingle((string)$columnName);

            if ($value === null) {
                $conditions[] = "$columnName IS NULL";
                continue;
            }

            if (is_array($value)) {
                $placeholders = rtrim(str_repeat('?,', count($value)), ',');
                $conditions[] = "$columnName IN ($placeholders)";
                $params->addIndexValues($value);
                continue;
            }

            $conditions[] = "$columnName = ?";
            $params->addIndexValue($value);
        }

        return implode(' AND ', $conditions);
    }
}
