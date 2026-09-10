<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes;

use Illuminate\Queue\SqsQueue;

/**
 * An SqsQueue that can be built without the AWS SDK.
 *
 * SqsQueue::getQueueableOptions() decides whether a queue is FIFO and derives the
 * MessageGroupId / MessageDeduplicationId SQS requires, without ever touching the
 * SQS client. Skipping the parent constructor - whose signature needs
 * Aws\Sqs\SqsClient - lets those assertions run against the real framework code
 * with no aws-sdk-php dependency, while still initialising every property that
 * getQueueableOptions() reads.
 */
final class FakeSqsQueue extends SqsQueue
{
    public function __construct(
        string $default = 'default',
        string $prefix = 'https://sqs.eu-west-2.amazonaws.com/1234567890',
        string $suffix = '',
        string $connectionName = 'sqs',
    ) {
        $this->default = $default;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->connectionName = $connectionName;
    }
}
