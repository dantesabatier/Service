<?php

declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\LocalizationExtractor;
use Sabatier\Service\Responder;

try {
    $bundle = Bundle::bundleForClass(Responder::class);
    $excludedFilenames = new ArrayClass(["DownloadResponseTransformer", "AbstractTool", "PersistentHistoryTool", "JobTool"]);
    $extractor = new LocalizationExtractor($bundle, $bundle->localizations, $excludedFilenames);
    $extractor->extract();
} catch (Exception $exception) {
    error_log("Exception raised $exception");
}
