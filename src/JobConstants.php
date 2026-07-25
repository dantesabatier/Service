<?php

declare(strict_types=1);

/**
 * Scheduled-job constants and defaults.
 *
 * These constants configure the directory names and defaults used by the
 * job subsystem and are part of its public surface.
 */
namespace Sabatier\Service;

/** @var string Name of the directory under src/ where the app's Job classes are discovered. */
const JobsDirectory = "Jobs";
