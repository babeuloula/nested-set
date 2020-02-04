<?php

/**
 * @author BaBeuloula <info@babeuloula.fr>
 */

declare(strict_types=1);

namespace BaBeuloula;

use Doctrine\DBAL\FetchMode;

class NestedSetModelConfig
{
    /** @var string */
    protected $tableName;

    /** @var int */
    protected $fetchMode;

    /** @var string */
    protected $primaryKey;

    /** @var string */
    protected $parentColumn;

    /** @var string */
    protected $leftColumn;

    /** @var string */
    protected $rightColumn;

    public function __construct(
        string $tableName,
        int $fetchMode = FetchMode::ASSOCIATIVE,
        string $primaryKey = 'id',
        string $parentColumn = 'parent',
        string $leftColumn = 'left_count',
        string $rightColumn = 'right_count'
    ) {
        $this->tableName = $tableName;
        $this->fetchMode = $fetchMode;
        $this->primaryKey = $primaryKey;
        $this->parentColumn = $parentColumn;
        $this->leftColumn = $leftColumn;
        $this->rightColumn = $rightColumn;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;

        return $this;
    }

    public function getFetchMode(): int
    {
        return $this->fetchMode;
    }

    public function setFetchMode(int $fetchMode): self
    {
        $this->fetchMode = $fetchMode;

        return $this;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function setPrimaryKey(string $primaryKey): self
    {
        $this->primaryKey = $primaryKey;

        return $this;
    }

    public function getParentColumn(): string
    {
        return $this->parentColumn;
    }

    public function setParentColumn(string $parentColumn): self
    {
        $this->parentColumn = $parentColumn;

        return $this;
    }

    public function getLeftColumn(): string
    {
        return $this->leftColumn;
    }

    public function setLeftColumn(string $leftColumn): self
    {
        $this->leftColumn = $leftColumn;

        return $this;
    }

    public function getRightColumn(): string
    {
        return $this->rightColumn;
    }

    public function setRightColumn(string $rightColumn): self
    {
        $this->rightColumn = $rightColumn;

        return $this;
    }
}
