<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\NestedSet;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\FetchMode;

class NestedSet
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
    public function getFullTree(NestedSetConfig $config): \Generator
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
            $this->updateLeftRightNode($mainNode, 1, 2);
        } elseif (false === \is_null($childOfNode)) {
            $this->setPositionChildOfNode($mainNode, $childOfNode);
        } elseif (false === \is_null($beforeNode)) {
            $this->setPositionBeforeNode($mainNode, $beforeNode);
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
                    '
SELECT @myLeft:=`' . $node->getLeftColumn() . '`,
    @myRight:=`' . $node->getRightColumn() . '`,
    @myWidth:=`' . $node->getRightColumn() . '`-`' . $node->getLeftColumn() . '`
FROM `' . $node->getTableName() . '`
WHERE `' . $node->getNodeColumn() . '` = :nodeColumnValue
'
                )
                ->execute(
                    [
                        'nodeColumnValue' => $node->getNodeId(),
                    ]
                )
            ;

            $this
                ->connection
                ->prepare(
                    '
DELETE FROM `' . $node->getTableName() . '`
WHERE `' . $node->getLeftColumn() . '` BETWEEN @myLeft AND @myRight
'
                )
                ->execute()
            ;

            $this
                ->connection
                ->prepare(
                    '
    UPDATE `' . $node->getTableName() . '`
    SET `' . $node->getRightColumn() . '` = `' . $node->getRightColumn() . '` - (@myWidth + 1)
    WHERE `' . $node->getRightColumn() . '` > @myRight
    ORDER BY `' . $node->getRightColumn() . '` ASC
'
                )
                ->execute()
            ;

            $this
                ->connection
                ->prepare(
                    '
    UPDATE `' . $node->getTableName() . '`
    SET `' . $node->getLeftColumn() . '` = `' . $node->getLeftColumn() . '` - @myWidth
    WHERE `' . $node->getLeftColumn() . '` > @myRight
    ORDER BY `' . $node->getLeftColumn() . '` ASC
'
                )
                ->execute()
            ;

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    protected function updateLeftRightNode(NodeEntityInterface $node, int $leftColumnValue, int $rightColumnValue): self
    {
        $node
            ->setLeft($leftColumnValue)
            ->setRight($rightColumnValue)
        ;

        return $this;
    }

    protected function setPositionChildOfNode(NodeEntityInterface $mainNode, NodeEntityInterface $childOfNode): self
    {
        try {
            $this->connection->beginTransaction();

            $statement = $this
                ->connection
                ->prepare(
                    '
SELECT @my:=`' . $childOfNode->getRightColumn() . '`
FROM `' . $childOfNode->getTableName() . '`
WHERE `' . $childOfNode->getNodeColumn() . '` = :childOfNode
'
                )
            ;

            $statement->execute(
                [
                    'childOfNode' => $childOfNode->getNodeId(),
                ]
            );

            $myRight = $statement->fetchColumn();
            if (true === \is_bool($myRight)) {
                $myRight = 0;
            }

            $this->updateNodes($mainNode, (int) $myRight, static::CHILD_OF_NODE);
            $this->updateLeftRightNode(
                $childOfNode,
                $mainNode->getLeft() - 1,
                $mainNode->getRight() + 1
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    protected function setPositionBeforeNode(NodeEntityInterface $mainNode, NodeEntityInterface $beforeNode): self
    {
        try {
            $this->connection->beginTransaction();

            $statement = $this
                ->connection
                ->prepare(
                    '
SELECT @my:=`' . $beforeNode->getLeftColumn() . '`
FROM `' . $beforeNode->getTableName() . '`
WHERE `' . $beforeNode->getNodeColumn() . '` = :beforeNode
    AND `' . $beforeNode->getLeftColumn() . '` != 0
'
                )
            ;

            $statement->execute(
                [
                    'beforeNode' => $beforeNode->getNodeId(),
                ]
            );

            $myLeft = $statement->fetchColumn();
            if (true === \is_bool($myLeft)) {
                $myLeft = 0;
            }

            $this->updateNodes($mainNode, (int) $myLeft, static::BEFORE_NODE);
            $this->updateLeftRightNode(
                $beforeNode,
                $mainNode->getLeft() - 1,
                $mainNode->getRight() + 1
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return $this;
    }

    protected function updateNodes(NodeEntityInterface $mainNode, int $my, string $insertType): self
    {
        $this
            ->connection
            ->prepare(
                '
UPDATE `' . $mainNode->getTableName() . '`
SET `' . $mainNode->getRightColumn() . '` = `' . $mainNode->getRightColumn() . '` + 2
WHERE `' . $mainNode->getRightColumn() . '` >= @my
ORDER BY `' . $mainNode->getRightColumn() . '` DESC
'
            )
            ->execute()
        ;

        if (static::CHILD_OF_NODE === $insertType) {
            $statement = $this
                ->connection
                ->prepare(
                    '
UPDATE `' . $mainNode->getTableName() . '`
SET `' . $mainNode->getLeftColumn() . '` = `' . $mainNode->getLeftColumn() . '` + 2
WHERE `' . $mainNode->getLeftColumn() . '` > @my
ORDER BY `' . $mainNode->getLeftColumn() . '` DESC
'
                );
        } else {
            $statement = $this
                ->connection
                ->prepare(
                    '
UPDATE `' . $mainNode->getTableName() . '`
SET `' . $mainNode->getLeftColumn() . '` = `' . $mainNode->getLeftColumn() . '` + 2
WHERE `' . $mainNode->getLeftColumn() . '` >= @my
ORDER BY `' . $mainNode->getLeftColumn() . '` DESC
'
                );
        }

        $statement->execute();

        $this->updateLeftRightNode($mainNode, $my, $my + 1);

        return $this;
    }
}
