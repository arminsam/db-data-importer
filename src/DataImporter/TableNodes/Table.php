<?php

namespace DataImporter\TableNodes;

use Exception;

class Table
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $pk;

    /**
     * @var Table
     */
    public $parent;

    /**
     * @var Table[]
     */
    public $children = [];

    /**
     * @var array
     */
    public $ids = [];

    /**
     * @var string
     */
    public $foreignKey = '';

    /**
     * @var string
     */
    public $otherKey = '';

    /**
     * @var string
     */
    public $relation;

    /**
     * @var array
     */
    public $data = [];

    /**
     * Set the ids to be queried for each child table of this table based on the relationship type.
     *
     * @throws Exception
     */
    public function setChildrenIds()
    {
        foreach ($this->children as $child) {
            if ($child->relation == 'has_many') {
                $ids = array_column($this->data, $child->otherKey);
            } elseif ($child->relation == 'belongs_to') {
                $ids = array_column($this->data, $child->foreignKey);
            } else {
                throw new Exception("Error! Invalid relation {$child->relation} given fro table {$child->name}");
            }
            $child->ids = array_values(array_filter($ids, function($id) { return ! is_null($id); }));
        }
    }

    /**
     * Is this table a reference to another table?
     *
     * @return bool
     */
    public function isReferenced()
    {
        return substr($this->name, 0, 1) == '@';
    }

    /**
     * Get the name of the referenced table.
     *
     * @return string
     */
    public function getReferencedTableName()
    {
        if ($this->isReferenced()) {
            return ltrim($this->name, '@');
        }

        return $this->name;
    }

    /**
     * Return the column name which the select query should use.
     *
     * @return string
     */
    public function getQueryColumn()
    {
        if ($this->relation == 'has_many') {
            return $this->foreignKey;
        } elseif ($this->relation == 'belongs_to') {
            return $this->otherKey;
        }

        return $this->pk;
    }
}