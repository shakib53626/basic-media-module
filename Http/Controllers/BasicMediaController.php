<?php

namespace App\Modules\BasicMedia\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BasicMedia\Repositories\BasicMediaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasicMediaController extends Controller
{
    public function __construct(private BasicMediaRepository $media) {}

    // GET /admin/basic-media?folder=path
    public function index(Request $request): JsonResponse
    {
        $folder = $request->query('folder');
        $items  = $this->media->list($folder);

        return response()->json(['items' => $items]);
    }

    // POST /admin/basic-media/upload
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => ['required', 'file', 'max:51200'],
            'folder' => ['nullable', 'string'],
        ]);

        $item = $this->media->upload($request->file('file'), $request->folder);

        return response()->json(['item' => $item], 201);
    }

    // DELETE /admin/basic-media
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $this->media->delete($request->path);

        return response()->json(['message' => 'Deleted successfully.']);
    }
}
