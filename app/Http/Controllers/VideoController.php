<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use FFMpeg\FFProbe;

class VideoController extends Controller
{
    public function index()
    {
        try {
            Log::info('Fetching video list');
            
            $videos = Video::select([
                'id', 
                'title',
                'path', 
                'thumbnail', 
                'duration',
                'created_at',
                'updated_at'
            ])->get();
            
            return response()->json(
                $videos->map(function ($video) {
                    // Use direct storage path for iOS compatibility
                    $url = url('stream/' . basename($video->path));
                    
                    return [
                        'id' => $video->id,
                        'title' => $video->title,
                        'url' => $url,
                        'thumbnail' => $video->thumbnail ? url($video->thumbnail) : null,
                        'duration' => (float) $video->duration,
                        'created_at' => $video->created_at,
                        'updated_at' => $video->updated_at
                    ];
                })
            );
            
        } catch (\Exception $e) {
            Log::error('Video index error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:204800',
            'thumbnail' => 'nullable|image|max:10240'
        ]);

        try {
            $videoFile = $request->file('video');
            $videoPath = $videoFile->store('videos', 'public');
            $filename = basename($videoPath);
            
            // Create video record
            $video = Video::create([
                'title' => $request->title,
                'path' => Storage::url('videos/' . $filename),
                'filename' => $filename,
                'thumbnail' => $request->hasFile('thumbnail') 
                    ? Storage::url($request->file('thumbnail')->store('thumbnails', 'public'))
                    : null,
                'description' => $request->description,
                'mime_type' => $videoFile->getMimeType(),
                'size' => $videoFile->getSize(),
                'duration' => $this->getVideoDuration($videoFile)
            ]);

            return response()->json([
                'id' => $video->id,
                'title' => $video->title,
                'url' => url('stream/' . $filename),
                'duration' => (float) $video->duration
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Video store error: ' . $e->getMessage());
            return response()->json(['error' => 'Upload failed'], 500);
        }
    }

    private function getVideoDuration($videoFile)
    {
        try {
            $ffprobe = FFProbe::create();
            return (float) $ffprobe->format($videoFile->getRealPath())->get('duration');
        } catch (\Exception $e) {
            Log::error('Duration error: ' . $e->getMessage());
            return 0;
        }
    }
    
   public function stream($filename, Request $request)
{
    $path = storage_path('app/public/videos/' . $filename);

    if (!file_exists($path)) {
        abort(404, 'Video not found');
    }

    $fileSize = filesize($path);
    $start = 0;
    $end = $fileSize - 1;

    if ($request->hasHeader('Range')) {
        preg_match('/bytes=(\d+)-(\d*)/', $request->header('Range'), $matches);
        $start = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $end;
    }

    $length = $end - $start + 1;

    $headers = [
        'Content-Type' => 'video/mp4',
        'Content-Length' => $length,
        'Content-Range' => "bytes $start-$end/$fileSize",
        'Accept-Ranges' => 'bytes',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Expose-Headers' => 'Content-Length, Content-Range',
        'Cache-Control' => 'public, max-age=86400'
    ];

    $response = response()->stream(function () use ($path, $start, $length) {
        $stream = fopen($path, 'rb');
        fseek($stream, $start);
        echo fread($stream, $length);
        fclose($stream);
    }, 206, $headers);

    return $response;
}

}