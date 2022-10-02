<?php

/** @noinspection PhpUnused */

namespace Sabatier\Service;

use Sabatier\Foundation\Date;

use function Sabatier\Foundation\string_compare;
use function Sabatier\Foundation\string_contains;

function jwt_compare(string $wt1, string $wt2): int
{
    return string_compare($wt1, $wt2);
}

function jwt_validate(string $jwt, string $pk, ?string $iss = null): bool
{
    $isValid = false;
    if (string_contains($jwt, '.')) {
        $components = explode('.', $jwt);
        if (count($components) == 3) {
            [$header, $payload, $signature] = $components;
            $unsigned = sprintf("%s.%s", $header, $payload);
            $signed = base64_encode(hash_hmac('sha256', $unsigned, $pk, true));
            $isValid = $signature == $signed;
            if ($isValid && ($obj = json_decode(base64_decode($payload)))) {
                $date = new Date();
                $isValid = !(((property_exists($obj, 'nbf') && $obj->nbf > $date->timeIntervalSinceReferenceDate) || (property_exists($obj, 'exp') && $obj->exp < $date->timeIntervalSinceReferenceDate) || (property_exists($obj, 'iss') && $obj->iss !== $iss)));
            }
        }
    }
    return $isValid;
}

function jwt_generate(array $payload, string $pk): string
{
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode($payload));
    $unsigned = sprintf("%s.%s", $header, $payload);
    $signed = base64_encode(hash_hmac('sha256', $unsigned, $pk, true));
    return sprintf("%s.%s", $unsigned, $signed);
}

function jwt_payload(string $jwt, string $pk, ?string $iss = null, ?bool $validated = null): ?object
{
    $validated ??= jwt_validate($jwt, $pk, $iss);
    if ($validated) {
        [, $payload,] = explode('.', $jwt);
        return json_decode(base64_decode($payload));
    }
    return null;
}
