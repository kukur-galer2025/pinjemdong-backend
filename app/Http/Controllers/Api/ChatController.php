<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\User;
use App\Events\ChatMessageSent;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Get chat history or contacts list
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            // Admin: If target user_id is provided, fetch history. 
            // Else fetch list of unique users they've chatted with.
            if ($request->has('user_id')) {
                $targetId = $request->user_id;
                $chats = Chat::with(['sender', 'receiver'])
                    ->where(function($q) use ($user, $targetId) {
                        $q->where('sender_id', $user->id)->where('receiver_id', $targetId);
                    })
                    ->orWhere(function($q) use ($user, $targetId) {
                        $q->where('sender_id', $targetId)->where('receiver_id', $user->id);
                    })
                    ->orderBy('created_at', 'asc')
                    ->get();
                return response()->json($chats);
            } else {
                // Get list of users the admin has chatted with or who messaged the admin
                $userIds = Chat::where('receiver_id', $user->id)->pluck('sender_id')
                    ->concat(Chat::where('sender_id', $user->id)->pluck('receiver_id'))
                    ->unique();
                
                $contacts = User::whereIn('id', $userIds)->get();
                return response()->json($contacts);
            }
        } else {
            // Regular User: Chat history with Admin
            $admin = User::where('role', 'admin')->first();
            if (!$admin) return response()->json([]);

            $chats = Chat::with(['sender', 'receiver'])
                ->where(function($q) use ($user, $admin) {
                    $q->where('sender_id', $user->id)->where('receiver_id', $admin->id);
                })
                ->orWhere(function($q) use ($user, $admin) {
                    $q->where('sender_id', $admin->id)->where('receiver_id', $user->id);
                })
                ->orderBy('created_at', 'asc')
                ->get();
            return response()->json($chats);
        }
    }

    /**
     * Send a new message
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'message' => 'required|string',
            'receiver_id' => $user->role === 'admin' ? 'required|exists:users,id' : 'nullable'
        ]);

        $receiverId = null;
        if ($user->role === 'admin') {
            $receiverId = $request->receiver_id;
        } else {
            $admin = User::where('role', 'admin')->first();
            if (!$admin) {
                return response()->json(['message' => 'Admin tidak ditemukan'], 404);
            }
            $receiverId = $admin->id;
        }

        $chat = Chat::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        $chat->load(['sender', 'receiver']);

        // Broadcast event to receiver
        broadcast(new ChatMessageSent($chat))->toOthers();

        // Also broadcast to the sender's channel if they are using multiple tabs
        // But for simplicity, we just send to receiver's channel.

        return response()->json([
            'message' => 'Pesan terkirim',
            'chat' => $chat
        ], 201);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request)
    {
        $user = $request->user();
        $senderId = $request->sender_id;

        Chat::where('receiver_id', $user->id)
            ->where('sender_id', $senderId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Pesan telah dibaca']);
    }
}
