<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use YiiDb\DBAL\Doctrine\Types\BigIntType;
use YiiDb\DBAL\Doctrine\Types\BinaryType;
use YiiDb\DBAL\Doctrine\Types\EnumType;

return [
    'yii/dbal' => [
        'types' => [
            'addTypes' => [
                'enum' => EnumType::class,
            ],
            'overrideTypes' => [
                Types::BINARY => BinaryType::class,
                // ВАЖНО!!!
                // В PDO MySQL даже если использовать строковые BigInt, проблема с числами больше PHP_INT_MAX остаётся.
                // Для решения этой проблемы необходимо установить PDO::ATTR_EMULATE_PREPARES = false.
                Types::BIGINT => BigIntType::class,
            ]
        ],
        'connectionManager' => [
            'defaultConnection' => 'default',
            'connections' => [
                'default' => [
                    /** PSR-6 */
                    'queryCache' => null,
                    /** SQL query logger. Definition of Psr\Log\LoggerInterface */
                    'queryLogger' => null,
                    'useNamedParameters' => false,
                    'inlineParameters' => false,
                    'realtimeConditionBuilding' => false,
                    'params' => [
                        /**
                         * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#configuration
                         * 'url' => 'mysql://user:secret@localhost/mydb',
                         */
                    ],
                ],
            ],
        ],
    ],
];
