<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Chronicle\Eloquent\HasChronicle;

class FakeChronicleModel extends FakeModel
{
    use HasChronicle;
}
