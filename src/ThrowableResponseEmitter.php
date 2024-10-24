<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\ProcessInfo;
use Throwable;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorDomain;

/** @internal */
readonly class ThrowableResponseEmitter extends ResponseEmitter
{
    public function __construct(Throwable $throwable)
    {
        $statusCode = HTTPStatusCode::internalServerError;
        $error = new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedFailureReasonErrorKey => $throwable->getMessage()]));
        if ($throwable instanceof InternalInconsistencyException) {
            $error = $throwable->error;
            if ($throwable instanceof InvalidRequestException) {
                $statusCode = $throwable->getCode();
            }
        }
        $application = Application::shared();
        $error = $application->delegate?->applicationWillPresentError($application, $error) ?? $error;
        $content = json_encode(["error" => $error]);
        $request = $application->request;
        $scheme = $application->authentication->scheme;
        $headerFields = $application->headerFields;
        $headerFields["Content-Type"] = "application/json";
        if ($throwable instanceof UnauthorizedException) {
            $headerFields["WWW-Authenticate"] = "$scheme->value realm=\"{$request->url->host}\"" . match ($scheme) {
                    AuthenticationScheme::digest => sprintf(", uri=\"%s\", algorithm=\"%s\", nonce=\"%s\", qop=\"%s\", opaque=\"%s\"", $request->url->path, "SHA-256", ProcessInfo::processInfo()->globallyUniqueString, "auth", base64_encode((string)$request->url->host)),
                    AuthenticationScheme::bearer => sprintf(", error=\"%s\", error_description=\"%s\"", $error->localizedDescription, $error->localizedFailureReason ?? ""),
                    default => ""
                };
        }
        $response = new HTTPURLResponse($request->url, $statusCode, headerFields: $headerFields);
        parent::__construct($response, $content);
    }
}
