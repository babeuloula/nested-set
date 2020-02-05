<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\NestedSet;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\FetchMode;

class NestedSetModel
{
    protected const CHILD_OF_NODE = 'ChildOfNode';
    protected const BEFORE_NODE = 'BeforeNode';

    /** @var Connection */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /** @return \Generator<array|\stdClass> */
    public function getSiblings(NodeEntityInterface $node, int $fetchMode = FetchMode::ASSOCIATIVE): \Generator
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
                'tableName' => $node->getTableName(),
                'leftColumn' => $node->getLeftColumn(),
                'rightColumn' => $node->getRightColumn(),
                'nodeColumn' => $node->getNodeColumn(),
                'nodeColumnValue' => $node->getNodeId(),
            ]
        );

        yield $statement->fetch($fetchMode);
    }

    /** @return \Generator<array|\stdClass> */
    public function getAncestors(NodeEntityInterface $node, int $fetchMode = FetchMode::ASSOCIATIVE): \Generator
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
                'tableName' => $node->getTableName(),
                'leftColumn' => $node->getLeftColumn(),
                'rightColumn' => $node->getRightColumn(),
                'nodeColumn' => $node->getNodeColumn(),
                'nodeColumnValue' => $node->getNodeId(),
            ]
        );

        yield $statement->fetch($fetchMode);
    }

    /** @return \Generator<array|\stdClass> */
    public function getFullTree(NestedSetModelConfig $config): \Generator
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
                'tableName' => $config->getTableName(),
                'nodeColumn' => $config->getNodeColumn(),
                'leftColumn' => $config->getLeftColumn(),
                'rightColumn' => $config->getRightColumn(),
            ]
        );

        yield $statement->fetch($config->getFetchMode());
    }

    public function addNode(
        NodeEntityInterface $mainNode,
        NodeEntityInterface $childOfNode = null,
        NodeEntityInterface $beforeNode = null
    ): self {
        if (false === $childOfNode instanceof NodeEntityInterface
            && false === $beforeNode instanceof NodeEntityInterface
        ) {
            $this->insertNode($mainNode, 0, 1);
        } elseif (false === \is_null($childOfNode)) {
            $this->insertChildOfNode($mainNode, $childOfNode);
        } elseif (false === \is_null($beforeNode)) {
            $this->insertBeforeNode($mainNode, $beforeNode);
        } else {
            throw new \LogicException("You can't set \$childOfNode and \$beforeNode. You need to choose one of them.");
        }

        return $this;
    }

    public function moveNode(NodeEntityInterface $node, int $newLeftPosition): self
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
                        'leftColumn' => $node->getLeftColumn(),
                        'rightColumn' => $node->getRightColumn(),
                        'newLeftPosition' => $newLeftPosition,
                        'tableName' => $node->getTableName(),
                        'nodeColumn' => $node->getNodeColumn(),
                        'nodeColumnValue' => $node->getNodeId(),
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
                        'tableName' => $node->getTableName(),
                        'leftColumn' => $node->getLeftColumn(),
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
                        'tableName' => $node->getTableName(),
                        'rightColumn' => $node->getRightColumn(),
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
                        'tableName' => $node->getTableName(),
                        'leftColumn' => $node->getLeftColumn(),
                        'rightColumn' => $node->getRightColumn(),
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
                        'tableName' => $node->getTableName(),
                        'leftColumn' => $node->getLeftColumn(),
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
                        'tableName' => $node->getTableName(),
                        'rightColumn' => $node->getRightColumn(),
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

    public function deleteNode(NodeEntityInterface $node): self
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
                        'leftColumn' => $node->getLeftColumn(),
                        'rightColumn' => $node->getRightColumn(),
                        'tableName' => $node->getTableName(),
                        'nodeColumn' => $node->getNodeColumn(),
                        'nodeColumnValue' => $node->getNodeId(),
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
                        'tableName' => $node->getTableName(),
                        'leftColumn' => $node->getLeftColumn(),
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
                        'tableName' => $node->getTableName(),
                        'rightColumn' => $node->getRightColumn(),
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
                        'tableName' => $node->getTableName(),
                        'leftColumn' => $node->getLeftColumn(),
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

    protected function insertNode(NodeEntityInterface $mainNode, int $leftColumnValue, int $rightColumnValue): self
    {
        $mainNode
            ->setLeft($leftColumnValue)
            ->setRight($rightColumnValue)
        ;

        return $this;
    }

    protected function insertChildOfNode(NodeEntityInterface $mainNode, NodeEntityInterface $childOfNode): self
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
                    'rightColumn' => $childOfNode->getRightColumn(),
                    'tableName' => $childOfNode->getTableName(),
                    'nodeColumn' => $childOfNode->getNodeColumn(),
                    'childOfNode' => $childOfNode->getNodeId(),
                ]
            );

            $myRight = $statement->fetchColumn();
            if (true === \is_bool($myRight)) {
                $myRight = 0;
            }

            $this->updateLeftRight($mainNode, (int) $myRight, static::CHILD_OF_NODE);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    protected function insertBeforeNode(NodeEntityInterface $mainNode, NodeEntityInterface $beforeNode): self
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
                    'leftColumn' => $beforeNode->getLeftColumn(),
                    'tableName' => $beforeNode->getTableName(),
                    'nodeColumn' => $beforeNode->getNodeColumn(),
                    'beforeNode' => $beforeNode->getNodeId(),
                ]
            );

            $myLeft = $statement->fetchColumn();
            if (true === \is_bool($myLeft)) {
                $myLeft = 0;
            }

            $this->updateLeftRight($mainNode, (int) $myLeft, static::BEFORE_NODE);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    protected function updateLeftRight(NodeEntityInterface $mainNode, int $my, string $insertType): self
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
                    'tableName' => $mainNode->getTableName(),
                    'rightColumn' => $mainNode->getRightColumn(),
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
                'tableName' => $mainNode->getTableName(),
                'leftColumn' => $mainNode->getLeftColumn(),
            ]
        );

        $this->insertNode($mainNode, $my, $my + 1);

        return $this;
    }
}
