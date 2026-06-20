<?php

namespace App\Modules\BasicMedia\Repositories;

use App\Traits\FileHelpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BasicMediaRepository
{
    use FileHelpers;

    private string $disk = 'public';

    // ── List flat file contents of a folder (no recursion) ──────────────────

    public function list(?string $folder = null): array
    {
        $path  = $folder ?? '';
        $files = Storage::disk($this->disk)->files($path);
        $items = [];

        foreach ($files as $file) {
            $name = basename($file);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $size = Storage::disk($this->disk)->size($file);
            $type = $this->getFileType($ext);
            $url  = Storage::disk($this->disk)->url($file);

            $items[] = [
                'id'       => base64_encode($file),
                'type'     => $type,
                'name'     => $name,
                'path'     => $file,
                'parentId' => $folder ? base64_encode($folder) : null,
                'size'     => $size,
                'ext'      => $ext,
                'date'     => date('Y-m-d', Storage::disk($this->disk)->lastModified($file)),
                'url'      => $url,
            ];
        }

        return $items;
    }

    // ── Upload file ──────────────────────────────────────────────────────────

    public function upload(UploadedFile $file, ?string $folder = null): array
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext      = strtolower($file->getClientOriginalExtension());
        $name     = Str::slug($original) . '.' . $ext;
        $path     = $folder ? "{$folder}/{$name}" : $name;

        $counter = 1;
        while (Storage::disk($this->disk)->exists($path)) {
            $name    = Str::slug($original) . "-{$counter}.{$ext}";
            $path    = $folder ? "{$folder}/{$name}" : $name;
            $counter++;
        }

        Storage::disk($this->disk)->putFileAs($folder ?? '', $file, $name);

        $url  = Storage::disk($this->disk)->url($path);
        $size = Storage::disk($this->disk)->size($path);
        $type = $this->getFileType($ext);

        return [
            'id'       => base64_encode($path),
            'type'     => $type,
            'name'     => $name,
            'path'     => $path,
            'parentId' => $folder ? base64_encode($folder) : null,
            'size'     => $size,
            'ext'      => $ext,
            'date'     => now()->format('Y-m-d'),
            'url'      => $url,
        ];
    }

    // ── Delete file ──────────────────────────────────────────────────────────

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }
}
