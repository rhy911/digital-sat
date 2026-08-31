<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClassroomDocumentController extends Controller
{
    public function store(Request $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        $validated = $request->validate([
            'source_type' => 'required|in:file,link',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:2000',
            // Streaming-ZIP OOXML exports (e.g. Google Slides) are detected as
            // application/octet-stream, so `mimes:` alone rejects valid .pptx files.
            'document_file' => [
                'required_if:source_type,file',
                'file',
                'extensions:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,png,jpg,jpeg,webp',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,application/zip,application/octet-stream,image/png,image/jpeg,image/webp',
                'max:20480',
            ],
            'external_url' => 'required_if:source_type,link|nullable|url|starts_with:http://,https://|max:2000',
        ]);

        $payload = [
            'classroom_id' => $classroom->id,
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'source_type' => $validated['source_type'],
        ];

        if ($validated['source_type'] === 'file') {
            $file = $request->file('document_file');
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(32).($extension ? '.'.$extension : '');
            $path = $file->storeAs('classroom-documents/'.$classroom->ulid, $filename, 'local');

            $payload += [
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ];
        } else {
            $payload['external_url'] = $validated['external_url'];
        }

        ClassroomDocument::create($payload);

        return back()->with('success', 'Study document added.');
    }

    public function destroy(Classroom $classroom, ClassroomDocument $document)
    {
        abort_unless((int) $document->classroom_id === (int) $classroom->id, 404);
        $this->authorize('manage', $document);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        // Keep the file while the soft-deleted row is in the recycle bin.
        // RecycleBinService removes it only after the retention period ends.
        $document->delete();

        return back()->with('success', 'Study document removed.');
    }
}
