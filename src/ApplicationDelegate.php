<?php

namespace Sabatier\Service;

/**
 * A set of methods to manage shared behaviors for your app.
 */
interface ApplicationDelegate extends AuthorizationService
{
    /**
     * Tells the delegate that the app's initialization is about to complete.
     * @param Application $application The app object associated with the delegate.
     */
    public function applicationWillFinishLaunching(Application $application): void;

    /**
     * Tells the delegate that the app's initialization is complete but, before it generates the response.
     * @param Application $application The app object associated with the delegate.
     */
    public function applicationDidFinishLaunching(Application $application): void;

    /**
     * Tells the delegate when the app is about to terminate.
     *
     * Your delegate can use this method to perform any final cleanup before the app terminates. The app will terminate after this method returns.
     * @param Application $application The singleton app object.
     */
    public function applicationWillTerminate(Application $application): void;
}
