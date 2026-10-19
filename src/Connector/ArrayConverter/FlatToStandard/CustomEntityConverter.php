<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Connector\ArrayConverter\FlatToStandard;

use Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Akeneo\Tool\Component\Connector\Exception\DataArrayConversionException;
use DanMyers\CustomEntityBundle\Normalizer\Flat\CustomEntityNormalizer;

/**
 * Converts a CSV row to the standard format expected by the updater:
 * "label-<locale>" columns become "labels", other columns are kept as is.
 */
class CustomEntityConverter implements ArrayConverterInterface
{
    public function convert(array $item, array $options = [])
    {
        if (!array_key_exists('code', $item)) {
            throw new DataArrayConversionException('The "code" column is mandatory.');
        }

        $converted = [];
        foreach ($item as $column => $value) {
            $column = (string) $column;
            if (str_starts_with($column, CustomEntityNormalizer::LABEL_PREFIX)) {
                $converted['labels'][substr($column, strlen(CustomEntityNormalizer::LABEL_PREFIX))] = $value;
                continue;
            }
            $converted[$column] = $value;
        }

        return $converted;
    }
}
