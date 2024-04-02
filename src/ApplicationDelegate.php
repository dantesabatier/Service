<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Error;

/**
 * A set of methods to manage shared behaviors for your app.
 */
interface ApplicationDelegate
{
    /**
     * Tells the delegate that the app's initialization is about to complete.
     * @param Application $application The app object associated with the delegate.
     */
    public function applicationWillFinishLaunching(Application $application): void;

    /**
     * Tells the delegate that the app's initialization is complete but before it generates the response.
     * @param Application $application The app object associated with the delegate.
     */
    public function applicationDidFinishLaunching(Application $application): void;

    /**
     * Returns an error for the app to display to the user.
     * @param Application $application The application object associated with the delegate.
     * @param Error $error The error object that is used to construct the error message. Your implementation of this method can return a new Error object or the same one in this parameter.
     * @return Error The error object to display.
     */
    public function applicationWillPresentError(Application $application, Error $error): Error;

    /**
     * Tells the delegate when the app is about to terminate.
     *
     * Your delegate can use this method to perform any final cleanup before the app terminates. The app will terminate after this method returns.
     * @param Application $application The singleton app object.
     */
    public function applicationWillTerminate(Application $application): void;
}
