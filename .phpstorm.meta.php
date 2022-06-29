<?php

namespace PHPSTORM_META {

    registerArgumentsSet(
        'doctrineDbalParameterType',
        \Doctrine\DBAL\ParameterType::NULL,
        \Doctrine\DBAL\ParameterType::INTEGER,
        \Doctrine\DBAL\ParameterType::STRING,
        \Doctrine\DBAL\ParameterType::LARGE_OBJECT,
        \Doctrine\DBAL\ParameterType::BOOLEAN,
        \Doctrine\DBAL\ParameterType::BINARY,
        \Doctrine\DBAL\ParameterType::ASCII
    );

    registerArgumentsSet(
        'yiiDbalExpressionBuilderOperator',
        \YiiDb\DBAL\Expressions\Operator::EQ,
        \YiiDb\DBAL\Expressions\Operator::NEQ,
        \YiiDb\DBAL\Expressions\Operator::LT,
        \YiiDb\DBAL\Expressions\Operator::LTE,
        \YiiDb\DBAL\Expressions\Operator::GT,
        \YiiDb\DBAL\Expressions\Operator::GTE
    );

    expectedArguments(
        \YiiDb\DBAL\Parameter\Parameter::__construct(),
        1,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::createParameter(),
        1,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::comparison(),
        1,
        argumentsSet('yiiDbalExpressionBuilderOperator')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::comparison(),
        3,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::comparisonColumns(),
        1,
        argumentsSet('yiiDbalExpressionBuilderOperator')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::eq(),
        2,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::neq(),
        2,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::lt(),
        2,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::lte(),
        2,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::gt(),
        2,
        argumentsSet('doctrineDbalParameterType')
    );

    expectedArguments(
        \YiiDb\DBAL\Expressions\ExpressionBuilder::gte(),
        2,
        argumentsSet('doctrineDbalParameterType')
    );
}
