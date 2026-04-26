<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Throwable;

/**
 * A set of lifecycle callbacks invoked by `Application` at key points in its run loop.
 *
 * Implement this interface in the class declared as `principalClass` in the application
 * bundle's `Info.plist`. The `Application` singleton discovers and instantiates it automatically.
 *
 * ## Lifecycle order
 * 1. `applicationWillFinishLaunching` — called after the persistent container is ready but before
 *    access control is enforced. Use this to register defaults, configure services, or override
 *    framework-level policies (e.g. `PersistentStore::$rowCacheClass`, `Application::$cachePolicy`).
 * 2. `applicationDidFinishLaunching` — called after the response has been produced and is about to
 *    be sent. Use this for post-response bookkeeping.
 * 3. `applicationWillTerminate` — called during the PHP shutdown sequence when no fatal error
 *    was detected.
 * 4. `applicationDidCrash` — called during the PHP shutdown sequence when a fatal error is
 *    detected. Use this to log the throwable or notify an error tracker.
 *
 * @see Application
 */
interface ApplicationDelegate
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

    /**
     * Called when the application terminates due to a fatal error or
     * an unrecoverable exception.
     *
     * This method is invoked exactly once, during the shutdown phase,
     * before any error response is emitted and before the process
     * terminates.
     *
     * The provided Throwable represents the primary cause of the crash.
     * It is not wrapped, transformed, or normalized in any way. The full
     * stack trace and original context are preserved.
     *
     * This method is observational only.
     *
     * Implementations MUST NOT:
     * - Throw exceptions
     * - Attempt to recover from the error
     * - Emit HTTP responses or write output
     * - Modify application state or control flow
     *
     * Implementations MAY:
     * - Log the error
     * - Send alerts or notifications
     * - Collect metrics or diagnostics
     *
     * The application will terminate immediately after this method
     * completes. Termination is guaranteed regardless of any actions
     * taken inside this hook.
     *
     * @param Application $application The running application instance.
     * @param Throwable $throwable The primary cause of the crash.
     */
    public function applicationDidCrash(Application $application, Throwable $throwable): void;
}
