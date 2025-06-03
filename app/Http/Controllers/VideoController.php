<?php

namespace App\Http\Controllers;

use App\Models\Video;  
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFProbe;

class VideoController extends Controller
{
    public function index()
    {
        return Video::paginate(10);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:204800', // 200MB max
            'thumbnail' => 'nullable|image|max:10240' // 10MB max
        ]);

        // Store video
        $videoPath = $request->file('video')->store('videos', 'public');
        
        // Store thumbnail
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        // Create video record
        $video = Video::create([
            'title' => $request->title,
            'path' => Storage::url($videoPath),
            'thumbnail' => $thumbnailPath ? Storage::url($thumbnailPath) : null,
            'description' => $request->description,
            'mime_type' => $request->file('video')->getMimeType(),
            'size' => $request->file('video')->getSize(),
            'duration' => $this->getVideoDuration($request->file('video'))
        ]);

        return response()->json($video, 201);
    }

    
    private function getVideoDuration($videoFile)
    {
        try {
            $ffprobe = \FFMpeg\FFProbe::create();
            return $ffprobe->format($videoFile->getRealPath())->get('duration');
        } catch (\Exception $e) {
            \Log::error('Error getting video duration: ' . $e->getMessage());
            return 0;
        }
    }
}
