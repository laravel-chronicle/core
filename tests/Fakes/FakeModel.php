<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

class FakeModel extends Model
{
    protected $table = 'fake_chronicle_models';

    protected $guarded = [];

    public $timestamps = true;
}
