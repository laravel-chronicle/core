<?php

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Pipeline\RunExtensions;
use Illuminate\Support\Carbon;

final class TestEntryExtension implements EntryExtension, PrioritizedEntryExtension
{
    public function __construct(
        public string $name,
        protected ExtensionStage $stage,
        protected int $priority = 0,
    ) {}

    public function stage(): ExtensionStage
    {
        return $this->stage;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        $context = $entry->attribute('context', []);
        $sequence = $context['extension_sequence'] ?? [];
        $sequence[] = $this->name;
        $context['extension_sequence'] = $sequence;

        $entry->setAttribute('context', $context);

        return $entry;
    }
}

function makeExtensionPending(array $overrides = []): PendingEntry
{
    return new PendingEntry(array_merge([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => 'test.event',
        'subject_type' => 'system',
        'subject_id' => 'system',
        'metadata' => [],
        'context' => [],
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ], $overrides));
}

it('orders extensions deterministically by stage and priority', function () {
    $registry = new EntryExtensionRegistry(app());

    $registry->register(new TestEntryExtension('process', ExtensionStage::PROCESS));
    $registry->register(new TestEntryExtension('validate_late', ExtensionStage::VALIDATE, 10));
    $registry->register(new TestEntryExtension('policy', ExtensionStage::POLICY, 0));
    $registry->register(new TestEntryExtension('validate_early', ExtensionStage::VALIDATE, -10));

    $orderedNames = array_map(
        fn (TestEntryExtension $extension): string => $extension->name,
        $registry->ordered()
    );

    expect($orderedNames)->toBe([
        'validate_early',
        'validate_late',
        'policy',
        'process',
    ]);
});

it('is a no-op when no extensions are registered', function () {
    $registry = new EntryExtensionRegistry(app());
    $processor = new RunExtensions($registry);
    $entry = makeExtensionPending();

    $result = $processor->process($entry);

    expect($result)->toBe($entry)
        ->and($result->attribute('context'))->toBe([]);
});

it('runs extensions in deterministic order before core pipeline', function () {
    $registry = new EntryExtensionRegistry(app());
    $processor = new RunExtensions($registry);
    $entry = makeExtensionPending();

    $registry->register(new TestEntryExtension('process', ExtensionStage::PROCESS));
    $registry->register(new TestEntryExtension('validate_2', ExtensionStage::VALIDATE, 2));
    $registry->register(new TestEntryExtension('policy', ExtensionStage::POLICY));
    $registry->register(new TestEntryExtension('validate_1', ExtensionStage::VALIDATE, 1));

    $processor->process($entry);

    expect($entry->attribute('context')['extension_sequence'])->toBe([
        'validate_1',
        'validate_2',
        'policy',
        'process',
    ]);
});
