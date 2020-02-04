<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula;

use Doctrine\DBAL\Driver\Connection;

class NestedSetModel
{
    /** @var Connection */
    protected $connection;

    /** @var NestedSetModelConfig */
    protected $config;

    public function __construct(Connection $connection, NestedSetModelConfig $config)
    {
        $this->connection = $connection;
        $this->config = $config;
    }

    /**
     * @param mixed $primaryKeyValue
     * @param mixed $parentColumnValue
     *
     * @return \Generator<array|\stdClass>
     */
    public function getSiblings($primaryKeyValue, $parentColumnValue): \Generator
    {
        $statement = $this->connection->prepare(
            <<<SQL
SELECT child.*
FROM :tableName parent
JOIN :tableName child
    ON (child.:leftColumn > parent.:leftColumn AND child.:rightColumn < parent.:rightColumn
    AND child.:parentColumn = :parentColumnValue)
LEFT JOIN :tableName intermediate
    ON (intermediate.:leftColumn > parent.:leftColumn AND intermediate.:rightColumn < parent.:rightColumn
    AND child.:leftColumn > intermediate.:leftColumn AND child.:rightColumn < intermediate.:rightColumn
    AND intermediate.:parentColumn = :parentColumnValue)
WHERE intermediate.:primaryKey IS NULL
    AND parent.:primaryKey = :primaryKeyValue
    AND parent.:parentColumn = :parentColumnValue
ORDER BY child.:leftColumn;
SQL
        );
        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
                'parentColumn' => $this->config->getParentColumn(),
                'primaryKey' => $this->config->getPrimaryKey(),
                'primaryKeyValue' => $primaryKeyValue,
                'parentColumnValue' => $parentColumnValue,
            ]
        );

        yield $statement->fetch($this->config->getFetchMode());
    }

    /**
     * @param mixed $primaryKeyValue
     *
     * @return \Generator<array|\stdClass>
     */
    public function getAncestors($primaryKeyValue): \Generator
    {
        $statement = $this->connection->prepare(
            <<<SQL
SELECT parent.*
FROM :tableName parent
LEFT JOIN :tableName child
    ON (parent.:leftColumn < child.:leftColumn AND parent.:rightColumn > child.:rightColumn
    AND parent.:parentColumn = child.:parentColumn)
WHERE child.:primaryKey = :primaryKeyValue
ORDER BY parent.:leftColumn ASC;
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
                'parentColumn' => $this->config->getParentColumn(),
                'primaryKey' => $this->config->getPrimaryKey(),
                'primaryKeyValue' => $primaryKeyValue,
            ]
        );

        yield $statement->fetch($this->config->getFetchMode());
    }

    public function addNode(): void
    {
    }

    public function updateNode(): void
    {
    }

    /** @param mixed $nodeId */
    public function moveNode($nodeId, int $newLeftPosition): void
    {
    }

    /** @param mixed $primaryKeyValue */
    public function deleteNode($primaryKeyValue): bool
    {
        $this->connection->beginTransaction();

        $statement = $this->connection->prepare(
            <<<SQL
SELECT @myLeft:=:leftColumn, @myRight:=:rightColumn, @myWidth:=:rightColumn-:leftColumn + 1, @mySegment:=:parentColumn
FROM :tableName
WHERE :primaryKey = :primaryKeyValue
SQL
        );

        $statement->execute(
            [
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
                'parentColumn' => $this->config->getParentColumn(),
                'tableName' => $this->config->getTableName(),
                'primaryKey' => $this->config->getPrimaryKey(),
                'primaryKeyValue' => $primaryKeyValue,
            ]
        );

        $statement = $this->connection->prepare(
            <<<SQL
DELETE FROM :tableName
WHERE :parentColumn = @mySegment
    AND :leftColumn BETWEEN @myLeft AND @myRight
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'parentColumn' => $this->config->getParentColumn(),
                'leftColumn' => $this->config->getLeftColumn(),
            ]
        );

        $statement = $this->connection->prepare(
            <<<SQL
UPDATE :tableName
SET :rightColumn = :rightColumn - @myWidth
WHERE :parentColumn = @mySegment
    AND :rightColumn > @myRight
ORDER BY :rightColumn ASC
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'rightColumn' => $this->config->getRightColumn(),
                'parentColumn' => $this->config->getParentColumn(),
            ]
        );

        $statement = $this->connection->prepare(
            <<<SQL
UPDATE :tableName
SET :leftColumn = :leftColumn - @myWidth
WHERE :parentColumn = @mySegment
    AND :leftColumn > @myRight
ORDER BY :leftColumn ASC
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'leftColumn' => $this->config->getLeftColumn(),
                'parentColumn' => $this->config->getParentColumn(),
            ]
        );

        return $this->connection->commit();
    }

    /**
     * @param mixed $parentColumnValue
     *
     * @return \Generator<array|\stdClass>
     */
    public function getFullTree($parentColumnValue): \Generator
    {
        $statement = $this->connection->prepare(
            <<<SQL
SELECT (COUNT(parent.:primaryKey) - 1) AS depth, node.*
FROM :tableName AS node, :tableName AS parent
WHERE node.:parentColumn = :parentColumnValue
    AND parent.:parentColumn = :parentColumnValue
    AND node.:leftColumn BETWEEN parent.:leftColumn AND parent.:rightColumn
GROUP BY node.:primaryKey
ORDER BY node.:leftColumn;
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'primaryKey' => $this->config->getPrimaryKey(),
                'parentColumn' => $this->config->getParentColumn(),
                'parentColumnValue' => $parentColumnValue,
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
            ]
        );

        yield $statement->fetch($this->config->getFetchMode());
    }
}
