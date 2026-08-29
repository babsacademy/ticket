<?php

use App\Services\EventCoverImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = app(EventCoverImageService::class);
});

test('it stores the image as WebP under events/ with a .webp extension', function () {
    $path = $this->service->store(UploadedFile::fake()->image('cover.jpg'));

    expect($path)->toStartWith('events/')
        ->and($path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($path);
});

test('it converts a PNG source to WebP just as well as a JPG source', function () {
    $path = $this->service->store(UploadedFile::fake()->image('cover.png'));

    $mime = getimagesizefromstring(Storage::disk('public')->get($path))['mime'];

    expect($mime)->toBe('image/webp');
});

test('it scales down an oversized image to 1200px wide, preserving aspect ratio', function () {
    $path = $this->service->store(UploadedFile::fake()->image('cover.jpg', 3000, 1500));

    $size = getimagesizefromstring(Storage::disk('public')->get($path));

    expect($size[0])->toBe(1200)
        ->and($size[1])->toBe(600);
});

test('it does not upscale an image already narrower than the cap', function () {
    $path = $this->service->store(UploadedFile::fake()->image('cover.jpg', 300, 200));

    $size = getimagesizefromstring(Storage::disk('public')->get($path));

    expect($size[0])->toBe(300)
        ->and($size[1])->toBe(200);
});

test('each stored image gets a unique path', function () {
    $first = $this->service->store(UploadedFile::fake()->image('cover.jpg'));
    $second = $this->service->store(UploadedFile::fake()->image('cover.jpg'));

    expect($first)->not->toBe($second);
});

test('delete removes a previously stored image', function () {
    $path = $this->service->store(UploadedFile::fake()->image('cover.jpg'));
    Storage::disk('public')->assertExists($path);

    $this->service->delete($path);

    Storage::disk('public')->assertMissing($path);
});

test('delete is a no-op when there is nothing to delete', function () {
    expect(fn () => $this->service->delete(null))->not->toThrow(Throwable::class);
});
