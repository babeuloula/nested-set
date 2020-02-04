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

    /** @param mixed $nodeId */
    public function deleteNode($nodeId): void
    {
    }

    public function getFullTree(): void
    {
    }
}
