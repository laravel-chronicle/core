<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * FakeUser model used for Chronicle package tests.
 */
class FakeUser extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'fake_users';

    protected $guarded = [];

    public $timestamps = true;
}
