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
     * Tells the delegate that the app’s initialization is about to complete.
     * @param Application $application The app object associated with the delegate.
     */
    public function applicationWillFinishLaunching(Application $application): void;

    /**
     * Tells the delegate that the app’s initialization is complete but before it generates the response.
     * @param Application $application The app object associated with the delegate.
     */
    public function applicationDidFinishLaunching(Application $application): void;

    /** 
     * Provides an opportunity to modify the content of the response about to be sent.
     * @param Application $application The application object associated with the delegate.
     * @param HTTPURLResponse $response The response about to be sent.
     * @param Throwable $throwable The throwable object that was used to construct the response.
     * @return View|string|null The content of the response about to be sent.
     */
    public function applicationWillFail(Application $application, HTTPURLResponse &$response, Throwable $throwable): View|string|null;

    /**
     * Tells the delegate when the app is about to terminate.
     * 
     * Your delegate can use this method to perform any final cleanup before the app terminates. The app will terminate after this method returns.
     * @param Application $application The singleton app object.
     */
    public function applicationWillTerminate(Application $application): void;
}
