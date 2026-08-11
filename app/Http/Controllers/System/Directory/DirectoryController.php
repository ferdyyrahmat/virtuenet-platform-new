<?php

namespace App\Http\Controllers\System\Directory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $disk = Storage::disk(config('filesystems.default'));

        $currentPath = trim($request->input('path', ''), '/');
        $rawDirectories = [];
        $rawFiles = [];
        $errorMsg = null;

        try {
            $rawDirectories = $disk->directories($currentPath);
            $rawFiles = $disk->files($currentPath);
        } catch (\Throwable $e) {
            $errorMsg = 'Unable to fetch the storage directory.';
        }

        // Format subdirectories
        $directories = [];
        foreach ($rawDirectories as $dir) {
            $dirName = basename($dir);
            $directories[] = [
                'name' => $dirName,
                'path' => $dir,
            ];
        }

        // Format files (Skip hidden system files like .gitignore, .DS_Store, etc.)
        $files = [];
        foreach ($rawFiles as $file) {
            $fileName = basename($file);
            if (str_starts_with($fileName, '.')) {
                continue;
            }

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $sizeBytes = 0;
            $lastModified = '-';
            try {
                $sizeBytes = $disk->size($file);
                $lastModified = date('Y-m-d H:i:s', $disk->lastModified($file));
            } catch (\Throwable $ex) {}

            $files[] = [
                'name'          => $fileName,
                'path'          => $file,
                'extension'     => $ext,
                'size_formatted'=> $this->formatBytes($sizeBytes),
                'last_modified' => $lastModified,
                'icon'          => $this->getFileIcon($ext),
            ];
        }

        // Generate Breadcrumbs
        $breadcrumbs = [];
        if (!empty($currentPath)) {
            $parts = explode('/', $currentPath);
            $accumulated = '';
            foreach ($parts as $p) {
                $accumulated = trim($accumulated . '/' . $p, '/');
                $breadcrumbs[] = [
                    'name' => $p,
                    'path' => $accumulated
                ];
            }
        }

        return view('admin.directory.index', compact(
            'directories',
            'files',
            'currentPath',
            'breadcrumbs',
            'errorMsg'
        ));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // max 100MB
            'path' => 'nullable|string',
        ]);

        $disk = Storage::disk(config('filesystems.default'));
        $targetPath = trim($request->input('path', ''), '/');
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        $destination = !empty($targetPath) ? $targetPath . '/' . $fileName : $fileName;
        $disk->putFileAs($targetPath, $file, $fileName);

        audit_log("Uploaded file {$fileName} to directory {$targetPath}", 'create', 'directory');

        return response()->json([
            'success'  => true,
            'message'  => "File {$fileName} uploaded successfully to directory!",
            'redirect' => route('admin.directory.index', ['path' => $targetPath])
        ]);
    }

    public function makeFolder(Request $request)
    {
        $request->validate([
            'folder_name' => 'required|string|max:100',
            'path'        => 'nullable|string',
        ]);

        $disk = Storage::disk(config('filesystems.default'));
        $targetPath = trim($request->input('path', ''), '/');
        $folderName = trim($request->input('folder_name'), '/');

        $newFolderPath = !empty($targetPath) ? $targetPath . '/' . $folderName : $folderName;
        $disk->makeDirectory($newFolderPath);

        audit_log("Created folder {$folderName} in directory {$targetPath}", 'create', 'directory');

        return response()->json([
            'success'  => true,
            'message'  => "Folder '{$folderName}' created successfully!",
            'redirect' => route('admin.directory.index', ['path' => $targetPath])
        ]);
    }

    public function download(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $path = $request->input('path');
        $disk = Storage::disk(config('filesystems.default'));

        if (!$disk->exists($path)) {
            abort(404, 'File not found on storage.');
        }

        audit_log("Downloaded file {$path} from directory", 'download', 'directory');
        return $disk->download($path, basename($path));
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'type' => 'required|in:file,folder'
        ]);

        $path = $request->input('path');
        $type = $request->input('type');
        $disk = Storage::disk(config('filesystems.default'));

        if ($type === 'folder') {
            $disk->deleteDirectory($path);
        } else {
            $disk->delete($path);
        }

        $parentPath = dirname($path) === '.' ? '' : dirname($path);
        audit_log("Deleted {$type} {$path} from directory", 'delete', 'directory');

        return response()->json([
            'success'  => true,
            'message'  => ucfirst($type) . " deleted successfully.",
            'redirect' => route('admin.directory.index', ['path' => $parentPath])
        ]);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getFileIcon(string $ext): string
    {
        return match ($ext) {
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => 'mdi-file-image-outline text-success',
            'pdf' => 'mdi-file-pdf-box text-danger',
            'doc', 'docx' => 'mdi-file-word-box text-primary',
            'xls', 'xlsx', 'csv' => 'mdi-file-excel-box text-success',
            'zip', 'rar', '7z', 'tar', 'gz' => 'mdi-folder-zip-outline text-warning',
            'mp4', 'mkv', 'avi', 'mov' => 'mdi-file-video-outline text-danger',
            'mp3', 'wav', 'ogg' => 'mdi-file-music-outline text-info',
            'php', 'js', 'html', 'css', 'json', 'sql', 'py' => 'mdi-file-code-outline text-info',
            default => 'mdi-file-outline text-muted',
        };
    }
}
