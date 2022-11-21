<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPURLResponse;
use Throwable;

interface ApplicationDelegate
{
    public function applicationWillFinishLaunching(Application $application): void;

    public function applicationWillFail(Application $application, HTTPURLResponse $response, Throwable $throwable): View|string|null;

    public function applicationWillTerminate(Application $application): void;
}
