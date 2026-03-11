<?php

namespace Chronicle\Pipeline;

/**
 * Ordered extension stages executed before Chronicle core processing.
 */
enum ExtensionStage: int
{
    case VALIDATE = 100;
    case RESOLVE_CONTEXT = 200;
    case POLICY = 300;
    case PROCESS = 400;
}
