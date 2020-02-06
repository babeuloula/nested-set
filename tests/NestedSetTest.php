<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\Test\NestedSet;

use BaBeuloula\NestedSet\NestedSet;

class NestedSetTest extends DatabaseTest
{
    /** @var FooNode */
    protected $clothingNode;

    /** @var NestedSet */
    protected $nestedSet;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->connection
            ->prepare(
                '
DROP TABLE IF EXISTS `' . static::TABLE_NAME . '`;

CREATE TABLE IF NOT EXISTS `' . static::TABLE_NAME . '` (
  `' . static::NODE_COLUMN . '` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `' . static::NAME_COLUMN . '` VARCHAR(255) NULL,
  `' . static::LEFT_COLUMN . '` INT UNSIGNED NULL,
  `' . static::RIGHT_COLUMN . '` INT UNSIGNED NULL,
  PRIMARY KEY (`' . static::NODE_COLUMN . '`),
  UNIQUE INDEX `id_UNIQUE` (`' . static::NODE_COLUMN . '` ASC),
  UNIQUE INDEX `left_right_count_UNIQUE` (`' . static::LEFT_COLUMN . '` ASC, `' . static::RIGHT_COLUMN . '` ASC))
ENGINE = InnoDB;
'
            )
            ->execute()
        ;

        $this->clothingNode = new FooNode();
        $this->clothingNode->setName("Clothing");

        $this->nestedSet = new NestedSet($this->connection);

        $this->nestedSet->addNode($this->clothingNode);
        $this->save($this->clothingNode);
    }

    /**
     * @covers NestedSet::__construct
     * @covers NestedSet::addNode
     * @covers NestedSet::updateLeftRightNode
     */
    public function testAddFirstNode(): void
    {
        static::assertSame(1, $this->clothingNode->getNodeId());
        static::assertSame(1, $this->clothingNode->getLeft());
        static::assertSame(2, $this->clothingNode->getRight());
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
        $suitsNode = new FooNode();
        $suitsNode->setName("Suits");

        $this->nestedSet->addNode($suitsNode, $this->clothingNode);
        $this->save($suitsNode);

        static::assertSame(1, $this->clothingNode->getNodeId());
        static::assertSame(1, $this->clothingNode->getLeft());
        static::assertSame(4, $this->clothingNode->getRight());

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
        $dressesNode = new FooNode();
        $dressesNode->setName("Dresses");

        $this->nestedSet = new NestedSet($this->connection);

        $this->nestedSet->addNode($dressesNode, $this->clothingNode);
        $this->save($dressesNode);

        $skirtsNode = new FooNode();
        $skirtsNode->setName("Skirts");

        $this->nestedSet->addNode($skirtsNode, null, $dressesNode);
        $this->save($skirtsNode);

        $this->refreshNode($this->clothingNode);
        $this->refreshNode($dressesNode);

        static::assertSame(1, $this->clothingNode->getNodeId());
        static::assertSame(1, $this->clothingNode->getLeft());
        static::assertSame(6, $this->clothingNode->getRight());

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
        $this->nestedSet = new NestedSet($this->connection);

        $suitsNode = new FooNode();
        $suitsNode->setName("Suits");

        $this->nestedSet->addNode($suitsNode, $this->clothingNode);
        $this->save($suitsNode);

        $jacketsNode = new FooNode();
        $jacketsNode->setName("Jackets");

        $this->nestedSet->addNode($jacketsNode, $suitsNode);
        $this->save($jacketsNode);

        $this->nestedSet->deleteNode($suitsNode);

        $this->refreshNode($this->clothingNode);

        static::assertSame(1, $this->clothingNode->getNodeId());
        static::assertSame(1, $this->clothingNode->getLeft());
        static::assertSame(2, $this->clothingNode->getRight());
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
