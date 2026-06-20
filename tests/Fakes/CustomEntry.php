<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Chronicle\Entry\Entry;

/**
 * Test-only subclass used to prove the config-resolvable entry model.
 * Shares the chronicle_entries table; adds nothing but its own class identity.
 */
class CustomEntry extends Entry {}
