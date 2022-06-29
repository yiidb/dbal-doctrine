<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Query;

use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use YiiDb\DBAL\Contracts\CommandInterface;
use YiiDb\DBAL\Contracts\CommandTypeEnum;
use YiiDb\DBAL\Contracts\Query\ConditionBuilderInterface;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Doctrine\Command;
use YiiDb\DBAL\Doctrine\Connection;
use YiiDb\DBAL\Doctrine\Parameter\Parameter;
use YiiDb\DBAL\Doctrine\Query\Expressions\ExpressionBuilder;
use YiiDb\DBAL\Exceptions\ConnectionRequiredException;
use YiiDb\DBAL\Query\BaseUpdateQuery;
use YiiDb\DBAL\Query\TableName;

final class UpdateQuery extends BaseUpdateQuery
{
    private readonly ?Connection $conn;
    private ?ExpressionBuilder $exprBuilder = null;
    private readonly Query $query;

    /**
     * @internal
     */
    public function __construct(
        Query $query,
        string|TableName|ExpressionInterface $table,
        ConditionBuilderInterface $condBuilder
    ) {
        $this->conn = $query->getConnection();
        $this->query = $query;

        parent::__construct($table, $condBuilder);
    }

    public function createCommand(Connection $conn = null): CommandInterface
    {
        return new Command($this->toExpr(), $conn ?? $this->conn, CommandTypeEnum::Statement);
    }

    /**
     * @throws DoctrineException
     */
    public function execute(Connection $conn = null): int|string
    {
        if ($this->emulateExecution) {
            return 0;
        }

        $conn = $conn ?? $this->getRealConnection();
        $expr = $this->toExpr();

        return $conn->executeStatement((string)$expr, $expr->getParams());
    }

    public function expr(): ExpressionBuilder
    {
        return $this->exprBuilder ?? ($this->exprBuilder = $this->query->expr());
    }

    public function getConnection(): ?Connection
    {
        return $this->conn;
    }

    public function getRealConnection(): Connection
    {
        return $this->getConnection() ?? throw new ConnectionRequiredException();
    }

    /**
     * @psalm-param ParameterType::*|Parameter::TYPE_*|string|Type|null $valueType
     */
    public function set(string $column, mixed $value, int|string|Type $valueType = null): static
    {
        return parent::set($column, $valueType === null ? $value : new Parameter($value, $valueType));
    }
}
