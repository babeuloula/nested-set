<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\Test\NestedSet;

use BaBeuloula\NestedSet\NestedSet;

class NestedSetTest extends DatabaseTest
{
    /**
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::updateLeftRightNode
     */
    public function testAddFirstNode(): FooNode
    {
        $this->resetDatabase();

        $clothingNode = new FooNode();
        $clothingNode->setName("Clothing");

        $nestedSet = new NestedSet($this->connection);
        $nestedSet->addNode($clothingNode);

        $this->save($clothingNode);

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(0, $clothingNode->getLeft());
        static::assertSame(1, $clothingNode->getRight());

        return $clothingNode;
    }

    /**
     * @depends testAddFirstNode
     *
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::setPositionChildOfNode
     * @covers NestedSet::updateNodes
     * @covers NestedSet::updateLeftRightNode
     *
     * @return FooNode[]
     */
    public function testAddChildOfFirstNode(FooNode $clothingNode): array
    {
        $suitsNode = new FooNode();
        $suitsNode->setName("Suits");

        $nestedSet = new NestedSet($this->connection);
        $nestedSet->addNode($suitsNode, $clothingNode);

        $this->save($suitsNode);

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(0, $clothingNode->getLeft());
        static::assertSame(3, $clothingNode->getRight());

        static::assertSame(2, $suitsNode->getNodeId());
        static::assertSame(1, $suitsNode->getLeft());
        static::assertSame(2, $suitsNode->getRight());

        return [
            $clothingNode,
            $suitsNode,
        ];
    }

    /**
     * @depends testAddChildOfFirstNode
     *
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::setPositionBeforeNode
     * @covers NestedSet::updateNodes
     * @covers NestedSet::updateLeftRightNode
     *
     * @param FooNode[] $previousNodes
     */
    public function testDeleteSecondNode(array $previousNodes): void
    {
        [$clothingNode, $suitsNode] = $previousNodes;

        $nestedSet = new NestedSet($this->connection);
        $nestedSet->deleteNode($suitsNode);

        $this->refreshNode($clothingNode);

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(0, $clothingNode->getLeft());
        static::assertSame(1, $clothingNode->getRight());
    }

    protected function save(FooNode $node): void
    {
        $params = [
            'name' => $node->getName(),
            'leftColumnValue' => $node->getLeft(),
            'rightColumnValue' => $node->getRight(),
        ];

        if (true === \is_null($node->getNodeId())) {
            $statement = $this
                ->connection
                ->prepare(
                    '
INSERT INTO `' . static::TABLE_NAME . '`
    (`' . static::NAME_COLUMN . '`, `' . static::LEFT_COLUMN . '`, `' . static::RIGHT_COLUMN . '`)
VALUES (:name, :leftColumnValue, :rightColumnValue)
'
                )
            ;
        } else {
            $statement = $this
                ->connection
                ->prepare(
                    '
UPDATE ' . static::TABLE_NAME . '
SET `' . static::NAME_COLUMN . '` = :name,
    `' . static::LEFT_COLUMN . '` = :leftColumnValue,
    `' . static::RIGHT_COLUMN . '` = :rightColumnValue
WHERE `' . static::NODE_COLUMN . '` = :nodeId
'
                )
            ;

            $params = \array_merge(
                $params,
                [
                    'nodeId' => $node->getNodeId(),
                ]
            );
        }

        $statement->execute($params);

        if (true === \is_null($node->getNodeId())) {
            $node->setId(
                (int) $this->connection->lastInsertId()
            );
        }
    }

    protected function refreshNode(FooNode $node): void
    {
        $statement = $this
            ->connection
            ->prepare(
                '
SELECT `' . static::NAME_COLUMN . '`, `' . static::LEFT_COLUMN . '`, `' . static::RIGHT_COLUMN . '`
FROM ' . static::TABLE_NAME . '
WHERE `' . static::NODE_COLUMN . '` = :nodeId
'
            )
        ;

        $statement->execute(
            [
                'nodeId' => $node->getNodeId(),
            ]
        );

        $data = $statement->fetch();

        $node->setName((string) $data['name']);
        $node->setLeft((int) $data['left']);
        $node->setRight((int) $data['right']);
    }
}
