<?php

namespace Sabatier\Service;

use JsonException;
use Sabatier\Foundation\Date;

/**
 * @throws JsonException
 */
function jwt_validate(string $jwt, string $pk, ?string $iss = null): bool
{
    if (!str_contains($jwt, ".")) {
        return false;
    }
    $components = explode(".", $jwt);
    if (count($components) !== 3) {
        return false;
    }
    [$header, $payload, $signature] = $components;
    $unsigned = sprintf("%s.%s", $header, $payload);
    $signed = base64_encode(hash_hmac("sha256", $unsigned, $pk, true));
    if ($signature !== $signed || !($obj = json_decode(base64_decode($payload), null, 512, JSON_THROW_ON_ERROR))) {
        return false;
    }
    $date = new Date();
    return !(((property_exists($obj, "nbf") && $obj->nbf > $date->timeIntervalSinceReferenceDate) || (property_exists($obj, "exp") && $obj->exp < $date->timeIntervalSinceReferenceDate) || (property_exists($obj, "iss") && $obj->iss !== $iss)));
}

/**
 * @throws JsonException
 */
function jwt_encode(object|array $value, string $pk): string
{
    $header = base64_encode(json_encode(["alg" => "HS256", "typ" => "JWT"]));
    $value = base64_encode(json_encode((array)$value, JSON_THROW_ON_ERROR));
    $unsigned = sprintf("%s.%s", $header, $value);
    $signed = base64_encode(hash_hmac("sha256", $unsigned, $pk, true));
    return sprintf("%s.%s", $unsigned, $signed);
}

/**
 * @throws JsonException
 */
function jwt_decode(string $jwt, string $pk, ?string $iss = null, ?bool $validated = null): ?object
{
    $validated ??= jwt_validate($jwt, $pk, $iss);
    if (!$validated) {
        return null;
    }
    [, $payload,] = explode(".", $jwt);
    return json_decode(base64_decode($payload), null, 512, JSON_THROW_ON_ERROR);
}
