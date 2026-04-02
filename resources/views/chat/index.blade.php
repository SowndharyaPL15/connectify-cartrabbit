@extends('layouts.app')
@section('title', isset($user) ? $user->name : 'Chats')

@section('content')
<div class="chat-app" id="chatApp">

    {{-- ══════════════ SIDEBAR ══════════════ --}}
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-user">
                <a href="{{ route('profile') }}" class="sidebar-avatar-link">
                    <img src="{{ $authUser->profile_photo_url }}" alt="{{ $authUser->name }}" class="avatar avatar-md">
                </a>
                <span class="sidebar-user-name">{{ $authUser->name }}</span>
            </div>
            <div class="sidebar-actions">
                <button class="icon-btn" id="newGroupBtn" title="New Group">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </button>
                <button class="icon-btn" id="searchToggle" title="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </button>
                <a href="{{ route('profile') }}" class="icon-btn" title="Profile">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                </a>
                <form action="{{ route('logout') }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="icon-btn" title="Logout">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="search-bar" id="searchBar" style="display:none">
            <div class="search-input-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="searchInput" placeholder="Search or start new chat" autocomplete="off">
            </div>
            <div id="searchResults" class="search-results" style="display:none"></div>
        </div>

        <div class="chat-list" id="chatList">
        <div class="chat-list" id="chatList">
            @forelse($conversations as $item)
            <a href="{{ route('chat.conversation', $item['conversation']->id) }}"
               class="chat-item @if(isset($conversation) && $conversation->id === $item['conversation']->id) active @endif"
               data-conv-id="{{ $item['conversation']->id }}">
                <div class="chat-item-avatar">
                    <img src="{{ $item['avatar'] }}" alt="{{ $item['display_name'] }}" class="avatar avatar-md">
                    {{-- Status online dot for 1-on-1 chats omitted for simplicity --}}
                </div>
                <div class="chat-item-body">
                    <div class="chat-item-top">
                        <span class="chat-item-name">{{ $item['display_name'] }}</span>
                        @if($item['last_message'])
                            <span class="chat-item-time">{{ $item['last_message']->time }}</span>
                        @endif
                    </div>
                    <div class="chat-item-bottom">
                        <span class="chat-item-preview">
                            @if($item['last_message'])
                                @if($item['last_message']->type === 'image') 📷 Photo
                                @else {{ Str::limit($item['last_message']->body, 35) }}
                                @endif
                            @else
                                <em>No messages yet</em>
                            @endif
                        </span>
                        @if($item['unread_count'] > 0)
                            <span class="unread-badge">{{ $item['unread_count'] }}</span>
                        @endif
                    </div>
                </div>
            </a>
            @empty
            <div class="empty-state-small"><p>No chats found.</p></div>
            @endforelse
        </div>
    </aside>

    {{-- ══════════════ CHAT WINDOW ══════════════ --}}
    <main class="chat-window" id="chatWindow">
        @if(isset($conversation))
        @php
            $activeMeta = collect($conversations)->firstWhere('conversation.id', $conversation->id);
            $displayName = $activeMeta ? $activeMeta['display_name'] : $conversation->name;
            $avatar = $activeMeta ? $activeMeta['avatar'] : asset('assets/group.png');
        @endphp

        <div class="chat-header">
            <button class="icon-btn mobile-back" id="mobileBack">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            </button>
            <div class="chat-header-user">
                <img src="{{ $avatar }}" alt="{{ $displayName }}" class="avatar avatar-sm">
                <div class="chat-header-info">
                    <span class="chat-header-name">{{ $displayName }}</span>
                    <span class="chat-header-status" id="chatStatus">
                        {{ $conversation->is_group ? 'Group Chat' : 'Online' }}
                    </span>
                </div>
            </div>
            <div class="chat-header-actions">
                @if(!$conversation->is_group)
                    <button class="icon-btn" id="contactActionBtn" title="{{ $currentContact ? 'Edit Contact' : 'Add to Contacts' }}">
                        @if($currentContact)
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        @else
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        @endif
                    </button>
                @else
                    <button class="icon-btn" id="groupInfoBtn" title="Group Info">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </button>
                @endif
                
                <button class="icon-btn theme-toggle-btn" id="themeToggleBtn" title="Toggle Theme">
                    <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>

                <div class="header-more-wrap">
                    <button class="icon-btn" id="headerMoreBtn" title="More">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                    <div class="header-dropdown" id="headerDropdown">
                        <button class="dropdown-item" id="chatInfoLink">ℹ️ {{ $conversation->is_group ? 'Group Info' : 'Contact Info' }}</button>
                        <button class="dropdown-item" id="mediaLink">🖼️ Media, links and docs</button>
                        <button class="dropdown-item" id="exportChatBtn">📤 Export chat</button>
                        @if($conversation->is_group)
                            <button class="dropdown-item" id="exitGroupBtn" style="color:#FF6B6B">🚪 Exit Group</button>
                        @else
                            <button class="dropdown-item" id="blockUserBtn" style="color:#FF6B6B" data-user-id="{{ $otherUser->id }}">🛡️ {{ $hasBlocked ? 'Unblock User' : 'Block User' }}</button>
                        @endif
                        <button class="dropdown-item" id="clearChatBtn" style="color:#FF6B6B">🗑️ Clear chat</button>
                    </div>
                </div>
            </div>


        </div>

        <div class="messages-area" id="messagesArea">
            <div class="messages-container" id="messagesContainer">
                @php $lastDate = null; @endphp
                @foreach($messages as $message)
                    @if($message->date_label !== $lastDate)
                        <div class="date-divider"><span>{{ $message->date_label }}</span></div>
                        @php $lastDate = $message->date_label; @endphp
                    @endif
                    @php
                        $isMine = $message->sender_id === $authUser->id;
                        $isStarred = in_array($authUser->id, $message->starred_by ?? []);
                        $isFavorited = in_array($authUser->id, $message->favorited_by ?? []);
                    @endphp
                    <div class="message-wrap {{ $isMine ? 'mine' : 'theirs' }}" data-id="{{ $message->id }}">
                        <div class="bubble">
                            @if(!$isMine && $conversation->is_group)
                                <div class="bubble-sender">{{ $message->sender->name }}</div>
                            @endif
                            @if($message->forwarded_from_id)
                                <div class="bubble-forwarded">⤳ Forwarded</div>
                            @endif
                            @if($message->replyTo)
                                <div class="bubble-reply-context" data-reply-id="{{ $message->replyTo->id }}">
                                    <span class="reply-context-name">{{ $message->replyTo->sender->name ?? 'Unknown' }}</span>
                                    <span class="reply-context-text">{{ $message->replyTo->type === 'image' ? '📷 Photo' : Str::limit($message->replyTo->body, 60) }}</span>
                                </div>
                            @endif
                            @if($message->type === 'image' && $message->image_url)
                                <a href="{{ $message->image_url }}" target="_blank">
                                    <img src="{{ $message->image_url }}" alt="Image" class="bubble-image">
                                </a>
                            @endif
                            @if($message->body)
                                <p class="bubble-text">{{ $message->body }}</p>
                            @endif
                            <div class="bubble-meta">
                                @if($message->is_pinned)
                                    <span class="bubble-indicator" title="Pinned">📌</span>
                                @endif
                                @if($isStarred)
                                    <span class="bubble-indicator" title="Starred">⭐</span>
                                @endif
                                @if($isFavorited)
                                    <span class="bubble-indicator" title="Favorited">❤️</span>
                                @endif
                                @if($message->is_edited)
                                    <span style="margin-right:4px;">(edited)</span>
                                @endif
                                <span class="bubble-time">{{ $message->time }}</span>
                                @if($isMine)
                                <span class="bubble-status {{ $message->status }}">
                                    @if($message->status === 'read')
                                        <svg width="16" height="11" viewBox="0 0 16 11" fill="none"><path d="M1 5.5L5 9.5L15 1.5" stroke="#53BDEB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 1.5L7 6" stroke="#53BDEB" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    @else
                                        <svg width="16" height="11" viewBox="0 0 16 11" fill="none"><path d="M1 5.5L5 9.5L15 1.5" stroke="#8696A0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @endif
                                </span>
                                @endif
                            </div>
                            <div class="bubble-menu">
                                <button class="bubble-menu-btn">&#8964;</button>
                                <div class="bubble-dropdown">
                                    <button class="dropdown-item reply-msg" data-id="{{ $message->id }}" data-body="{{ Str::limit($message->body, 60) }}" data-sender="{{ $message->sender->name }}">↩️ Reply</button>
                                    <button class="dropdown-item forward-msg" data-id="{{ $message->id }}">➡️ Forward</button>
                                    @if($isMine && $message->type === 'text')
                                    <button class="dropdown-item edit-msg" data-id="{{ $message->id }}" data-body="{{ $message->body }}">✏️ Edit</button>
                                    @endif
                                    <button class="dropdown-item star-msg" data-id="{{ $message->id }}">{{ $isStarred ? '⭐ Unstar' : '⭐ Star' }}</button>
                                    <button class="dropdown-item pin-msg" data-id="{{ $message->id }}">{{ $message->is_pinned ? '📌 Unpin' : '📌 Pin' }}</button>
                                    <button class="dropdown-item fav-msg" data-id="{{ $message->id }}">{{ $isFavorited ? '❤️ Unfavorite' : '❤️ Favorite' }}</button>
                                    <button class="dropdown-item info-msg" data-id="{{ $message->id }}">ℹ️ Message Info</button>
                                <button class="dropdown-item delete-msg" data-id="{{ $message->id }}" style="color:#FF6B6B">🗑️ Delete for me</button>
                                </div>
                            </div>

                            @php $rSum = $message->reactionsSummary($authUser->id); @endphp
                            <div class="bubble-reactions {{ empty($rSum['summary']) ? 'hidden' : '' }}" data-id="{{ $message->id }}">
                                @foreach($rSum['summary'] as $emoji => $count)
                                    <span class="reaction-badge {{ $rSum['userReaction'] === $emoji ? 'mine' : '' }}" data-emoji="{{ $emoji }}">
                                        {{ $emoji }} <span class="reaction-count">{{ $count > 1 ? $count : '' }}</span>
                                    </span>
                                @endforeach
                            </div>

                            <button class="reaction-trigger-btn" data-id="{{ $message->id }}" title="React">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Tone Popup --}}
        <div class="tone-popup" id="tonePopup" style="display:none">
            <div class="tone-popup-header">
                <span>✨ AI Suggestions</span>
                <button class="tone-close" id="toneClose">✕</button>
            </div>
            <div class="tone-corrected" id="toneCorrected"></div>
            <div class="tone-list" id="toneList"></div>
        </div>

        {{-- Input Area --}}
        <div class="input-area" style="position:relative;">
            {{-- Translation Bar --}}
            <div class="translation-bar" id="translationBar" style="display:none">
                <div class="trans-controls">
                    <select id="transLang" class="trans-select">
                        <option value="English">English</option>
                        <option value="Spanish">Spanish</option>
                        <option value="French">French</option>
                        <option value="German">German</option>
                        <option value="Italian">Italian</option>
                        <option value="Portuguese">Portuguese</option>
                        <option value="Russian">Russian</option>
                        <option value="Chinese">Chinese</option>
                        <option value="Japanese">Japanese</option>
                        <option value="Korean">Korean</option>
                        <option value="Arabic">Arabic</option>
                        <option value="Hindi">Hindi</option>
                    </select>
                    <div class="trans-mode">
                        <label class="switch">
                            <input type="checkbox" id="transContinuous">
                            <span class="slider round"></span>
                        </label>
                        <span class="mode-label">Continuous</span>
                    </div>
                </div>
                <button class="trans-btn" id="transBtn">Translate</button>
            </div>

            {{-- Emoji Picker Panel --}}
            <div id="emojiPickerWrap" class="emoji-picker-panel" style="display:none;">
                <div class="emoji-picker-header">
                    <div class="emoji-search-wrap">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" id="emojiSearch" placeholder="Search emoji" autocomplete="off">
                    </div>
                </div>
                <div class="emoji-tabs" id="emojiTabs">
                    <button class="emoji-tab active" data-category="smileys" title="Smileys">😀</button>
                    <button class="emoji-tab" data-category="people" title="People">👋</button>
                    <button class="emoji-tab" data-category="nature" title="Nature">🐶</button>
                    <button class="emoji-tab" data-category="food" title="Food">🍕</button>
                    <button class="emoji-tab" data-category="activities" title="Activities">⚽</button>
                    <button class="emoji-tab" data-category="travel" title="Travel">🚗</button>
                    <button class="emoji-tab" data-category="objects" title="Objects">💡</button>
                    <button class="emoji-tab" data-category="symbols" title="Symbols">❤️</button>
                </div>
                <div class="emoji-grid-container" id="emojiGridContainer">
                    <div class="emoji-grid" id="emojiGrid"></div>
                </div>
            </div>

            <div class="image-preview-wrap" id="imagePreviewWrap" style="display:none">
                <img src="" alt="Preview" id="imagePreview">
                <button class="remove-image" id="removeImage">✕</button>
            </div>
            {{-- Reply Preview Bar --}}
            <div class="reply-preview" id="replyPreview" style="display:none">
                <div class="reply-preview-content">
                    <span class="reply-preview-name" id="replyPreviewName"></span>
                    <span class="reply-preview-text" id="replyPreviewText"></span>
                </div>
                <button class="reply-preview-close" id="replyPreviewClose">✕</button>
            </div>
            <div class="input-row">
                @if($isBlocked)
                    <div class="blocked-notice">This user has blocked you.</div>
                @elseif($hasBlocked)
                    <div class="blocked-notice">You have blocked this user. <button id="unblockBannerBtn" class="link-btn" data-user-id="{{ $otherUser->id }}">Unblock</button></div>
                @else
                    <button class="icon-btn" id="emojiBtn" title="Emoji">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                    </button>
                    <label for="imageInput" class="icon-btn attach-btn" title="Attach image">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                    </label>
                    <input type="file" id="imageInput" accept="image/*" style="display:none">
                    <div class="message-input-wrap">
                        <textarea id="messageInput" placeholder="Type a message" rows="1" onkeydown="handleInputKey(event)"></textarea>
                    </div>
                    <button class="icon-btn enhance-btn" id="enhanceBtn" title="AI Enhance">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                    </button>
                    <button class="icon-btn translate-toggle-btn" id="translateToggleBtn" title="AI Translate">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
                    </button>
                    <button class="send-btn" id="sendBtn" title="Send">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                @endif
            </div>
        </div>

        <input type="hidden" id="conversationId" value="{{ $conversation->id }}">
        <input type="hidden" id="lastMessageId" value="{{ $messages->last()?->id ?? 0 }}">

        @else
        <div class="chat-empty">
            <div class="chat-empty-inner">
                <div class="chat-empty-icon">
                    <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                        <circle cx="40" cy="40" r="40" fill="#25D366" opacity="0.1"/>
                        <path d="M40 16C26.745 16 16 26.745 16 40c0 4.5 1.232 8.7 3.366 12.3L16 64l11.7-3.333A23.86 23.86 0 0040 64c13.255 0 24-10.745 24-24S53.255 16 40 16z" fill="#25D366" opacity="0.6"/>
                    </svg>
                </div>
                <h2>ChatApp Web</h2>
                <p>Select a conversation from the left to start chatting.</p>
                <div class="e2e-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Your messages are end-to-end encrypted
                </div>
            </div>
        </div>
        @endif
    </main>

    {{-- Information Sidebar (Hidden by default) --}}
    @if(isset($conversation))
    <aside class="chat-info-sidebar" id="groupInfoSidebar">
        <div class="info-header">
            <button class="icon-btn" id="closeGroupInfo">✕</button>
            <h3>{{ $conversation->is_group ? 'Group Info' : 'Contact Info' }}</h3>
        </div>
        <div class="info-body">
            <div class="info-avatar-section">
                <img src="{{ $avatar }}" class="info-avatar">
                <div class="info-name-wrap">
                    <h2 id="infoGroupName">{{ $displayName }}</h2>
                    @if($conversation->is_group)
                        <button class="edit-icon-btn" id="editGroupNameBtn">🖊️</button>
                    @endif
                </div>
                @if(!$conversation->is_group)
                    <p class="info-status-text" style="color: var(--wa-green); margin-top: 5px;">{{ $conversation->users->where('id', '!=', $authUser->id)->first()->status ?? 'Available' }}</p>
                @endif
            </div>

            @if($conversation->is_group)
            <div class="info-section">
                <div class="info-section-header">
                    <h4>Description</h4>
                    <button class="edit-link" id="editGroupDescBtn">Edit</button>
                </div>
                <p class="info-description" id="infoGroupDesc">
                    {{ $conversation->description ?: 'No description added.' }}
                </p>
                <div id="editGroupDescWrap" style="display:none">
                    <textarea id="groupDescInput" class="form-control" rows="3">{{ $conversation->description }}</textarea>
                    <div class="edit-actions">
                        <button class="btn btn-sm" id="cancelDescEdit">Cancel</button>
                        <button class="btn btn-primary btn-sm" id="saveDescBtn">Save</button>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <h4>{{ $conversation->users->count() }} Members</h4>
                <div class="info-member-list">
                    @foreach($conversation->users as $member)
                        <div class="info-member-item">
                            <img src="{{ $member->profile_photo_url }}" class="avatar avatar-sm">
                            <div class="info-member-details">
                                <span class="member-name">{{ $member->name }}</span>
                                <span class="member-role">{{ $member->pivot->role }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @else
            <div class="info-section">
                <h4>About</h4>
                <p class="info-description">
                    {{ $conversation->users->where('id', '!=', $authUser->id)->first()->about ?? 'Hey there! I am using Connectify.' }}
                </p>
            </div>
            <div class="info-section">
                <h4>Email</h4>
                <p class="info-description">
                    {{ $conversation->users->where('id', '!=', $authUser->id)->first()->email }}
                </p>
            </div>
            @endif

            <div class="info-section" id="mediaSection">
                <h4>Media, links and docs</h4>
                <div class="media-preview-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin-top: 10px;">
                    @php
                        $mediaMessages = $messages->where('type', 'image')->take(6);
                    @endphp
                    @forelse($mediaMessages as $mm)
                        <img src="{{ $mm->image_url }}" style="width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 4px; cursor: pointer;">
                    @empty
                        <p style="grid-column: span 3; font-size: 13px; color: var(--wa-text-muted); text-align: center; padding: 10px;">No media yet</p>
                    @endforelse
                </div>
            </div>
        </div>
    </aside>
    @endif

</div>

{{-- Global Reaction Picker --}}
<div class="reaction-picker" id="globalReactionPicker" style="display:none">
    <div class="reaction-emoji" data-emoji="👍">👍</div>
    <div class="reaction-emoji" data-emoji="❤️">❤️</div>
    <div class="reaction-emoji" data-emoji="😂">😂</div>
    <div class="reaction-emoji" data-emoji="😮">😮</div>
    <div class="reaction-emoji" data-emoji="😢">😢</div>
    <div class="reaction-emoji" data-emoji="🙏">🙏</div>
</div>

<div class="ai-loading" id="aiLoading" style="display:none">
    <div class="ai-spinner"></div>
    <span>Enhancing message…</span>
</div>

{{-- New Group Modal --}}
<div class="modal-overlay" id="groupModal" style="display:none">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create New Group</h3>
            <button class="close-btn" id="groupModalClose">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Group Name</label>
                <input type="text" id="groupName" placeholder="Enter group name">
            </div>
            <div class="form-group">
                <label>Select Members</label>
                <div class="member-search-wrap">
                    <input type="text" id="memberSearch" placeholder="Search users...">
                </div>
                <div id="memberList" class="member-list">
                    {{-- Search results for members will go here --}}
                </div>
                <div id="selectedMembers" class="selected-members">
                    {{-- Selected members chips --}}
                </div>
            </div>
            <button class="btn btn-primary" id="createGroupSubmit" style="width:100%; margin-top:15px">Create Group</button>
        </div>
    </div>
</div>

@if(isset($conversation))
{{-- Add/Edit Contact Modal --}}
<div class="modal-overlay" id="contactModal" style="display:none">
    <div class="modal-content">
        <div class="modal-header">
            <h3>{{ $currentContact ? 'Edit Contact' : 'Add Contact' }}</h3>
            <button class="close-btn" id="contactModalClose">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Contact Alias</label>
                <input type="text" id="contactAlias" value="{{ $currentContact ? $currentContact->alias_name : ($conversation->users->where('id', '!=', $authUser->id)->first()->name ?? '') }}" placeholder="Enter name alias">
            </div>
            @if($currentContact)
                <button class="btn" id="deleteContactBtn" style="background:#dc3545; width:100%; margin-bottom:10px">Delete Contact</button>
            @endif
            <button class="btn btn-primary" id="saveContactSubmit" style="width:100%">Save Contact</button>
        </div>
    </div>
</div>
@endif

{{-- Forward Message Modal --}}
@if(isset($conversation))
<div class="modal-overlay" id="forwardModal" style="display:none">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Forward Message</h3>
            <button class="close-btn" id="forwardModalClose">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Select Conversation</label>
                <div class="forward-conv-list" id="forwardConvList">
                    @foreach($conversations as $item)
                    <div class="forward-conv-item" data-conv-id="{{ $item['conversation']->id }}">
                        <img src="{{ $item['avatar'] }}" class="avatar avatar-sm">
                        <span>{{ $item['display_name'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Message Info Modal --}}
<div class="modal-overlay" id="msgInfoModal" style="display:none">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Message Info</h3>
            <button class="close-btn" id="msgInfoClose">✕</button>
        </div>
        <div class="modal-body" id="msgInfoBody">
            <div class="msg-info-loading">Loading...</div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
@if(isset($conversation))
window.CHAT = {
    conversationId: {{ $conversation->id }},
    isGroup: {{ $conversation->is_group ? 'true' : 'false' }},
    otherUserId: {{ $conversation->is_group ? 'null' : ($conversation->users->where('id', '!=', $authUser->id)->first()->id ?? 'null') }},
    contactId: {{ $currentContact ? $currentContact->id : 'null' }},
    pollUrl: '{{ route('chat.poll', $conversation->id) }}',
    sendUrl: '{{ route('chat.send') }}',
    deleteUrl: '{{ url('/message') }}',
    aiUrl: '{{ route('ai.convert') }}',
    translateUrl: '{{ route('ai.translate') }}',
};
@endif
window.SEARCH_URL = '{{ route('users.search') }}';
</script>
<script src="{{ asset('js/chat.js') }}"></script>
@endpush
@endsection
