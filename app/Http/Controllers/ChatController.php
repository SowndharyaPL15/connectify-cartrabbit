<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

use App\Traits\AutoCorrectTrait;


class ChatController extends Controller
{
    use AutoCorrectTrait;
    /**
     * Show chat home (sidebar + empty state or open conversation).
     */
    public function index()
    {
        $authUser  = Auth::user();
        $conversations = $this->getConversationsList($authUser);
        $currentContact = null;

        return view('chat.index', compact('conversations', 'authUser', 'currentContact'));
    }

    /**
     * Open a specific conversation mapping (like from search or contacts)
     * We convert a User click into fetching/creating their 1-on-1 conversation.
     */
    public function showUserChat(User $user)
    {
        $authUser = Auth::user();

        // Find or create 1-on-1 conversation exactly between authUser and $user
        $conversation = Conversation::where('is_group', false)
            ->whereHas('users', fn($q) => $q->where('user_id', $authUser->id))
            ->whereHas('users', fn($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([$authUser->id, $user->id]);
        }

        return redirect()->route('chat.conversation', $conversation->id);
    }

    /**
     * Open a specific Conversation (1-on-1 or Group).
     */
    public function showConversation(Conversation $conversation)
    {
        $authUser = Auth::user();

        if (!$conversation->users->contains($authUser->id)) {
            abort(403, 'You are not a participant of this conversation.');
        }

        $conversations = $this->getConversationsList($authUser);

        // Mark incoming messages as read (simple approach)
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $authUser->id)
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);

        $messages = $conversation->messages()
            ->with('sender', 'replyTo', 'replyTo.sender')
            ->where(function ($query) use ($authUser) {
                // Placeholder for future query filters
            })
            ->orderBy('created_at')
            ->get();

        // Manually filter deleted messages for auth user because SQLite JSON queries are tricky
        $messages = $messages->filter(function ($msg) use ($authUser) {
            $deletedBy = $msg->deleted_by ?? [];
            return !in_array($authUser->id, $deletedBy);
        });

        $currentContact = null;
        $isBlocked = false;
        $hasBlocked = false;
        $otherUser = null;

        if (!$conversation->is_group) {
            $otherUser = $conversation->users->where('id', '!=', $authUser->id)->first();
            if ($otherUser) {
                $currentContact = \App\Models\Contact::where('user_id', $authUser->id)
                    ->where('contact_id', $otherUser->id)
                    ->first();
                
                $isBlocked = $authUser->isBlockedBy($otherUser->id);
                $hasBlocked = $authUser->hasBlocked($otherUser->id);
            }
        }

