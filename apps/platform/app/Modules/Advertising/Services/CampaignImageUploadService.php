<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Services\Exceptions\ImageOrientationRefusedException;
use App\Modules\Advertising\Services\Exceptions\ImageUnreadableException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Upload d'une image publicitaire (Lot 6, instruction explicite du
 * fondateur 2026-07-30 : format vertical façon TikTok). Les dimensions
 * réelles sont toujours mesurées par `getimagesize()` après stockage,
 * jamais déclarées par le client (même discipline que la durée vidéo
 * mesurée par `ffprobe`, {@see CampaignVideoUploadService}). Portrait ou
 * carré acceptés (hauteur >= largeur), jamais un ratio exact imposé —
 * décision explicite du fondateur, pas une valeur inventée. Stockage sur
 * le disque `public`, comme la vidéo — pas de CDN/S3 dans ce lot.
 */
class CampaignImageUploadService
{
    /**
     * @return array{path: string, url: string, width: int, height: int}
     *
     * @throws ImageUnreadableException
     * @throws ImageOrientationRefusedException
     */
    public function store(UploadedFile $image): array
    {
        $path = Storage::disk('public')->putFile('campaign-images', $image);

        if ($path === false) {
            throw new RuntimeException('le stockage du fichier image a échoué');
        }

        $absolutePath = Storage::disk('public')->path($path);

        try {
            [$width, $height] = $this->readDimensions($absolutePath);
            $this->assertPortraitOrSquare($width, $height);
        } catch (ImageUnreadableException|ImageOrientationRefusedException $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     *
     * @throws ImageUnreadableException
     */
    private function readDimensions(string $absolutePath): array
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new ImageUnreadableException(
                'impossible de décoder le fichier comme une image réelle (getimagesize)'
            );
        }

        return [$info[0], $info[1]];
    }

    /**
     * @throws ImageOrientationRefusedException
     */
    private function assertPortraitOrSquare(int $width, int $height): void
    {
        if ($width > $height) {
            throw new ImageOrientationRefusedException(
                "image {$width}x{$height} refusée : format paysage, un format vertical (portrait ou carré) est requis"
            );
        }
    }
}
