<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\Test\NestedSet;

use BaBeuloula\NestedSet\NodeEntityInterface;

class FooNode implements NodeEntityInterface
{
    /** @var null|int */
    protected $nodeId;

    /** @var null|string */
    protected $name;

    /** @var null|int */
    protected $left;

    /** @var null|int */
    protected $right;

    public function getTableName(): string
    {
        return DatabaseTest::TABLE_NAME;
    }

    public function getNodeId(): ?int
    {
        return $this->nodeId;
    }

    public function setId(int $nodeId): self
    {
        $this->nodeId = $nodeId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getNodeColumn(): string
    {
        return DatabaseTest::NODE_COLUMN;
    }

    public function getLeftColumn(): string
    {
        return DatabaseTest::LEFT_COLUMN;
    }

    public function getRightColumn(): string
    {
        return DatabaseTest::RIGHT_COLUMN;
    }

    public function getLeft(): ?int
    {
        return $this->left;
    }

    public function setLeft(int $left): self
    {
        $this->left = $left;

        return $this;
    }

    public function getRight(): ?int
    {
        return $this->right;
    }

    public function setRight(int $right): self
    {
        $this->right = $right;

        return $this;
    }
}
