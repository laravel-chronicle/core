<?php

namespace Chronicle\Tests\Fakes;

use Chronicle\Eloquent\HasChronicle;
use Illuminate\Database\Eloquent\Model;

class FakeChronicleModel extends Model
{
    use HasChronicle;

    protected $table = 'fake_chronicle_models';

    protected $guarded = [];

    public $timestamps = true;
}
