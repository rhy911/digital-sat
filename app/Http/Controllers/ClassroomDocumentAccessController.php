<?php

namespace App\Http\Controllers;

use App\Models\ClassroomDocument;
use Illuminate\Support\Facades\Storage;

class ClassroomDocumentAccessController extends Controller
{
    /** Only these render in the browser tab; everything else is forced to download. */
    private const INLINE_SAFE_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    public function open(ClassroomDocument $document)
    {
        $this->authorize('view', $document);
        abort_unless($document->isFile() && $document->disk && $document->path, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        $mime = $document->mime_type ?: 'application/octet-stream';
        $disposition = in_array($mime, self::INLINE_SAFE_MIMES, true) ? 'inline' : 'attachment';

        return response()->file(Storage::disk($document->disk)->path($document->path), [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$this->safeFilename($document).'"',
            'X-Content-Type-Options' => 'nosniff',
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
