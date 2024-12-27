<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;

class Downloader extends Responder
{
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function download(): void
    {
        $body = $this->request->parsedBody;
        $urlString = $body["url"] ?? throw new BadRequestException();
        $attachmentURL = new URL($urlString);
        $fileManager = FileManager::default();
        $resourceURL = new URL($attachmentURL->path, $fileManager->documentRootDirectory)->absoluteURL;
        $path = $resourceURL->path;
        $fileManager->fileExists($path) ?: throw new NotFoundException();
        $fileManager->isReadableFile($path) ?: throw new MethodNotAllowedException();
        $content = $fileManager->contents($path) ?? throw new InternalServerErrorException();
        if (($contentType = URLFileTypeMappings::shared()->mimeType($resourceURL->pathExtension)) && ($encoding = mb_detect_encoding($content))) {
            $contentType .= "; charset=$encoding";
        }
        $this->headerFields["Content-Type"] = $contentType;
        $this->headerFields["Content-Disposition"] = "attachment; filename=\"$resourceURL->lastPathComponent\"";
        $this->content = $content;
    }
}
