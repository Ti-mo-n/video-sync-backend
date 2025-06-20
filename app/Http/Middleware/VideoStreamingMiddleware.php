<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('Video request: ' . $request->fullUrl());
        
        $response = $next($request);
        
        if ($response instanceof BinaryFileResponse) {
            $file = $response->getFile();
            $mime = $file->getMimeType();
            
            if (str_starts_with($mime, 'video/')) {
                Log::info('Streaming video: ' . $file->getPathname());
                
                // Set critical headers for iOS compatibility
                $response->headers->set('Accept-Ranges', 'bytes');
                $response->headers->set('Access-Control-Allow-Origin', '*');
                $response->headers->set('Access-Control-Expose-Headers', 'Content-Length,Content-Range');
                $response->headers->set('Cache-Control', 'public, max-age=604800, must-revalidate');
                
                // iOS requires these for proper seeking
                $response->headers->set('Content-Type', $mime);
                $response->headers->set('Content-Length', $file->getSize());
                
                if ($request->header('Range')) {
                    Log::info('Range request: ' . $request->header('Range'));
                    return $this->handleRangeRequest($file, $request);
                }
            }
        }
        
        return $response;
    }

    protected function handleRangeRequest($file, Request $request)
    {
        $size = $file->getSize();
        $start = 0;
        $end = $size - 1;
        $length = $size;
        
        $range = $request->header('Range');
        if (preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
            $start = (int) $matches[1];
            $end = isset($matches[2]) ? (int) $matches[2] : $size - 1;
            $length = $end - $start + 1;
        }
        
        $headers = [
            'Content-Type' => $file->getMimeType(),
            'Content-Length' => $length,
            'Content-Range' => "bytes $start-$end/$size",
            'Accept-Ranges' => 'bytes',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Length,Content-Range',
            'Cache-Control' => 'public, max-age=604800, must-revalidate',
        ];
        
        Log::info("Streaming range: $start-$end/$size");
        
        return new StreamedResponse(
            function () use ($file, $start, $length) {
                $stream = fopen($file->getPathname(), 'rb');
                fseek($stream, $start);
                
                $remaining = $length;
                $chunkSize = 1024 * 1024; // 1MB chunks
                
                while ($remaining > 0 && !feof($stream)) {
                    $bytesToRead = min($chunkSize, $remaining);
                    $chunk = fread($stream, $bytesToRead);
                    echo $chunk;
                    $remaining -= $bytesToRead;
                    flush();
                    if (connection_aborted()) break;
                }
                
                fclose($stream);
            },
            206, // HTTP Partial Content
            $headers
        );
    }
}