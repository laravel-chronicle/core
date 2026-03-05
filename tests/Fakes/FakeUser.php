<?php

namespace Chronicle\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;

/**
 * FakeUser model used for Chronicle package tests.
 */
class FakeUser extends Model
{
    protected $table = 'fake_users';

    protected $guarded = [];

    public $timestamps = true;
}
