<?php

use Chronicle\ChronicleServiceProvider;
use Chronicle\Contracts\SigningProvider;

beforeEach(function () {
    config()->set('chronicle.signing.enforce_on_boot', true);
});

it('throws in non-testing environments when signing provider cannot be resolved', function () {
    $app = Mockery::mock();
    $app->shouldReceive('environment')->once()->with('testing')->andReturn(false);
    $app->shouldReceive('make')->once()->with(SigningProvider::class)->andThrow(new InvalidArgumentException('bad signing config'));

    $provider = new class($app) extends ChronicleServiceProvider
    {
        public function runSigningSanityCheck(): void
        {
            $this->assertSigningConfiguration();
        }
    };

    expect(fn () => $provider->runSigningSanityCheck())
        ->toThrow(\RuntimeException::class, 'Invalid Chronicle signing configuration');
});

it('passes in non-testing environments when signing provider resolves', function () {
    $signer = Mockery::mock(SigningProvider::class);

    $app = Mockery::mock();
    $app->shouldReceive('environment')->once()->with('testing')->andReturn(false);
    $app->shouldReceive('make')->once()->with(SigningProvider::class)->andReturn($signer);

    $provider = new class($app) extends ChronicleServiceProvider
    {
        public function runSigningSanityCheck(): void
        {
            $this->assertSigningConfiguration();
        }
    };

    $provider->runSigningSanityCheck();

    expect(true)->toBeTrue();
});

it('skips signing sanity check in testing environment', function () {
    $app = Mockery::mock();
    $app->shouldReceive('environment')->once()->with('testing')->andReturn(true);
    $app->shouldNotReceive('make');

    $provider = new class($app) extends ChronicleServiceProvider
    {
        public function runSigningSanityCheck(): void
        {
            $this->assertSigningConfiguration();
        }
    };

    $provider->runSigningSanityCheck();

    expect(true)->toBeTrue();
});

it('skips signing sanity check in non-testing environment when enforcement is disabled', function () {
    config()->set('chronicle.signing.enforce_on_boot', false);

    $app = Mockery::mock();
    $app->shouldNotReceive('environment');
    $app->shouldNotReceive('make');

    $provider = new class($app) extends ChronicleServiceProvider
    {
        public function runSigningSanityCheck(): void
        {
            $this->assertSigningConfiguration();
        }
    };

    try {
        $provider->runSigningSanityCheck();
        expect(true)->toBeTrue();
    } finally {
        config()->set('chronicle.signing.enforce_on_boot', true);
    }
});
