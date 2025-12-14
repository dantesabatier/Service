<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

/** @internal */
class Downloader extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }

    /**
     * @throws Exception
     */
    #[Action(decorators: [DownloadResponseDecorator::class])]
    public function download(): void
    {
        $request = $this->request;
        $parsedBody = $request->parsedBody;
        $urlString = $parsedBody["url"] ?? throw new BadRequestException();
        $attachmentURL = new URL($urlString);
        $fileManager = FileManager::default();
        $resourceURL = new URL($attachmentURL->path, $fileManager->documentRootDirectory)->absoluteURL;
        $path = $resourceURL->path;
        $fileManager->fileExists($path) ?: throw new NotFoundException();
        $fileManager->isReadableFile($path) ?: throw new MethodNotAllowedException();
        $body = $fileManager->contents($path) ?? throw new InternalServerErrorException();
        if (($contentType = URLFileTypeMappings::shared()->mimeType($resourceURL->pathExtension)) && ($encoding = mb_detect_encoding($body))) {
            $contentType .= "; charset=$encoding";
        }
        $this->data = ["body" => $body, "filename" => $resourceURL->lastPathComponent, "contentType" => $contentType];
    }
}
