<?php

namespace App\Http\Controllers;

use App\Models\ClassroomDocument;
use Illuminate\Support\Facades\Storage;

class ClassroomDocumentAccessController extends Controller
{
    public function open(ClassroomDocument $document)
    {
        $this->authorize('view', $document);
        abort_unless($document->isFile() && $document->disk && $document->path, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return response()->file(Storage::disk($document->disk)->path($document->path), [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$this->safeFilename($document).'"',
        ]);
    }

    public function download(ClassroomDocument $document)
    {
        $this->authorize('view', $document);
        abort_unless($document->isFile() && $document->disk && $document->path, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $this->safeFilename($document));
    }

    private function safeFilename(ClassroomDocument $document): string
    {
        return str_replace(['"', "\r", "\n"], '', $document->original_name ?: $document->title);
    }
}
