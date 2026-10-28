<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes\Casts;

enum FakeStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
}
