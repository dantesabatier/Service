<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\ProcessInfo;
use Throwable;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorDomain;

/** @internal */
final class ErrorResponder extends Responder
{
    #[Override]
    public Response $response {
        get {
            try {
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $request = $this->request;
                $throwable = $this->throwable;
                $statusCode = HTTPStatusCode::internalServerError;
                $localizedDescription = HTTPURLResponse::localizedString($statusCode);
                $localizedFailureReason = $this->isDevelopmentMode ? $throwable->getMessage() : "";
                $userInfo = null;
                if ($throwable instanceof InternalInconsistencyException) {
                    if ($throwable instanceof InvalidRequestException) {
                        $statusCode = $throwable->getCode();
                        $localizedDescription = HTTPURLResponse::localizedString($statusCode);
                    }
                    if ($this->isDevelopmentMode) {
                        $localizedDescription = $throwable->error->localizedDescription;
                        $localizedFailureReason = $throwable->error->localizedFailureReason;
                        $userInfo = $throwable->error->userInfo;
                    }
                }
                $body = new Dictionary(["error" => new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedDescriptionKey => $localizedDescription, LocalizedFailureReasonErrorKey => $localizedFailureReason])->merging($userInfo ?? []))]);
                $headerFields = new Dictionary();
                if ($throwable instanceof UnauthorizedException) {
                    $scheme = AuthenticationScheme::tryFrom($request->authorizationHeader->name) ?? AuthenticationScheme::basic;
                    $realm = $request->url->host ?? "";
                    $schemeHeader = match ($scheme) {
                        AuthenticationScheme::digest => sprintf("Digest realm=\"%s\", uri=\"%s\", algorithm=\"SHA-256\", nonce=\"%s\", qop=\"auth\", opaque=\"%s\"", $realm, $request->url->path, ProcessInfo::processInfo()->globallyUniqueString, base64_encode($realm)),
                        AuthenticationScheme::bearer => sprintf("Bearer realm=\"%s\", error=\"%s\", error_description=\"%s\"", $realm, $localizedDescription, $localizedFailureReason ?? ""),
                        default => sprintf("%s realm=\"%s\"", $scheme->value, $realm),
                    };
                    $headerFields["WWW-Authenticate"] = $schemeHeader;
                }
                return new CORSResponseTransformer(
                    new SecurityHeadersTransformer(
                        new ResponseHeaderSanitizerTransformer(
                            new JSONTransformer(
                                new Response($request->url, $statusCode, $headerFields, $body)
                            )->response
                        )->response,
                        $this->transformerContext
                    )->response,
                    $this->transformerContext
                )->response;
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
    private bool $isDevelopmentMode {
        get => $this->isDevelopmentMode ??= $this->environment[ApplicationEnvironmentKey] === ApplicationEnvironmentDevelopment;
    }
    private Throwable $throwable;

    public function handle(Throwable $throwable): never
    {
        error_log("$this->debugDescription $this->request $throwable");
        $this->throwable = $throwable;
        $this->response->send();
    }
}
