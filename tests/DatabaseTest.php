<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\Test\NestedSet;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTest extends TestCase
{
    /** @var string */
    public const TABLE_NAME = 'nested_set';

    /** @var string */
    public const NODE_COLUMN = 'primary_id';

    /** @var string */
    public const NAME_COLUMN = 'name';

    /** @var string */
    public const LEFT_COLUMN = 'left';

    /** @var string */
    public const RIGHT_COLUMN = 'right';

    /** @var int */
    public const FETCH_MODE = FetchMode::STANDARD_OBJECT;

    /** @var Connection */
    protected $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = DriverManager::getConnection(
            [
                'dbname' => 'nested_set',
                'user' => 'nested_set',
                'password' => 'nested_set',
                'host' => 'mysql',
                'driver' => 'pdo_mysql',
            ]
        );
    }
}
