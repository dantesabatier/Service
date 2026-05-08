<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\Dictionary;

/** Reconstructs an LLMModel from its dictionary representation (as produced by `LLMModel::$dictionaryRepresentation`). */
final class LLMModelBuilder
{
    public static function build(Dictionary $dictionary): LLMModel
    {
        return new LLMModel($dictionary["name"] ?? "", $dictionary["identifier"] ?? "", $dictionary["tier"] ?? "");
    }
}
