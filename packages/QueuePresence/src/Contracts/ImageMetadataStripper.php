<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\Exceptions\InvalidEvidencePhoto;

/**
 * Domain-facing port for removing metadata (EXIF, GPS tags embedded by
 * the camera, etc.) from an uploaded evidence photo before it is stored,
 * per "remove metadata from uploaded images where appropriate".
 */
interface ImageMetadataStripper
{
    /**
     * @throws InvalidEvidencePhoto if the content is not a decodable image
     */
    public function strip(string $imageContents): string;
}
