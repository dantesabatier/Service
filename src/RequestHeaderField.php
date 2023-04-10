<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/** @internal */
readonly class RequestHeaderField
{
    public string $name;
    public string $value;
    /** @var Dictionary<string> */
    public Dictionary $parameters;
    private int $index;

    public function __construct(public string $rawValue)
    {
        unset($this->name);
        unset($this->value);
        unset($this->parameters);
        unset($this->index);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "index" => (int)strpos($this->rawValue, " "),
            "name" => trim(substring_to_index($this->rawValue, $this->index)),
            "value" => trim(substring_from_index($this->rawValue, $this->index)),
            "parameters" => (new ArrayClass(explode(",", $this->value)))->reduce(new Dictionary(), function (Dictionary $result, string $e): Dictionary {
                $components = explode("=", $e, 2);
                $result[trim($components[0])] = count($components) > 1 ? trim($components[1]) : "";
                return $result;
            }),
            default => null
        };
    }
}
