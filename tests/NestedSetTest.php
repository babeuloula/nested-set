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
    public function testAddFirstNode(): void
    {
        $clothingNode = $this->createFirstNode();

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(1, $clothingNode->getLeft());
        static::assertSame(2, $clothingNode->getRight());
    }

    /**
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::setPositionChildOfNode
     * @covers NestedSet::updateNodes
     * @covers NestedSet::updateLeftRightNode
     */
    public function testAddChildOfFirstNode(): void
    {
        $clothingNode = $this->createFirstNode();

        $suitsNode = new FooNode();
        $suitsNode->setName("Suits");

        $nestedSet = new NestedSet($this->connection);

        $nestedSet->addNode($suitsNode, $clothingNode);
        $this->save($suitsNode);

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(1, $clothingNode->getLeft());
        static::assertSame(4, $clothingNode->getRight());

        static::assertSame(2, $suitsNode->getNodeId());
        static::assertSame(2, $suitsNode->getLeft());
        static::assertSame(3, $suitsNode->getRight());
    }

    /**
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::setPositionChildOfNode
     * @covers NestedSet::setPositionBeforeNode
     * @covers NestedSet::updateNodes
     * @covers NestedSet::updateLeftRightNode
     */
    public function testAddBeforeNode(): void
    {
        $clothingNode = $this->createFirstNode();

        $dressesNode = new FooNode();
        $dressesNode->setName("Dresses");

        $nestedSet = new NestedSet($this->connection);

        $nestedSet->addNode($dressesNode, $clothingNode);
        $this->save($dressesNode);

        $skirtsNode = new FooNode();
        $skirtsNode->setName("Skirts");

        $nestedSet->addNode($skirtsNode, null, $dressesNode);
        $this->save($skirtsNode);

        $this->refreshNode($clothingNode);
        $this->refreshNode($dressesNode);

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(1, $clothingNode->getLeft());
        static::assertSame(6, $clothingNode->getRight());

        static::assertSame(2, $dressesNode->getNodeId());
        static::assertSame(4, $dressesNode->getLeft());
        static::assertSame(5, $dressesNode->getRight());

        static::assertSame(3, $skirtsNode->getNodeId());
        static::assertSame(2, $skirtsNode->getLeft());
        static::assertSame(3, $skirtsNode->getRight());
    }

    /**
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::setPositionBeforeNode
     * @covers NestedSet::updateNodes
     * @covers NestedSet::updateLeftRightNode
     */
    public function testDeleteSecondNode(): void
    {
        $clothingNode = $this->createFirstNode();

        $nestedSet = new NestedSet($this->connection);

        $suitsNode = new FooNode();
        $suitsNode->setName("Suits");

        $nestedSet->addNode($suitsNode, $clothingNode);
        $this->save($suitsNode);

        $jacketsNode = new FooNode();
        $jacketsNode->setName("Jackets");

        $nestedSet->addNode($jacketsNode, $suitsNode);
        $this->save($jacketsNode);

        $nestedSet->deleteNode($suitsNode);

        $this->refreshNode($clothingNode);

        static::assertSame(1, $clothingNode->getNodeId());
        static::assertSame(1, $clothingNode->getLeft());
        static::assertSame(2, $clothingNode->getRight());
    }

    protected function createFirstNode(): FooNode
    {
        $this->resetDatabase();

        $clothingNode = new FooNode();
        $clothingNode->setName("Clothing");

        $nestedSet = new NestedSet($this->connection);

        $nestedSet->addNode($clothingNode);
        $this->save($clothingNode);

        return $clothingNode;
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
