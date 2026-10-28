<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Chronicle\Eloquent\HasChronicle;
use Chronicle\Tests\Fakes\Casts\FakeStatus;
use Chronicle\Tests\Fakes\Casts\ObjectReturningDatetimeCast;
use Illuminate\Database\Eloquent\Model;

class FakeCastModel extends Model
{
    use HasChronicle;

    protected $table = 'fake_cast_models';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'settings' => 'array',
            'published_at' => 'datetime',
            'status' => FakeStatus::class,
            'secret' => 'encrypted',
            'normalized_at' => ObjectReturningDatetimeCast::class,
        ];
    }
}
