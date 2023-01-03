<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\HTTPURLResponse;
use Throwable;

/**
 * A set of methods to manage shared behaviors for your app.
 */
interface ApplicationDelegate
{
    /**
     * Tells the delegate that the launch process has begun but that state restoration hasn't occurred.
     * @param Application $application The singleton app object.
     */
    public function applicationWillFinishLaunching(Application $application): void;

    /**
     * Tells the delegate that the launch process is almost done and the app is almost ready to run.
     * @param Application $application The singleton app object.
     */
    public function applicationDidFinishLaunching(Application $application): void;

    public function applicationWillFail(Application $application, HTTPURLResponse $response, Throwable $throwable): View|string|null;

    /**
     * Tells the delegate when the app is about to terminate.
     * @param Application $application The singleton app object.
     */
    public function applicationWillTerminate(Application $application): void;
}
