<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;

trait FormErrorHelperTrait
{
    /**
     * Extracts errors from a form into a flat array of "Label: Message" strings.
     * Use this for unified AJAX error reporting.
     */
    protected function getFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin->getName();
            $errors[$fieldName] = $error->getMessage();
        }
        return $errors;
    }
}
