<?php

namespace Sabatier\Service;

use JsonException;
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
final class ErrorResponder extends Responder
{
    public bool $isDevelopmentMode {
        get => $this->environment["APP_ENV"] === "development";
    }
    public Response $response {
        /**
         * @throws JsonException
         */
        get {
            $request = $this->request;
            $throwable = $this->throwable;
            $statusCode = HTTPStatusCode::internalServerError;
            $error = new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedFailureReasonErrorKey => $this->isDevelopmentMode ? $throwable->getMessage() : sprintf("<%s %s> code: %s", $throwable::class, spl_object_id($throwable), $throwable->getCode())]));
            if ($throwable instanceof InternalInconsistencyException) {
                $error = $throwable->error;
                if ($throwable instanceof InvalidRequestException) {
                    $statusCode = $throwable->getCode();
                }
            }
            $body = new Dictionary(["error" => $error]);
            $headerFields = new Dictionary();
            if ($throwable instanceof UnauthorizedException) {
                $scheme = AuthenticationScheme::tryFrom($request->authorizationHeader->name) ?? AuthenticationScheme::basic;
                $realm = $request->url->host ?? "";
                $schemeHeader = match ($scheme) {
                    AuthenticationScheme::digest => sprintf("Digest realm=\"%s\", uri=\"%s\", algorithm=\"SHA-256\", nonce=\"%s\", qop=\"auth\", opaque=\"%s\"", $realm, $request->url->path, ProcessInfo::processInfo()->globallyUniqueString, base64_encode($realm)),
                    AuthenticationScheme::bearer => sprintf("Bearer realm=\"%s\", error=\"%s\", error_description=\"%s\"", $realm, $error->localizedDescription, $error->localizedFailureReason ?? ""),
                    default => sprintf("%s realm=\"%s\"", $scheme->value, $realm),
                };
                $headerFields["WWW-Authenticate"] = $schemeHeader;
            }
            return new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new JSONDecorator(new Response($request->url, $statusCode, $headerFields, $body))->response)->response, $request, $this->corsPolicy)->response;
        }
    }
    private Throwable $throwable;

    public function handle(Throwable $throwable): never
    {
        error_log("$this->debugDescription $throwable");
        $this->throwable = $throwable;
        $this->response->send();
    }
}
