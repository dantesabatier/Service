<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
final class Downloader extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }

    /**
     * @throws Exception
     */
    #[Action(transformers: [DownloadResponseTransformer::class])]
    public function download(): void
    {
        $parsedBody = $this->request->parameters;
        /** @var string $urlString */
        $urlString = $parsedBody["url"] ?? throw new BadRequestException();
        $attachmentURL = new URL($urlString);
        $fileManager = FileManager::default();
        $resourceURL = new URL($attachmentURL->path, $fileManager->documentRootDirectory)->absoluteURL;
        $path = $resourceURL->path;
        $fileManager->fileExists($path) ?: throw new NotFoundException("The requested URL was not found on this server $resourceURL");
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
