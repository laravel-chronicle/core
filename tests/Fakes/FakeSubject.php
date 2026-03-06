<?php

namespace Chronicle\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

class FakeSubject extends Model
{
    protected $table = 'fake_subjects';

    protected $guarded = [];
}
