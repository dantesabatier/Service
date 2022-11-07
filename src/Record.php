<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;

class Record extends ManagedObject
{
    public function jsonSerialize(): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        $dictionary['objectID'] = $this->objectID->referenceObject;
        $dictionary->merge(parent::jsonSerialize());
        return $dictionary;
    }
}
