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
    public Throwable $throwable;
    public Response $response {
        get {
            $request = $this->request;
            $throwable = $this->throwable;
            $statusCode = HTTPStatusCode::internalServerError;
            $error = new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedFailureReasonErrorKey => $throwable->getMessage()]));
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
                $this->headerFields["WWW-Authenticate"] = "$scheme->value realm=\"{$request->url->host}\"" . match ($scheme) {
                        AuthenticationScheme::digest => sprintf(", uri=\"%s\", algorithm=\"%s\", nonce=\"%s\", qop=\"%s\", opaque=\"%s\"", $request->url->path, "SHA-256", ProcessInfo::processInfo()->globallyUniqueString, "auth", base64_encode((string)$request->url->host)),
                        AuthenticationScheme::bearer => sprintf(", error=\"%s\", error_description=\"%s\"", $error->localizedDescription, $error->localizedFailureReason ?? ""),
                        default => ""
                    };
            }
            return new Response($this);
        }
    }

    public function throw(Throwable $throwable): never
    {
        $this->throwable = $throwable;
        $this->response->send();
    }
}
