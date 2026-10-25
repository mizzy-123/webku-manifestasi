<?php

namespace App\Console\Commands;

use App\Models\CustomizedPreview;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

#[Signature('previews:cleanup')]
#[Description('Delete expired, unlocked client previews with their uploads, and stale WordPress export leftovers')]
class CleanupExpiredPreviews extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;

        CustomizedPreview::query()
            ->where('expires_at', '<', now())
            ->where('is_locked_by_admin', false)
            ->each(function (CustomizedPreview $preview) use (&$deleted): void {
                Storage::disk('public')->deleteDirectory("custom-uploads/{$preview->unique_code}");
                $preview->delete();
                $deleted++;
            });

        $this->info("{$deleted} preview kedaluwarsa dihapus.");
        $this->info($this->cleanTempExports().' sisa file export dihapus.');

        return self::SUCCESS;
    }

    /**
     * Remove files and folders in temp-exports that are older than one hour.
     */
    private function cleanTempExports(): int
    {
        $directory = storage_path('app/temp-exports');

        if (! File::isDirectory($directory)) {
            return 0;
        }

        $threshold = now()->subHour()->getTimestamp();
        $removed = 0;

        foreach (File::glob($directory.'/*') as $path) {
            if (File::lastModified($path) >= $threshold) {
                continue;
            }

            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
            $removed++;
        }

        return $removed;
    }
}
