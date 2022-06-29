<?php

declare(strict_types=1);

namespace YiiDb\DBAL\Doctrine\Query;

use YiiDb\DBAL\Contracts\Query\ConditionBuilderInterface;
use YiiDb\DBAL\Contracts\Query\Expressions\ExpressionInterface;
use YiiDb\DBAL\Contracts\Query\QueryInterface;
use YiiDb\DBAL\Doctrine\Connection;
use YiiDb\DBAL\Doctrine\Query\Expressions\ExpressionBuilder;
use YiiDb\DBAL\Doctrine\Query\Expressions\ParameterBuilder;
use YiiDb\DBAL\Query\Expressions\SimpleConditionBuilder;
use YiiDb\DBAL\Query\TableName;

class Query implements QueryInterface
{
    private readonly ConditionBuilderInterface $condBuilder;
    private readonly ?Connection $conn;
    private ?ExpressionBuilder $exprBuilder = null;
    private readonly ParameterBuilder $paramBuilder;

    public function __construct(
        ?Connection $conn = null,
        bool $useNamedParams = null,
        bool $inlineParams = null,
        ConditionBuilderInterface $condBuilder = null
    ) {
        $this->conn = $conn;
        $this->condBuilder = $condBuilder ?? $conn?->getConditionBuilder() ?? new SimpleConditionBuilder();

        $this->paramBuilder = new ParameterBuilder(
            $conn?->dConn,
            $useNamedParams ?? $conn?->useNamedParams ?? false,
            $inlineParams ?? $conn?->inlineParams ?? false
        );
    }

    public function delete(string|TableName|ExpressionInterface $table): DeleteQuery
    {
        return new DeleteQuery($this, $table, $this->condBuilder);
    }

    public function expr(): ExpressionBuilder
    {
        return $this->exprBuilder ?? ($this->exprBuilder = new ExpressionBuilder($this->conn, $this->paramBuilder));
    }

    public function getConditionBuilder(): ConditionBuilderInterface
    {
        return $this->condBuilder;
    }

    public function getConnection(): ?Connection
    {
        return $this->conn;
    }

    public function getParameterBuilder(): ParameterBuilder
    {
        return $this->paramBuilder;
    }

    public function insert(string|TableName|ExpressionInterface $table): InsertQuery
    {
        return new InsertQuery($this, $table);
    }

    public function select(string|ExpressionInterface|array ...$columns): SelectQuery
    {
        return new SelectQuery($this, $columns, $this->condBuilder);
    }

    public function selectRaw(string $rawString, array $params = [], bool $wrap = true): SelectQuery
    {
        return $this->select()->selectRaw($rawString, $params, $wrap);
    }

    public function selectString(string $columns): SelectQuery
    {
        return $this->select()->selectString($columns);
    }

    public function update(string|TableName|ExpressionInterface $table): UpdateQuery
    {
        return new UpdateQuery($this, $table, $this->condBuilder);
    }
}
