<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ServiceRequestAttachmentService
{
    public function store(ServiceRequest $request, User $user, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if ($request->attachments()->where('original_name', $file->getClientOriginalName())->where('size', $file->getSize())->exists()) {
                continue;
            }

            $path = $file->store('request-attachments/'.$request->id, 'local');
            try {
                $request->attachments()->create([
                    'uploaded_by' => $user->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        }
    }
}
