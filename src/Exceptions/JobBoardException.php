<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Exceptions;

use Throwable;

/**
 * Marker for every exception thrown by this package and by the connectors
 * built on top of it, so a consumer can catch the whole family at once.
 */
interface JobBoardException extends Throwable {}
