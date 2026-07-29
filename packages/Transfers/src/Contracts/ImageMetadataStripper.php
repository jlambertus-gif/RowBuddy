<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

use RowBuddy\Transfers\Exceptions\InvalidEvidencePhoto;

/**
 * Domain-facing port for removing metadata (EXIF, GPS tags embedded by
 * the camera, etc.) from a submitted handoff evidence photo before it is
 * stored, per "remove metadata from uploaded images where appropriate"
 * and ADR-020 §4. Transfers' own copy of QueuePresence's identical port
 * (ADR-020 §1's "each consuming module gets its own copy" discipline),
 * not a shared interface.
 */
interface ImageMetadataStripper
{
    /**
     * @throws InvalidEvidencePhoto if the content is not a decodable image
     */
    public function strip(string $imageContents): string;
}