        return view('chat.index', compact('conversations', 'authUser', 'messages', 'conversation', 'currentContact', 'isBlocked', 'hasBlocked', 'otherUser'));
    }

    /**
     * Send a message (AJAX).
     */
    public function send(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'body'            => 'nullable|string|max:5000',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'reply_to_id'     => 'nullable|exists:messages,id',
        ]);

        $authUser = Auth::user();
        $conversation = Conversation::findOrFail($request->conversation_id);

        // Check for blocks
        if (!$conversation->is_group) {
            $otherUser = $conversation->users->where('id', '!=', $authUser->id)->first();
            if ($otherUser) {
                if ($authUser->hasBlocked($otherUser->id)) {
                    return response()->json(['error' => 'You have blocked this user. Unblock them to send messages.'], 403);
                }
                if ($otherUser->fresh()->hasBlocked($authUser->id)) {
                    return response()->json(['error' => 'This user has blocked you.'], 403);
                }
            }
        }

        $type = 'text';
        $imagePath = null;

        if ($request->hasFile('image')) {
            $type = 'image';
            $file = $request->file('image');
            $filename = time() . '_' . $authUser->id . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/messages'), $filename);
            $imagePath = $filename;
        }

        if (!$request->body && !$imagePath) {
            return response()->json(['error' => 'Message cannot be empty.'], 422);
        }

        $body = $request->body ? $this->autoCorrect($request->body) : null;

        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'sender_id'       => $authUser->id,
            'body'            => $body,
            'image_path'      => $imagePath,
            'type'            => $type,
            'status'          => 'sent',
            'reply_to_id'     => $request->reply_to_id,
        ]);

        $message->load('sender', 'replyTo', 'replyTo.sender');

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message, $authUser->id),
        ]);
    }

    /**
     * Update a message (AJAX).
     */
    public function updateMessage(Request $request, Message $message)
    {
        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $authUser = Auth::user();

        if ($message->sender_id !== $authUser->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($message->type !== 'text') {
            return response()->json(['error' => 'Cannot edit non-text messages.'], 400);
        }

        $body = $this->autoCorrect($request->body);

        $message->update([
            'body'      => $body,
            'is_edited' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message, $authUser->id),
        ]);
    }

    /**
     * Delete a message for me (AJAX).
     */
    public function deleteForMe(Request $request, Message $message)
    {
        $authUser = Auth::user();
        
        $deletedBy = $message->deleted_by ?? [];
        if (!in_array($authUser->id, $deletedBy)) {
            $deletedBy[] = $authUser->id;
            $message->update(['deleted_by' => $deletedBy]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Toggle star on a message (AJAX).
     */
    public function toggleStar(Message $message)
    {
        $authUser = Auth::user();
        $starredBy = $message->starred_by ?? [];

        if (in_array($authUser->id, $starredBy)) {
            $starredBy = array_values(array_diff($starredBy, [$authUser->id]));
            $starred = false;
        } else {
            $starredBy[] = $authUser->id;
            $starred = true;
        }

        $message->update(['starred_by' => $starredBy]);

        return response()->json(['success' => true, 'starred' => $starred]);
    }

    /**
     * Toggle pin on a message (AJAX).
     */
    public function togglePin(Message $message)
    {
        $authUser = Auth::user();
        
        // Verify user is part of the conversation
        if (!$message->conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->update(['is_pinned' => !$message->is_pinned]);

        return response()->json(['success' => true, 'pinned' => $message->is_pinned]);
    }

    /**
     * Toggle favorite on a message (AJAX).
     */
    public function toggleFavorite(Message $message)
    {
        $authUser = Auth::user();
        $favoritedBy = $message->favorited_by ?? [];

        if (in_array($authUser->id, $favoritedBy)) {
            $favoritedBy = array_values(array_diff($favoritedBy, [$authUser->id]));
            $favorited = false;
        } else {
            $favoritedBy[] = $authUser->id;
            $favorited = true;
        }

        $message->update(['favorited_by' => $favoritedBy]);

        return response()->json(['success' => true, 'favorited' => $favorited]);
    }

    /**
     * Get message info — read/delivered status (AJAX).
     */
    public function messageInfo(Message $message)
    {
        $authUser = Auth::user();

        if (!$message->conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->load('sender');

        return response()->json([
            'success' => true,
            'info' => [
                'id'         => $message->id,
                'body'       => $message->body,
                'type'       => $message->type,
                'status'     => $message->status,
                'sender'     => $message->sender->name,
                'sent_at'    => $message->created_at->format('M j, Y g:i A'),
                'is_pinned'  => $message->is_pinned,
                'is_starred' => in_array($authUser->id, $message->starred_by ?? []),
                'is_favorited' => in_array($authUser->id, $message->favorited_by ?? []),
                'is_edited'  => $message->is_edited,
            ],
        ]);
    }

    /**
     * Forward a message to another conversation (AJAX).
     */
    public function forwardMessage(Request $request, Message $message)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        $authUser = Auth::user();
        $targetConv = Conversation::findOrFail($request->conversation_id);

        if (!$targetConv->users->contains($authUser->id)) {
            return response()->json(['error' => 'You are not part of that conversation.'], 403);
        }

        $newMessage = Message::create([
            'conversation_id'   => $request->conversation_id,
            'sender_id'         => $authUser->id,
            'body'              => $message->body,
            'image_path'        => $message->image_path,
            'type'              => $message->type,
            'status'            => 'sent',
            'forwarded_from_id' => $message->id,
        ]);

        $newMessage->load('sender');

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($newMessage, $authUser->id),
        ]);
    }

    public function react(Request $request, Message $message)
    {
        $request->validate([
            'emoji' => 'required|string',
        ]);

        $authUser = Auth::user();
        if (!$message->conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $existing = \App\Models\MessageReaction::where('message_id', $message->id)
            ->where('user_id', $authUser->id)
            ->first();

        if ($existing && $existing->emoji === $request->emoji) {
            $existing->delete();
            $reacted = false;
        } else {
            \App\Models\MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $authUser->id],
                ['emoji' => $request->emoji]
            );
            $reacted = true;
        }

        return response()->json([
            'success'   => true,
            'reacted'   => $reacted,
            'reactions' => $this->getMessageReactionsSummary($message, $authUser->id),
        ]);
    }

    private function getMessageReactionsSummary(Message $message, $userId)
    {
        $reactions = $message->reactions()->with('user')->get();
        $summary = [];
        $userReaction = null;

        foreach ($reactions as $r) {
            if (!isset($summary[$r->emoji])) {
                $summary[$r->emoji] = 0;
            }
            $summary[$r->emoji]++;
            if ($r->user_id === $userId) {
                $userReaction = $r->emoji;
            }
        }

        return [
            'summary' => $summary,
            'userReaction' => $userReaction,
        ];
    }

    /**
     * Poll for new messages (AJAX).
     */
    /**
     * Mark the current user as typing in a conversation (AJAX).
     */
    public function setTyping(Conversation $conversation)
    {
        $authUser = Auth::user();
        if (!$conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Set a short-lived cache key for 5 seconds
        Cache::put("typing:{$conversation->id}:{$authUser->id}", true, 5);

        return response()->json(['success' => true]);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $authUser = Auth::user();
        if (!$conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $lastId = (int) $request->query('last_id', 0);

        $messages = $conversation->messages()
            ->where('id', '>', $lastId)
            ->with('sender', 'replyTo', 'replyTo.sender')
            ->orderBy('created_at')
            ->get();

        // Mark incoming as read
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $authUser->id)
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);

        // --- Fetch Reactions for recent messages ---
        // To sync reactions in real-time without massive overhead, 
        // we'll return reactions for the last 50 messages of this conversation.
        $recentMessages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
        
        $reactionsMap = [];
        foreach ($recentMessages as $rm) {
            $reactionsMap[$rm->id] = $this->getMessageReactionsSummary($rm, $authUser->id);
        }

        // Update own online status
        $authUser->update(['status' => 'online', 'last_seen' => now()]);

        // --- Typing Status ---
        $typingUsers = [];
        foreach ($conversation->users as $user) {
            if ($user->id === $authUser->id) continue;
            if (Cache::has("typing:{$conversation->id}:{$user->id}")) {
                $typingUsers[] = $user->name;
            }
        }
        
        $isTypingMessage = '';
        if (count($typingUsers) > 0) {
            $isTypingMessage = implode(', ', $typingUsers) . (count($typingUsers) === 1 ? ' is typing...' : ' are typing...');
        }

        // --- Chat Status ---
        $chatStatus = $isTypingMessage;
        if (empty($chatStatus) && !$conversation->is_group) {

            $otherUser = $conversation->users->where('id', '!=', $authUser->id)->first();
            if ($otherUser) {
                $chatStatus = $otherUser->fresh()->last_seen_text;
            }
        }

        return response()->json([
            'messages'    => $messages->map(fn($m) => $this->formatMessage($m, $authUser->id)),
            'chat_status' => $chatStatus,
            'reactions'   => $reactionsMap,
        ]);
    }

    /**
     * Create a Group Conversation.
     */
    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'user_ids' => 'required|array|min:1', // At least one other person
            'user_ids.*' => 'exists:users,id',
        ]);

        $authUser = Auth::user();
        
        $conversation = Conversation::create([
            'name' => $request->name,
            'is_group' => true,
        ]);

        // Attach everyone
        $conversation->users()->attach($authUser->id, ['role' => 'admin']);
        foreach ($request->user_ids as $id) {
            if ($id != $authUser->id) {
                $conversation->users()->attach($id, ['role' => 'member']);
            }
        }

        return response()->json(['success' => true, 'conversation_id' => $conversation->id]);
    }

    /**
     * Update group metadata (AJAX).
     */
    public function updateGroup(Request $request, Conversation $conversation)
    {
        if (!$conversation->is_group) {
            return response()->json(['error' => 'Not a group conversation.'], 400);
        }

        $authUser = Auth::user();
        if (!$conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $conversation->update([
            'name'        => $request->name ?: $conversation->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success'     => true,
            'name'        => $conversation->name,
            'description' => $conversation->description,
        ]);
    }

    /**
     * Exit/Leave a group conversation (AJAX).
     */
    public function exitGroup(Conversation $conversation)
    {
        if (!$conversation->is_group) {
            return response()->json(['error' => 'Not a group conversation.'], 400);
        }

        $authUser = Auth::user();
        if (!$conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Detach the user
        $conversation->users()->detach($authUser->id);

        // If no users left, delete the conversation
        if ($conversation->users()->count() === 0) {
            $conversation->messages()->delete();
            $conversation->delete();
        }

        return response()->json(['success' => true]);
    }

    public function toggleBlock(User $user)
    {
        $authUser = Auth::user();
        
        if ($authUser->hasBlocked($user->id)) {
            $authUser->blockedUsers()->detach($user->id);
            $blocked = false;
        } else {
            $authUser->blockedUsers()->attach($user->id);
            $blocked = true;
        }

        return response()->json(['success' => true, 'blocked' => $blocked]);
    }

    /**
     * Clear all messages in a conversation for the current user (AJAX).
     */
    public function clearConversation(Conversation $conversation)
    {
        $authUser = Auth::user();

        if (!$conversation->users->contains($authUser->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()->get();

        foreach ($messages as $message) {
            $deletedBy = $message->deleted_by ?? [];
            if (!in_array($authUser->id, $deletedBy)) {
                $deletedBy[] = $authUser->id;
                $message->update(['deleted_by' => $deletedBy]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Export conversation as a text file.
     */
    public function exportConversation(Conversation $conversation)
    {
        $authUser = Auth::user();

        if (!$conversation->users->contains($authUser->id)) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        // Filter out messages deleted by this user
        $messages = $messages->filter(function ($msg) use ($authUser) {
            $deletedBy = $msg->deleted_by ?? [];
            return !in_array($authUser->id, $deletedBy);
        });

        $content = "Chat Export - " . ($conversation->is_group ? $conversation->name : "Direct Chat") . "\n";
        $content .= "Exported on: " . now()->format('M j, Y g:i A') . "\n";
        $content .= "--------------------------------------------------\n\n";

        foreach ($messages as $msg) {
            $time = $msg->created_at->format('M j, Y g:i A');
            $sender = $msg->sender->name;
            $body = $msg->type === 'image' ? '[Photo]' : $msg->body;
            $content .= "[$time] $sender: $body\n";
        }

        $filename = "chat_export_" . $conversation->id . "_" . now()->format('YmdHis') . ".txt";

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }

    public function searchUsers(Request $request)
    {
        $q = $request->query('q', '');
        $users = User::where('id', '!=', Auth::id())
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%$q%")
                      ->orWhere('email', 'like', "%$q%");
            })
            ->select('id', 'name', 'email', 'profile_photo', 'status')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'avatar'       => $u->profile_photo_url,
                'status'       => $u->status,
            ]);

        return response()->json($users);
    }

    // ── Private / Format Helpers ──────────────────────────────────────────────────

    private function getConversationsList($authUser): array
    {
        $list = [];
        $contacts = Contact::where('user_id', $authUser->id)->get()->keyBy('contact_id');
        
        $conversations = $authUser->conversations()->with('users', 'lastMessage')->get();

        foreach ($conversations as $conv) {
            $displayName = $conv->name;
            $avatarUrl = asset('assets/group.png'); // Default group icon

            if (!$conv->is_group) {
                $otherUser = $conv->users->where('id', '!=', $authUser->id)->first();
                if ($otherUser) {
                    $alias = $contacts->has($otherUser->id) ? $contacts[$otherUser->id]->alias_name : null;
                    $displayName = $alias ?: $otherUser->name;
                    $avatarUrl = $otherUser->profile_photo_url;
                }
            }

            $unreadCount = Message::where('conversation_id', $conv->id)
                ->where('sender_id', '!=', $authUser->id)
                ->where('status', '!=', 'read')
                ->count();

            $list[] = [
                'conversation' => $conv,
                'display_name' => $displayName,
                'avatar'       => $avatarUrl,
                'last_message' => $conv->lastMessage,
                'unread_count' => $unreadCount,
            ];
        }

        // Sort by last message time
        usort($list, function ($a, $b) {
            $aTime = $a['last_message']?->created_at?->timestamp ?? 0;
            $bTime = $b['last_message']?->created_at?->timestamp ?? 0;
            return $bTime <=> $aTime;
        });

        return $list;
    }

    private function formatMessage(Message $message, int $authUserId): array
    {
        $replyData = null;
        if ($message->replyTo) {
            $replyData = [
                'id'     => $message->replyTo->id,
                'body'   => $message->replyTo->body,
                'type'   => $message->replyTo->type,
                'sender' => $message->replyTo->sender ? $message->replyTo->sender->name : 'Unknown',
            ];
        }

        return [
            'id'              => $message->id,
            'body'            => $message->body,
            'type'            => $message->type,
            'image_url'       => $message->image_url,
            'status'          => $message->status,
            'time'            => $message->time,
            'date_label'      => $message->date_label,
            'is_mine'         => $message->sender_id === $authUserId,
            'is_edited'       => $message->is_edited,
            'is_pinned'       => $message->is_pinned,
            'is_starred'      => in_array($authUserId, $message->starred_by ?? []),
            'is_favorited'    => in_array($authUserId, $message->favorited_by ?? []),
            'is_forwarded'    => $message->forwarded_from_id !== null,
            'reply_to'        => $replyData,
            'reactions'       => $this->getMessageReactionsSummary($message, $authUserId),
            'sender'          => [
                'id'   => $message->sender->id,
                'name' => $message->sender->name,
            ],
        ];
    }
}
