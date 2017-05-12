<?php

namespace Bolt\Storage\Migration\Result;

use Bolt\Collection\MutableBag;

/**
 * Response to a schema update.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class SchemaResult
{
    /** @var string */
    private $platform;
    /** @var MutableBag */
    private $results;

    /**
     * Constructor.
     *
     * @param string $platform
     */
    public function __construct($platform)
    {
        $this->platform = $platform;
        $this->results = MutableBag::of();
    }

    public function addResult($result)
    {
        $this->results->add($result);
    }

    /**
     * @return MutableBag
     */
    public function getResults()
    {
        return $this->results;
    }
}
