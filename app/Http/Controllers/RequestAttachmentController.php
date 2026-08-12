<?php

namespace App\Http\Controllers;

use App\Models\RequestAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestAttachmentController extends Controller
{
    public function __invoke(RequestAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->request);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
