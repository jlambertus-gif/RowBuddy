<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use RowBuddy\Transfers\Contracts\ImageMetadataStripper;
use RowBuddy\Transfers\Tests\Infrastructure\Images\GdImageMetadataStripperTest;

/**
 * A fake that returns its input unchanged — application-service tests
 * only need to prove the stripper is called and its output is what gets
 * stored, not GD's actual stripping behavior (that's
 * {@see GdImageMetadataStripperTest}'s
 * job).
 */
final class PassthroughMetadataStripper implements ImageMetadataStripper
{
    public function strip(string $imageContents): string
    {
        return $imageContents;
    }
}
