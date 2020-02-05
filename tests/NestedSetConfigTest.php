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
    protected const TABLE_NAME = 'foo_table';
    protected const NODE_COLUMN = 'primary_id';
    protected const LEFT_COLUMN = 'left';
    protected const RIGHT_COLUMN = 'right';
    protected const FETCH_MODE = FetchMode::STANDARD_OBJECT;

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
        $config = new NestedSetConfig(static::TABLE_NAME);

        static::assertSame(static::TABLE_NAME, $config->getTableName());
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
            static::TABLE_NAME,
            static::NODE_COLUMN,
            static::LEFT_COLUMN,
            static::RIGHT_COLUMN,
            static::FETCH_MODE
        );

        static::assertSame(static::TABLE_NAME, $config->getTableName());
        static::assertSame(static::NODE_COLUMN, $config->getNodeColumn());
        static::assertSame(static::LEFT_COLUMN, $config->getLeftColumn());
        static::assertSame(static::RIGHT_COLUMN, $config->getRightColumn());
        static::assertSame(static::FETCH_MODE, $config->getFetchMode());
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
            ->setTableName(static::TABLE_NAME)
            ->setNodeColumn(static::NODE_COLUMN)
            ->setLeftColumn(static::LEFT_COLUMN)
            ->setRightColumn(static::RIGHT_COLUMN)
            ->setFetchMode(static::FETCH_MODE)
        ;

        static::assertSame(static::TABLE_NAME, $config->getTableName());
        static::assertSame(static::NODE_COLUMN, $config->getNodeColumn());
        static::assertSame(static::LEFT_COLUMN, $config->getLeftColumn());
        static::assertSame(static::RIGHT_COLUMN, $config->getRightColumn());
        static::assertSame(static::FETCH_MODE, $config->getFetchMode());
    }
}
