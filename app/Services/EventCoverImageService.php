<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class EventCoverImageService
{
    /**
     * Cover images are never stored wider than this (proportional resize,
     * never upscaled — a narrower source image is left as-is).
     */
    private const MAX_WIDTH = 1200;

    /**
     * WebP encoding quality (0-100).
     */
    private const WEBP_QUALITY = 85;

    public function __construct(private readonly ImageManager $imageManager)
    {
        //
    }

    /**
     * Convert an uploaded cover image to WebP, resized to at most
     * MAX_WIDTH px wide, and store it on the "public" disk under
     * events/. Returns the stored path (relative to the disk root, e.g.
     * "events/<uuid>.webp") for the Event::cover_image column.
     */
    public function store(UploadedFile $file): string
    {
        $image = $this->imageManager
            ->decode($file)
            ->scaleDown(width: self::MAX_WIDTH);

        $encoded = $image->encode(new WebpEncoder(quality: self::WEBP_QUALITY));

        $path = 'events/'.Str::uuid()->toString().'.webp';

        Storage::disk('public')->put($path, $encoded->toString());

        return $path;
    }

    /**
     * Delete a previously stored cover image, if any.
     */
    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
