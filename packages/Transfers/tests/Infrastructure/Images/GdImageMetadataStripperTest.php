<?php

declare(strict_types=1);

use RowBuddy\Transfers\Exceptions\InvalidEvidencePhoto;
use RowBuddy\Transfers\Infrastructure\Images\GdImageMetadataStripper;

function aTestJpegForTransfersEvidence(int $red, int $green, int $blue): string
{
    $image = imagecreatetruecolor(4, 4);
    imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));

    ob_start();
    imagejpeg($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    return $contents;
}

it('returns valid, decodable image bytes with the original pixel data preserved', function () {
    $original = aTestJpegForTransfersEvidence(200, 40, 40);

    $stripped = (new GdImageMetadataStripper)->strip($original);

    $decoded = imagecreatefromstring($stripped);
    expect($decoded)->not->toBeFalse();

    $colors = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
    imagedestroy($decoded);

    expect($colors['red'])->toBeGreaterThan(150)
        ->and($colors['green'])->toBeLessThan(100)
        ->and($colors['blue'])->toBeLessThan(100);
});

it('throws for content that is not a decodable image', function () {
    expect(fn () => (new GdImageMetadataStripper)->strip('this is not an image'))
        ->toThrow(InvalidEvidencePhoto::class);
});

it('throws for empty content', function () {
    expect(fn () => (new GdImageMetadataStripper)->strip(''))
        ->toThrow(InvalidEvidencePhoto::class);
});
