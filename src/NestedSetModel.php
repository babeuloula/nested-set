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
     * @param mixed $nodeId
     *
     * @return \Generator<array|\stdClass>
     */
    public function getSiblings($nodeId): \Generator
    {
        $statement = $this->connection->prepare(
            <<<SQL
SELECT child.*
FROM :tableName parent
JOIN :tableName child 
    ON (child.:leftColumn > parent.:leftColumn
    AND child.:rightColumn < parent.:rightColumn
LEFT JOIN :tableName intermediate
    ON (intermediate.:leftColumn > parent.:leftColumn AND intermediate.:rightColumn < parent.:rightColumn
    AND child.:leftColumn > intermediate.:leftColumn AND child.:rightColumn < intermediate.:rightColumn
WHERE intermediate.:nodeColumn IS NULL
    AND parent.:nodeColumn = :nodeColumnValue
ORDER BY child.:leftColumn;
SQL
        );
        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
                'nodeColumn' => $this->config->getNodeColumn(),
                'nodeColumnValue' => $nodeId,
            ]
        );

        yield $statement->fetch($this->config->getFetchMode());
    }

    /**
     * @param mixed $nodeId
     *
     * @return \Generator<array|\stdClass>
     */
    public function getAncestors($nodeId): \Generator
    {
        $statement = $this->connection->prepare(
            <<<SQL
SELECT parent.*
FROM :tableName parent
LEFT JOIN :tableName child
    ON (parent.:leftColumn < child.:leftColumn AND parent.:rightColumn > child.:rightColumn
    AND parent.:parentColumn = child.:parentColumn)
WHERE child.:nodeColumn = :nodeColumnValue
ORDER BY parent.:leftColumn ASC;
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
                'nodeColumn' => $this->config->getNodeColumn(),
                'nodeColumnValue' => $nodeId,
            ]
        );

        yield $statement->fetch($this->config->getFetchMode());
    }

    /** @return \Generator<array|\stdClass> */
    public function getFullTree(): \Generator
    {
        $statement = $this->connection->prepare(
            <<<SQL
SELECT (COUNT(parent.:nodeColumn) - 1) AS depth, node.*
FROM :tableName AS node, :tableName AS parent
WHERE node.:leftColumn BETWEEN parent.:leftColumn AND parent.:rightColumn
GROUP BY node.:nodeColumn
ORDER BY node.:leftColumn;
SQL
        );

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'nodeColumn' => $this->config->getNodeColumn(),
                'leftColumn' => $this->config->getLeftColumn(),
                'rightColumn' => $this->config->getRightColumn(),
            ]
        );

        yield $statement->fetch($this->config->getFetchMode());
    }

    public function addNode(): void
    {
    }

    /** @param mixed $nodeId */
    public function moveNode($nodeId, int $newLeftPosition): self
    {
        try {
            $this->connection->beginTransaction();

            $this
                ->connection
                ->prepare(
                    <<<SQL
    SELECT @myLeft:=:leftColumn, @myRight:=:rightColumn, @myWidth:=:rightColumn - :leftColumn + 1,
        @myNewLeftPosition:=:newLeftPosition,
        @distance:=:newLeftPosition - :leftColumn
            + IF(:newLeftPosition < :leftColumn, - (:rightColumn - :leftColumn + 1), 0),
        @tmpLeft:=:leftColumn + IF(:newLeftPosition < :leftColumn, (:rightColumn - :leftColumn + 1), 0)
    FROM :tableName
    WHERE :nodeColumn = :nodeColumnValue
SQL
                )
                ->execute(
                    [
                        'leftColumn' => $this->config->getLeftColumn(),
                        'rightColumn' => $this->config->getRightColumn(),
                        'newLeftPosition' => $newLeftPosition,
                        'tableName' => $this->config->getTableName(),
                        'nodeColumn' => $this->config->getNodeColumn(),
                        'nodeColumnValue' => $nodeId,
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :leftColumn = :leftColumn + @myWidth
    WHERE :leftColumn >= @myNewLeftPosition
    ORDER BY :leftColumn DESC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'leftColumn' => $this->config->getLeftColumn(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :rightColumn = :rightColumn + @myWidth
    WHERE :rightColumn >= @myNewLeftPosition
    ORDER BY :rightColumn DESC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'rightColumn' => $this->config->getRightColumn(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :leftColumn = :leftColumn + @distance, :rightColumn = :rightColumn + @distance
    WHERE :leftColumn >= @tmpLeft
        AND :rightColumn < @tmpLeft + @myWidth
    ORDER BY :leftColumn ASC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'leftColumn' => $this->config->getLeftColumn(),
                        'rightColumn' => $this->config->getRightColumn(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :leftColumn = :leftColumn - @myWidth
    WHERE :leftColumn > @myRight
    ORDER BY :leftColumn ASC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'leftColumn' => $this->config->getLeftColumn(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :rightColumn = :rightColumn - @myWidth
    WHERE :rightColumn > @myRight
    ORDER BY :rightColumn ASC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'rightColumn' => $this->config->getRightColumn(),
                    ]
                )
            ;

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    /** @param mixed $nodeId */
    public function deleteNode($nodeId): self
    {
        try {
            $this->connection->beginTransaction();

            $this
                ->connection
                ->prepare(
                    <<<SQL
    SELECT @myLeft:=:leftColumn, @myRight:=:rightColumn, @myWidth:=:rightColumn-:leftColumn + 1
    FROM :tableName
    WHERE :nodeColumn = :nodeColumnValue
SQL
                )
                ->execute(
                    [
                        'leftColumn' => $this->config->getLeftColumn(),
                        'rightColumn' => $this->config->getRightColumn(),
                        'tableName' => $this->config->getTableName(),
                        'nodeColumn' => $this->config->getNodeColumn(),
                        'nodeColumnValue' => $nodeId,
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    DELETE FROM :tableName
    WHERE :leftColumn BETWEEN @myLeft AND @myRight
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'leftColumn' => $this->config->getLeftColumn(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :rightColumn = :rightColumn - @myWidth
    WHERE :rightColumn > @myRight
    ORDER BY :rightColumn ASC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'rightColumn' => $this->config->getRightColumn(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    <<<SQL
    UPDATE :tableName
    SET :leftColumn = :leftColumn - @myWidth
    WHERE :leftColumn > @myRight
    ORDER BY :leftColumn ASC
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'leftColumn' => $this->config->getLeftColumn(),
                    ]
                )
            ;

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }
}
