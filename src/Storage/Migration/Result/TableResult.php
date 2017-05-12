<?php

namespace Bolt\Storage\Migration\Result;

/**
 * Response to a table update.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class TableResult
{
    /** @var bool */
    private $isBolt;
    /** @var string */
    private $tableName;
    /** @var int */
    private $records;
    /** @var int */
    private $fields;

    /**
     * Constructor.
     *
     * @param string $tableName
     * @param bool   $isBolt
     */
    public function __construct($tableName, $isBolt = false)
    {
        $this->tableName = $tableName;
        $this->isBolt = $isBolt;
        $this->records = 0;
        $this->fields = 0;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    public function addRecord()
    {
        $this->records = ++$this->records;
    }

    /**
     * @return int
     */
    public function getRecordCount()
    {
        return $this->records;
    }

    public function addField()
    {
        $this->fields = ++$this->fields;
    }

    /**
     * @return int
     */
    public function getFieldCount()
    {
        return $this->fields;
    }

    /**
     * @return bool
     */
    public function isBolt()
    {
        return $this->isBolt;
    }

    /**
     * @return bool
     */
    public function isUpdated()
    {
        return empty($this->records[$this->tableName]);
    }
}
