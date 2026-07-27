<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use RowBuddy\QueuePresence\Contracts\ImageMetadataStripper;

/**
 * A fake that returns its input unchanged — application-service tests
 * only need to prove the stripper is called and its output is what gets
 * stored, not GD's actual stripping behavior (that's {@see
 * \RowBuddy\QueuePresence\Tests\Infrastructure\Images\GdImageMetadataStripperTest}'s
 * job).
 */
final class PassthroughMetadataStripper implements ImageMetadataStripper
{
    public function strip(string $imageContents): string
    {
        return $imageContents;
    }
}
