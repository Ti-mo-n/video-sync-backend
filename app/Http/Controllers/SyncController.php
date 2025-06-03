<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function createSession()
    {
        $expiry = now()->addMinutes(config('app.qr_expiry', 5));
        
        $session = Session::create(['expires_at' => $expiry]);

        return response()->json([
            'sessionId' => $session->id,
            'expiresAt' => $expiry->toIso8601String()
        ]);
    }

    public function getSession($sessionId)
    {
        $session = Session::findOrFail($sessionId);

        if ($session->expires_at->isPast()) {
            return response()->json(['error' => 'Session expired'], 410);
        }

        return response()->json([
            'videoUrl' => $session->video_url,
            'timestamp' => $session->timestamp
        ]);
    }

    public function updateSession(Request $request, $sessionId)
    {
        $session = Session::findOrFail($sessionId);
        
        $request->validate([
            'videoUrl' => 'required|url',
            'timestamp' => 'required|numeric'
        ]);

        $session->update([
            'video_url' => $request->videoUrl,
            'timestamp' => $request->timestamp
        ]);

        return response()->json(['success' => true]);
    }
}
