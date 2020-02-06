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
                'dbname' => getenv('MYSQL_DATABASE'),
                'user' => getenv('MYSQL_USER'),
                'password' => getenv('MYSQL_PASSWORD'),
                'host' => getenv('MYSQL_DOCKER_HOST'),
                'driver' => 'pdo_mysql',
            ]
        );
    }

    protected function resetDatabase(): void
    {
        $this
            ->connection
            ->prepare(
                '
DROP TABLE IF EXISTS `' . static::TABLE_NAME . '`;

CREATE TABLE IF NOT EXISTS `' . static::TABLE_NAME . '` (
  `' . static::NODE_COLUMN . '` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `' . static::NAME_COLUMN . '` VARCHAR(255) NULL,
  `' . static::LEFT_COLUMN . '` INT UNSIGNED NULL,
  `' . static::RIGHT_COLUMN . '` INT UNSIGNED NULL,
  PRIMARY KEY (`' . static::NODE_COLUMN . '`),
  UNIQUE INDEX `id_UNIQUE` (`' . static::NODE_COLUMN . '` ASC),
  UNIQUE INDEX `left_count_UNIQUE` (`' . static::LEFT_COLUMN . '` ASC),
  UNIQUE INDEX `right_count_UNIQUE` (`' . static::RIGHT_COLUMN . '` ASC))
ENGINE = InnoDB;
'
            )
            ->execute()
        ;
    }
}
