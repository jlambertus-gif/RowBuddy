<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure\Images;

use RowBuddy\Transfers\Contracts\ImageMetadataStripper;
use RowBuddy\Transfers\Exceptions\InvalidEvidencePhoto;

/**
 * Uses PHP's GD extension (pure PHP, no framework/Laravel dependency —
 * fully unit-testable standalone), mirroring QueuePresence's own
 * `GdImageMetadataStripper` (Phase 2) exactly. Decoding then re-encoding
 * an image via GD inherently strips all metadata (EXIF, ICC profiles,
 * XMP, GPS tags) — GD's JPEG encoder only ever writes pixel data, never
 * propagates the original file's APPn segments — so no separate
 * EXIF-scrubbing step is needed.
 *
 * Always re-encodes to JPEG regardless of the input format, matching
 * ADR-020 §4's "single photo evidence" scope.
 */
final class GdImageMetadataStripper implements ImageMetadataStripper
{
    private const JPEG_QUALITY = 90;

    public function strip(string $imageContents): string
    {
        // set_error_handler (not the @ operator) so the warning
        // imagecreatefromstring() emits for undecodable content is fully
        // swallowed rather than merely hidden from output — PHPUnit/Pest's
        // own error handler otherwise still records it as a test warning.
        set_error_handler(static fn (): bool => true);
        $image = imagecreatefromstring($imageContents);
        restore_error_handler();

        if ($image === false) {
            throw InvalidEvidencePhoto::notADecodableImage();
        }

        ob_start();
        imagejpeg($image, quality: self::JPEG_QUALITY);
        $stripped = ob_get_clean();
        imagedestroy($image);

        if ($stripped === false) {
            throw InvalidEvidencePhoto::notADecodableImage();
        }

        return $stripped;
    }
}
