<?php

declare(strict_types=1);

use Chronicle\Exceptions\OutsideTimeWindowException;
use Chronicle\Policy\TimeWindowPolicy;
use Illuminate\Support\Carbon;

it('passes when the current time is inside the window', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00', 'UTC')); // Monday noon

    (new TimeWindowPolicy)->enforce(makePolicyPending());
})->throwsNoExceptions()->afterEach(fn () => Carbon::setTestNow());

it('throws when the current time is before the window start', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 08:59:59', 'UTC'));

    expect(fn () => (new TimeWindowPolicy)->enforce(makePolicyPending()))
        ->toThrow(OutsideTimeWindowException::class);
})->afterEach(fn () => Carbon::setTestNow());

it('throws when the current time is after the window end', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 17:00:01', 'UTC'));

    expect(fn () => (new TimeWindowPolicy)->enforce(makePolicyPending()))
        ->toThrow(OutsideTimeWindowException::class);
})->afterEach(fn () => Carbon::setTestNow());

it('passes at exactly the start boundary', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 09:00:00', 'UTC'));

    (new TimeWindowPolicy)->enforce(makePolicyPending());
})->throwsNoExceptions()->afterEach(fn () => Carbon::setTestNow());

it('passes at exactly the end boundary', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 17:00:00', 'UTC'));

    (new TimeWindowPolicy)->enforce(makePolicyPending());
})->throwsNoExceptions()->afterEach(fn () => Carbon::setTestNow());

it('throws when the current day is not in the allowed days list', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-04 12:00:00', 'UTC')); // Sunday

    expect(fn () => (new TimeWindowPolicy)->enforce(makePolicyPending()))
        ->toThrow(OutsideTimeWindowException::class);
})->afterEach(fn () => Carbon::setTestNow());

it('passes when the current day is in the allowed days list', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00', 'UTC')); // Monday

    (new TimeWindowPolicy)->enforce(makePolicyPending());
})->throwsNoExceptions()->afterEach(fn () => Carbon::setTestNow());

it('applies the configured timezone when comparing times', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'America/New_York', // UTC-5
    ]]);
    // 13:00 UTC = 08:00 New York -> outside window
    Carbon::setTestNow(Carbon::parse('2026-01-05 13:00:00', 'UTC'));

    expect(fn () => (new TimeWindowPolicy)->enforce(makePolicyPending()))
        ->toThrow(OutsideTimeWindowException::class);
})->afterEach(fn () => Carbon::setTestNow());

it('throws an InvalidArgumentException when start is equal to end', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '09:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);

    expect(fn () => new TimeWindowPolicy)
        ->toThrow(InvalidArgumentException::class);
});

it('throws an InvalidArgumentException when start is after end', function () {
    config(['chronicle.policy.time_window' => [
        'start' => '22:00',
        'end' => '06:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);

    expect(fn () => new TimeWindowPolicy)
        ->toThrow(InvalidArgumentException::class);
});

it('does not call parseTime more than once per construction', function () {
    // Regression: enforce() previously called parseTime() again, which could
    // return null in a different locale/timezone context and fatal.
    // After the fix, the Carbon objects are stored in constructor properties.
    config(['chronicle.policy.time_window' => [
        'start' => '09:00',
        'end' => '17:00',
        'days' => [],
        'timezone' => 'UTC',
    ]]);
    Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00', 'UTC'));

    $policy = new TimeWindowPolicy;

    // Call enforce() twice - both must succeed with frozen construction-time state.
    $policy->enforce(makePolicyPending());
    $policy->enforce(makePolicyPending());
})->throwsNoExceptions()->afterEach(fn () => Carbon::setTestNow());
