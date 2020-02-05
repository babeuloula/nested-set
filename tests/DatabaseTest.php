<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\Test\NestedSet;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTest extends TestCase
{
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

        $this
            ->connection
            ->prepare(
                <<<SQL
DROP TABLE IF EXISTS `:tableName`;

CREATE TABLE IF NOT EXISTS `:tableName` (
  `:nodeColumn` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NULL,
  `:leftColumn` INT NULL,
  `:rightColumn` INT NULL,
  PRIMARY KEY (:nodeColumn),
  UNIQUE INDEX `id_UNIQUE` (`:nodeColumn` ASC),
  UNIQUE INDEX `left_count_UNIQUE` (`:leftColumn` ASC),
  UNIQUE INDEX `right_count_UNIQUE` (`:rightColumn` ASC))
ENGINE = InnoDB;
SQL
            )
            ->execute(
                [
                    'tableName' => 'nested_set',
                    'nodeColumn' => 'id',
                    'leftColumn' => 'left_count',
                    'rightColumn' => 'right_count',
                ]
            )
        ;
    }
}
