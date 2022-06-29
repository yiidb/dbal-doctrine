<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Query;

use Doctrine\DBAL\Exception as DoctrineException;
use YiiDb\DBAL\Contracts\CommandTypeEnum;
use YiiDb\DBAL\Contracts\Query\ConditionBuilderInterface;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Doctrine\Command;
use YiiDb\DBAL\Doctrine\Connection;
use YiiDb\DBAL\Doctrine\Query\Expressions\ExpressionBuilder;
use YiiDb\DBAL\Doctrine\Result;
use YiiDb\DBAL\EmulatedResult;
use YiiDb\DBAL\Exceptions\ConnectionRequiredException;
use YiiDb\DBAL\Query\BaseSelectQuery;

/**
 * @method ExpressionBuilder expr()
 */
final class SelectQuery extends BaseSelectQuery
{
    private readonly ?Connection $conn;

    /**
     * @param array<string|ExpressionInterface|array<string|ExpressionInterface>> $columns
     * @internal
     */
    public function __construct(Query $query, array $columns, ConditionBuilderInterface $condBuilder)
    {
        $this->conn = $query->getConnection();

        parent::__construct($query, $columns, $condBuilder);
    }

    public function createCommand(Connection $conn = null): Command
    {
        return new Command($this->toExpr(), $conn ?? $this->conn, CommandTypeEnum::Query);
    }

    /**
     * @throws DoctrineException
     */
    public function get(Connection $conn = null): Result|EmulatedResult
    {
        if ($this->emulateExecution) {
            return new EmulatedResult();
        }

        $conn = $conn ?? $this->getRealConnection();
        $expr = $this->toExpr();

        $result = $conn->executeQuery((string)$expr, $expr->getParams());

        if ($indexColumn = $this->indexColumn) {
            $result->setIndexColumn($indexColumn);
        }

        return $result;
    }

    public function getConnection(): ?Connection
    {
        return $this->conn;
    }

    public function getRealConnection(): Connection
    {
        return $this->getConnection() ?? throw new ConnectionRequiredException();
    }
}
