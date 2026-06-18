<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

class FakeSubject extends Model
{
    protected $table = 'fake_subjects';

    protected $guarded = [];
}
