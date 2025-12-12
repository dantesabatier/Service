<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\ProcessInfo;
use Throwable;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorDomain;

/** @internal */
class Thrower extends Responder
{
    public bool $isDevelopmentMode {
        get => ProcessInfo::processInfo()->environment["APP_ENV"] === "development";
    }
    public Throwable $throwable;
    public Response $response {
        get {
            $request = $this->request;
            $throwable = $this->throwable;
            $localizedFailureReason = $this->isDevelopmentMode ? $throwable->getMessage() : "An internal error occurred.";
            $statusCode = HTTPStatusCode::internalServerError;
            $error = new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedFailureReasonErrorKey => $localizedFailureReason]));
            if ($throwable instanceof InternalInconsistencyException) {
                $error = $throwable->error;
                if ($throwable instanceof InvalidRequestException) {
                    $statusCode = $throwable->getCode();
                }
            }
            $this->statusCode = $statusCode;
            $this->content = json_encode(["error" => $error]);
            $this->headerFields["Content-Type"] = "application/json";
            if ($throwable instanceof UnauthorizedException) {
                $scheme = AuthenticationScheme::tryFrom($request->authorizationHeader->name) ?? AuthenticationScheme::basic;
                $realm = $request->url->host;
                $schemeHeader = match ($scheme) {
                    AuthenticationScheme::digest => sprintf("Digest realm=\"%s\", uri=\"%s\", algorithm=\"SHA-256\", nonce=\"%s\", qop=\"auth\", opaque=\"%s\"", $realm, $request->url->path, ProcessInfo::processInfo()->globallyUniqueString, base64_encode($realm)),
                    AuthenticationScheme::bearer => sprintf("Bearer realm=\"%s\", error=\"%s\", error_description=\"%s\"", $realm, $error->localizedDescription, $error->localizedFailureReason ?? ""),
                    default => sprintf("%s realm=\"%s\"", $scheme->value, $realm),
                };
                $this->headerFields["WWW-Authenticate"] = $schemeHeader;
            }
            return new Response($this);
        }
    }

    public function throw(Throwable $throwable): never
    {
        error_log("[Thrower] " . $throwable);
        $this->throwable = $throwable;
        $this->response->send();
    }
}
