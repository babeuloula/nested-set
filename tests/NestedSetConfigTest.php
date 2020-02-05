<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\Test\NestedSet;

use BaBeuloula\NestedSet\NestedSetConfig;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;

class NestedSetConfigTest extends TestCase
{
    /**
     * @covers NestedSetConfig::__construct
     * @covers NestedSetConfig::getTableName
     * @covers NestedSetConfig::getNodeColumn
     * @covers NestedSetConfig::getLeftColumn
     * @covers NestedSetConfig::getRightColumn
     * @covers NestedSetConfig::getFetchMode
     */
    public function testConfigWithDefaultValues(): void
    {
        $config = new NestedSetConfig(DatabaseTest::TABLE_NAME);

        static::assertSame(DatabaseTest::TABLE_NAME, $config->getTableName());
        static::assertSame('id', $config->getNodeColumn());
        static::assertSame('left_count', $config->getLeftColumn());
        static::assertSame('right_count', $config->getRightColumn());
        static::assertSame(FetchMode::ASSOCIATIVE, $config->getFetchMode());
    }

    /**
     * @covers NestedSetConfig::__construct
     * @covers NestedSetConfig::getTableName
     * @covers NestedSetConfig::getNodeColumn
     * @covers NestedSetConfig::getLeftColumn
     * @covers NestedSetConfig::getRightColumn
     * @covers NestedSetConfig::getFetchMode
     */
    public function testConfigWithAllValuesConstructor(): void
    {
        $config = new NestedSetConfig(
            DatabaseTest::TABLE_NAME,
            DatabaseTest::NODE_COLUMN,
            DatabaseTest::LEFT_COLUMN,
            DatabaseTest::RIGHT_COLUMN,
            DatabaseTest::FETCH_MODE
        );

        static::assertSame(DatabaseTest::TABLE_NAME, $config->getTableName());
        static::assertSame(DatabaseTest::NODE_COLUMN, $config->getNodeColumn());
        static::assertSame(DatabaseTest::LEFT_COLUMN, $config->getLeftColumn());
        static::assertSame(DatabaseTest::RIGHT_COLUMN, $config->getRightColumn());
        static::assertSame(DatabaseTest::FETCH_MODE, $config->getFetchMode());
    }

    /**
     * @covers NestedSetConfig::__construct
     * @covers NestedSetConfig::getTableName
     * @covers NestedSetConfig::setTableName
     * @covers NestedSetConfig::getNodeColumn
     * @covers NestedSetConfig::setNodeColumn
     * @covers NestedSetConfig::getLeftColumn
     * @covers NestedSetConfig::setLeftColumn
     * @covers NestedSetConfig::getRightColumn
     * @covers NestedSetConfig::setRightColumn
     * @covers NestedSetConfig::getFetchMode
     * @covers NestedSetConfig::setFetchMode
     */
    public function testConfigWithSetters(): void
    {
        $config = (new NestedSetConfig('foo'))
            ->setTableName(DatabaseTest::TABLE_NAME)
            ->setNodeColumn(DatabaseTest::NODE_COLUMN)
            ->setLeftColumn(DatabaseTest::LEFT_COLUMN)
            ->setRightColumn(DatabaseTest::RIGHT_COLUMN)
            ->setFetchMode(DatabaseTest::FETCH_MODE)
        ;

        static::assertSame(DatabaseTest::TABLE_NAME, $config->getTableName());
        static::assertSame(DatabaseTest::NODE_COLUMN, $config->getNodeColumn());
        static::assertSame(DatabaseTest::LEFT_COLUMN, $config->getLeftColumn());
        static::assertSame(DatabaseTest::RIGHT_COLUMN, $config->getRightColumn());
        static::assertSame(DatabaseTest::FETCH_MODE, $config->getFetchMode());
    }
}
