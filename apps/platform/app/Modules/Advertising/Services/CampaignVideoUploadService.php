<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Http\Controllers\AdvertisingCreationsController;
use App\Modules\Advertising\Models\VideoAdDurationBounds;
use App\Modules\Advertising\Services\Exceptions\VideoDurationOutOfBoundsException;
use App\Modules\Advertising\Services\Exceptions\VideoDurationUnreadableException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Upload d'une vidéo publicitaire (Lot 4, instruction explicite du
 * fondateur 2026-07-30 : « une vidéo de 30 à 60 secondes », durée
 * configurable en admin — voir {@see VideoAdDurationBounds}). La durée est
 * toujours mesurée par `ffprobe` après stockage, jamais déclarée par le
 * client (même discipline que le calcul d'audience réel, Lot 3). Stockage
 * sur le disque `public` (`storage/app/public`, servi via `public/storage`)
 * — pas de CDN/S3 dans ce lot (aucun n'est configuré, `.env` sans clés
 * AWS), cohérent avec le report déjà écrit dans le code
 * ({@see AdvertisingCreationsController}, « W5 »).
 */
class CampaignVideoUploadService
{
    /**
     * @return array{path: string, url: string, duration_seconds: int}
     *
     * @throws VideoDurationOutOfBoundsException
     * @throws VideoDurationUnreadableException
     */
    public function store(UploadedFile $video): array
    {
        $path = Storage::disk('public')->putFile('campaign-videos', $video);

        if ($path === false) {
            throw new RuntimeException('le stockage du fichier vidéo a échoué');
        }

        $absolutePath = Storage::disk('public')->path($path);

        try {
            $durationSeconds = $this->readDuration($absolutePath);
            $this->assertWithinActiveBounds($durationSeconds);
        } catch (VideoDurationOutOfBoundsException|VideoDurationUnreadableException $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'duration_seconds' => $durationSeconds,
        ];
    }

    /**
     * @throws VideoDurationUnreadableException
     */
    private function readDuration(string $absolutePath): int
    {
        $result = Process::run([
            'ffprobe', '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $absolutePath,
        ]);

        $output = trim($result->output());

        if (! $result->successful() || $output === '' || ! is_numeric($output)) {
            throw new VideoDurationUnreadableException(
                "impossible de déterminer la durée réelle du fichier vidéo (ffprobe) : {$result->errorOutput()}"
            );
        }

        return (int) round((float) $output);
    }

    /**
     * @throws VideoDurationOutOfBoundsException
     */
    private function assertWithinActiveBounds(int $durationSeconds): void
    {
        $bounds = VideoAdDurationBounds::query()->where('state', 'active')->first();

        if ($bounds === null) {
            // Échec fermé : sans borne publiée, aucune vidéo n'est jamais
            // acceptée (même discipline que AudienceSegmentGuard::activeThreshold()).
            throw new RuntimeException('aucune borne de durée vidéo active (Lot 4)');
        }

        if ($durationSeconds < $bounds->min_seconds || $durationSeconds > $bounds->max_seconds) {
            throw new VideoDurationOutOfBoundsException(
                "durée vidéo {$durationSeconds}s hors des bornes actives ({$bounds->min_seconds}-{$bounds->max_seconds}s)"
            );
        }
    }
}
