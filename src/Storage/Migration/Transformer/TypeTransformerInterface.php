<?php

namespace Bolt\Storage\Migration\Transformer;

use Bolt\Storage\Migration\Field;

/**
 * Database field type transformer interface.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
interface TypeTransformerInterface
{
    /**
     * @param Field $field
     *
     * @return bool
     */
    public function supports(Field $field);

    /**
     * @param Field $field
     */
    public function transform(Field $field);
}
