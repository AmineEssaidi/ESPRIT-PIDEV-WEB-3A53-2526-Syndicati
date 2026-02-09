<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class BlocsToArrayTransformer implements DataTransformerInterface
{
    /**
     * Transforms a string of comma-separated blocs to an array for the form
     * 
     * @param string|null $blocsString (e.g., "A,B,C")
     * @return array (e.g., ['A', 'B', 'C'])
     */
    public function transform($blocsString): array
    {
        if (empty($blocsString)) {
            return [];
        }

        return explode(',', $blocsString);
    }

    /**
     * Transforms an array of blocs back to a comma-separated string for the entity
     * 
     * @param array|null $blocsArray (e.g., ['A', 'B', 'C'])
     * @return string (e.g., "A,B,C")
     */
    public function reverseTransform($blocsArray): string
    {
        if (empty($blocsArray)) {
            return '';
        }

        return implode(',', $blocsArray);
    }
}
