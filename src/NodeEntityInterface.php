<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula\NestedSet;

interface NodeEntityInterface
{
    public function getTableName(): string;

    /** @return mixed */
    public function getNodeId();
    public function getNodeColumn(): string;

    public function getLeftColumn(): string;
    public function getRightColumn(): string;

    public function setLeft(int $left): self;
    public function setRight(int $right): self;
}
