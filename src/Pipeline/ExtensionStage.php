<?php

declare(strict_types=1);

namespace Chronicle\Pipeline;

/**
 * Ordered extension stages executed before Chronicle core processing.
 */
enum ExtensionStage: int
{
    /**
     * Runs first, before any validator. For converting values Chronicle
     * cannot store as they are - value objects, DTOs - into the scalars or
     * arrays an entry should record.
     */
    case PREPARE = 50;

    case VALIDATE = 100;
    case RESOLVE_CONTEXT = 200;
    case POLICY = 300;
    case PROCESS = 400;
}
