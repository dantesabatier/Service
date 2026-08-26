<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URLResourceKey;

/** @internal */
final class Uploader extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }

    /**
     * Stores the uploaded files under the subdirectory the request names.
     *
     * The request chooses a subdirectory, never a path: {@see FileTransferPolicy} resolves where each file lands and under what name, so nothing arriving from the client can describe a location outside it. The filename needs settling there as much as the directory does — it comes from `$_FILES` and a write never passes through the gate that guards reads.
     *
     * @throws Exception
     */
    #[Action(transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
    public function upload(): void
    {
        $parameters = $this->request->parameters;
        /** @var string $directory */
        $directory = $parameters["directory"] ?? throw new BadRequestException();
        $keys = new Set([URLResourceKey::nameKey]);
        $enumerator = new UploadsEnumerator($directory, $keys);
        !$enumerator->isEmpty ?: throw new BadRequestException();
        /** @var ArrayClass<Dictionary<string>> $files */
        $files = new ArrayClass();
        foreach ($enumerator as $url) {
            $values = $url->resourceValues($keys);
            /** @var string $name */
            $name = $values->name;
            $files->append(new Dictionary([URLResourceKey::nameKey => $name]));
        }
        $this->data = $files;
    }
}
