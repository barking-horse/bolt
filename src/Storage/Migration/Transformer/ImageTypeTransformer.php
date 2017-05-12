<?php

namespace Bolt\Storage\Migration\Transformer;

use Bolt\Collection\MutableBag;
use Bolt\Common\Json;
use Bolt\Storage\Field\Type;
use Bolt\Storage\Migration\Field;

/**
 * Transformer for image field data.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class ImageTypeTransformer implements TypeTransformerInterface
{
    private $imageDefault = [
        'file'  => null,
        'title' => null,
        'alt'   => null,
    ];

    /**
     * {@inheritdoc}
     */
    public function supports(Field $field)
    {
        return $field->getMeta()->getPath('fieldtype') === Type\ImageType::class;
    }

    /**
     * Until v 1.5 field values were stored as strings, now JSON arrays.
     *
     * {@inheritdoc}
     */
    public function transform(Field $field)
    {
        if (!$this->supports($field)) {
            return;
        }
        if ($field->getValue() === null) {
            return;
        }
        if (empty($field->getValue())) {
            return;
        }
        $this->normalize($field);

        return;
    }

    /**
     * Ensure an image field data is correctly structured.
     *
     * @param Field $field
     */
    private function normalize(Field $field)
    {
        $data = $field->getValue();
        if (Json::test($data)) {
            $response = Json::parse($data, true);
            if (empty($response)) {
                return null;
            }
            if (\is_string($response)) {
                $response = ['file' => $response];
            }
        } else {
            $response['file'] = $data;
        }

        $value = MutableBag::from($this->imageDefault)
            ->replace($response)
            ->clean()
            ->toArray()
        ;
        if ($data === Json::dump($value)) {
            // Nothing changed
            return;
        }

        $field->setValue($value);
    }
}
