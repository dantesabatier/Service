<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;
use function Sabatier\Foundation\localized_string;

/** @internal */
final class Downloader extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }
    private URL $downloadURL {
        get {
            if (isset($this->downloadURL)) {
                return $this->downloadURL;
            }
            $parsedBody = $this->request->parameters;
            /** @var string $urlString */
            $urlString = $parsedBody["url"] ?? throw new BadRequestException();
            return $this->downloadURL = new URL($urlString);
        }
    }
    private URL $directoryURL {
        get {
            if (isset($this->directoryURL)) {
                return $this->directoryURL;
            }
            $directoryURL = $this->downloadURL->deletingLastPathComponent();
            $directoryURL->pathComponents->count === 2 ?: throw new BadRequestException(localized_string("The requested location must name one directory and one file."));
            return $this->directoryURL = $directoryURL;
        }
    }
    private DownloadDisposition $downloadDisposition {
        get => $this->downloadDisposition ??= Application::shared()->fileTransferPolicy->evaluateDownload($this->directoryURL->lastPathComponent, $this->downloadURL->lastPathComponent);
    }

    /**
     * Serves one file the request names by subdirectory and filename.
     *
     * The request body is unchanged — `url` still names one location relative to the document root — so a client that already calls this endpoint keeps working. The string is decomposed into a subdirectory and a filename before anything else happens, and only those two names travel onward: {@see FileTransferPolicy} resolves the destination from them, and a name cannot describe anywhere but the directory it sits in. Nothing has to be canonicalized or prefix-checked afterwards, because no path survives the decomposition.
     *
     * The policy's default implementation asks {@see StaticResourcePolicy} about the resolved URL, so a download answers to the same rules a static request does — dotfiles refused, the public and protected distinction respected — instead of deciding on its own.
     *
     * A download always requires authentication: `$isProtectedContentAvailable` stays at the responder's default of `false`, because access is enforced before the action runs and therefore before any file has been named. A file the static policy would serve anonymously is reachable through {@see ResourceManager} anyway.
     *
     * @throws Exception
     */
    #[Action(transformers: [DownloadResponseTransformer::class, NoCacheHeaderTransformer::class])]
    public function download(): void
    {
        $disposition = $this->downloadDisposition;
        /** @var URL $resourceURL */
        $resourceURL = $disposition->isAllowed ? $disposition->resourceURL : throw new NotFoundException($disposition->failureReason ?? localized_string("The requested file is not available."));
        $path = $resourceURL->path;
        $fileManager = FileManager::default();
        $fileManager->isReadableFile($path) ?: throw new MethodNotAllowedException();
        $body = $fileManager->contents($path) ?? throw new InternalServerErrorException();
        $contentType = URLFileTypeMappings::shared()->mimeType($resourceURL->pathExtension);
        if ($contentType && ($encoding = mb_detect_encoding($body))) {
            $contentType .= "; charset=$encoding";
        }
        $contentType ??= "application/octet-stream";
        $this->data = ["body" => $body, "filename" => $resourceURL->lastPathComponent, "contentType" => $contentType];
    }
}
