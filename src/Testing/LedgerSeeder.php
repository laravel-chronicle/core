<?php

declare(strict_types=1);

namespace Chronicle\Testing;

use Chronicle\Facades\Chronicle;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Test-only helper that seeds a verifiable Chronicle ledger at useful volume.
 *
 * Records N entries through the real write path (Chronicle::record()->commit())
 * inside a single transaction, so the payload/chain hashes are genuine and the
 * result passes IntegrityVerifier::verify(). Requires the eloquent driver and a
 * configured signing key (both provided by the package TestCase once
 * $this->useEloquentDriver() has been called).
 *
 * Example:
 *   LedgerSeeder::make()->count(1000)->checkpointEvery(100)->seed();
 */
final class LedgerSeeder
{
    protected int $count = 0;

    /** @var string|(Closure(int): string) */
    protected string|Closure $action = 'seed.recorded';

    protected mixed $actor = 'system';

    protected mixed $subject = null;

    public static function make(): self
    {
        return new self;
    }

    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function seed(): SeededLedger
    {
        $connection = Config::get('chronicle.connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;

        DB::connection($connection)->transaction(function (): void {
            for ($i = 1; $i <= $this->count; $i++) {
                Chronicle::record()
                    ->actor($this->actorFor($i))
                    ->action($this->actionFor($i))
                    ->subject($this->subjectFor($i))
                    ->commit();
            }
        });

        return new SeededLedger($this->count, 0, null);
    }

    protected function actionFor(int $i): string
    {
        return $this->action instanceof Closure ? ($this->action)($i) : $this->action;
    }

    protected function actorFor(int $i): mixed
    {
        return $this->actor instanceof Closure ? ($this->actor)($i) : $this->actor;
    }

    protected function subjectFor(int $i): mixed
    {
        if ($this->subject === null) {
            return (object) ['id' => $i];
        }

        return $this->subject instanceof Closure ? ($this->subject)($i) : $this->subject;
    }
}
