<?php

namespace Bolt\Storage\Migration;

use Bolt\Collection\Bag;
use Bolt\Storage\Entity\Entity;

/**
 * Entity field value DTO for use in transforms.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class Field
{
    /** @var string */
    private $name;
    /** @var mixed */
    private $value;
    /** @var Bag */
    private $meta;
    /** @var bool */
    private $changed;

    /**
     * Constructor.
     *
     * @param string $name
     * @param mixed  $value
     * @param Bag    $fieldMeta
     */
    public function __construct(Bag $record, Bag $fieldMeta)
    {
        $this->name = $fieldMeta->getPath('fieldname');
        $this->value = $record->get($this->name);
        $this->meta = $fieldMeta;
        $this->changed = false;
    }

    /**
     * @param Entity $entity
     */
    public function apply(Bag $entity)
    {
        $entity->set($this->name, $this->value);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
        $this->changed = true;
    }

    /**
     * @return Bag
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * @return bool
     */
    public function hasChanged()
    {
        return $this->changed;
    }
}
