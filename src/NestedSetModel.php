<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula;

use Doctrine\DBAL\Driver\Connection;

class NestedSetModel
{
    protected const CHILD_OF_NODE = 'ChildOfNode';
    protected const BEFORE_NODE = 'BeforeNode';

    /** @var Connection */
    protected $connection;

    /** @var NestedSetModelConfig */
    protected $config;

    public function __construct(Connection $connection, NestedSetModelConfig $config)
    {
        $this->connection = $connection;
        $this->config = $config;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
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

    /**
     * @param null|mixed $childOfNode
     * @param null|mixed $beforeNode
     */
    public function addNode($childOfNode = null, $beforeNode = null): self
    {
        if (true === \is_null($childOfNode) && true === \is_null($beforeNode)) {
            $this->insertNode(0, 1);
        } elseif (false === \is_null($childOfNode)) {
            $this->insertChildOfNode($childOfNode);
        } elseif (false === \is_null($beforeNode)) {
            $this->insertBeforeNode($beforeNode);
        } else {
            throw new \LogicException("You can't set \$childOfNode and \$beforeNode. You need to choose one of them.");
        }

        return $this;
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

    protected function insertNode(int $leftColumnValue, int $rightColumnValue): self
    {
        try {
            $this->connection->beginTransaction();

            $this
                ->connection
                ->prepare(
                    <<<SQL
INSERT INTO :tableName (:leftColumn, :rightColumn)
VALUES (:leftColumnValue, :rightColumnValue)
SQL
                )
                ->execute(
                    [
                        'tableName' => $this->config->getTableName(),
                        'leftColumn' => $this->config->getLeftColumn(),
                        'rightColumn' => $this->config->getRightColumn(),
                        'leftColumnValue' => $leftColumnValue,
                        'rightColumnValue' => $rightColumnValue,
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

    /** @param mixed $childOfNode */
    protected function insertChildOfNode($childOfNode): self
    {
        try {
            $this->connection->beginTransaction();

            $statement = $this
                ->connection
                ->prepare(
                    <<<SQL
SELECT @my:=:rightColumn
FROM :tableName
WHERE :nodeColumn = :childOfNode
SQL
                )
            ;

            $statement->execute(
                [
                    'rightColumn' => $this->config->getRightColumn(),
                    'tableName' => $this->config->getTableName(),
                    'nodeColumn' => $this->config->getNodeColumn(),
                    'childOfNode' => $childOfNode,
                ]
            );

            $myRight = $statement->fetchColumn();
            if (true === \is_bool($myRight)) {
                $myRight = 0;
            }

            $this->updateLeftRight((int) $myRight, static::CHILD_OF_NODE);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    /** @param mixed $beforeNode */
    protected function insertBeforeNode($beforeNode): self
    {
        try {
            $this->connection->beginTransaction();

            $statement = $this
                ->connection
                ->prepare(
                    <<<SQL
SELECT @my:=:leftColumn
FROM :tableName
WHERE :nodeColumn = :beforeNode
    AND :leftColumn != 0
SQL
                )
            ;

            $statement->execute(
                [
                    'leftColumn' => $this->config->getLeftColumn(),
                    'tableName' => $this->config->getTableName(),
                    'nodeColumn' => $this->config->getNodeColumn(),
                    'beforeNode' => $beforeNode,
                ]
            );

            $myLeft = $statement->fetchColumn();
            if (true === \is_bool($myLeft)) {
                $myLeft = 0;
            }

            $this->updateLeftRight((int) $myLeft, static::BEFORE_NODE);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    protected function updateLeftRight(int $my, string $insertType): self
    {
        $this
            ->connection
            ->prepare(
                <<<SQL
UPDATE :tableName
SET :rightColumn = :rightColumn + 2
WHERE :rightColumn >= @my
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

        if (static::CHILD_OF_NODE === $insertType) {
            $statement = $this
                ->connection
                ->prepare(
                    <<<SQL
UPDATE :tableName
SET :leftColumn = :leftColumn + 2
WHERE :leftColumn > @my
ORDER BY :leftColumn DESC
SQL
                );
        } else {
            $statement = $this
                ->connection
                ->prepare(
                    <<<SQL
UPDATE :tableName
SET :leftColumn = :leftColumn + 2
WHERE :leftColumn >= @my
ORDER BY :leftColumn DESC
SQL
                );
        }

        $statement->execute(
            [
                'tableName' => $this->config->getTableName(),
                'leftColumn' => $this->config->getLeftColumn(),
            ]
        );

        $this->insertNode($my, $my + 1);

        return $this;
    }
}
