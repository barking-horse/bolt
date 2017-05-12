<?php

namespace Bolt\Storage\Migration\Transformer;

use Bolt\Storage\Migration\Field;

/**
 * Transformer for nullable data.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class NullableTypeTransformer implements TypeTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Field $field)
    {
        return (bool) $field->getMeta()->getPath('nullable');
    }

    /**
     * Nullify empty nullable fields to a save a tiny amount of storage space per field.
     *
     * {@inheritdoc}
     */
    public function transform(Field $field)
    {
        if (!$this->supports($field)) {
            return;
        }
        if ($field->getValue() === '' || $field->getValue() === []) {
            $field->setValue(null);
        }
    }
}
