<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

class FakeActor extends Model
{
    protected $table = 'fake_actors';

    protected $guarded = [];
}
