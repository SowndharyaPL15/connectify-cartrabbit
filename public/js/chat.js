/* chat.js — chat-specific logic */

(function () {
    'use strict';

    // ── Guard: only run if we're in a conversation ─────────────────────────
    const CHAT = window.CHAT;
    const container  = document.getElementById('messagesContainer');
    const inputEl    = document.getElementById('messageInput');
    const sendBtn    = document.getElementById('sendBtn');
    const imageInput = document.getElementById('imageInput');
    const lastIdEl   = document.getElementById('lastMessageId');
    const chatStatusEl = document.getElementById('chatStatus');

    // ── Polling ────────────────────────────────────────────────────────────
    let pollTimer = null;

    function startPolling() {
        if (!CHAT) return;
        pollTimer = setInterval(poll, 2500);

        // --- Typing Signal ---
        let lastTypingTime = 0;
        if (inputEl) {
            inputEl.oninput = () => {
                const now = Date.now();
                if (now - lastTypingTime > 3000) { // Throttle every 3s
                    lastTypingTime = now;
                    fetch(window.APP.baseUrl + '/chat/' + window.CHAT.conversationId + '/typing', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': window.APP.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).catch(() => {});
                }
            };
        }
    }


    async function poll() {
        const lastId = lastIdEl ? parseInt(lastIdEl.value, 10) : 0;
        const url = CHAT.pollUrl + '?last_id=' + lastId;

        try {
            const response = await fetch(url, {
                headers: {
                    'X-CSRF-TOKEN': window.APP.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();

            if (data.messages && data.messages.length > 0) {
                for (const msg of data.messages) {
                    await appendMessage(msg);
                }
                lastIdEl.value = data.messages[data.messages.length - 1].id;
                scrollToBottom();
            }
            if (data.chat_status && chatStatusEl) {
                chatStatusEl.textContent = data.chat_status;
            }

            if (data.reactions) {
                for (const [msgId, reactions] of Object.entries(data.reactions)) {
                    updateMessageReactions(msgId, reactions);
                }
            }
        } catch (err) {}
    }

    // ── Render a message bubble ────────────────────────────────────────────
    async function appendMessage(msg) {
        const existing = document.querySelector(`.message-wrap[data-id="${msg.id}"]`);
        if (existing) {
            if (msg.is_edited) {
                const newText = existing.querySelector('.bubble-text');
                if (newText) newText.textContent = msg.body;
                const meta = existing.querySelector('.bubble-meta');
                if (meta && !meta.innerHTML.includes('(edited)')) {
                    meta.innerHTML = `<span style="margin-right:4px;">(edited)</span>` + meta.innerHTML;
                }
            }
            return;
        }

        // --- Continuous Translation (Incoming) ---
        if (!msg.is_mine && msg.body && window.TRANSLATE && window.TRANSLATE.state.isContinuous) {
            try {
                msg.body = await window.TRANSLATE.translate(msg.body, window.TRANSLATE.state.targetLanguage);
            } catch (err) {
                console.error('Translation failed for incoming message:', err);
            }
        }

        // Date divider
        const lastDivider = container.querySelector('.date-divider:last-of-type');
        const lastLabel   = lastDivider ? lastDivider.querySelector('span').textContent : null;
        if (msg.date_label !== lastLabel) {
            const divEl = document.createElement('div');
            divEl.className = 'date-divider';
            divEl.innerHTML = `<span>${escHtml(msg.date_label)}</span>`;
            container.appendChild(divEl);
        }

        const wrap = document.createElement('div');
        wrap.className = 'message-wrap ' + (msg.is_mine ? 'mine' : 'theirs');
        wrap.dataset.id = msg.id;

        const statusSvg = msg.is_mine
            ? (msg.status === 'read'
                ? `<svg width="16" height="11" viewBox="0 0 16 11" fill="none"><path d="M1 5.5L5 9.5L15 1.5" stroke="#53BDEB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 1.5L7 6" stroke="#53BDEB" stroke-width="1.5" stroke-linecap="round"/></svg>`
                : `<svg width="16" height="11" viewBox="0 0 16 11" fill="none"><path d="M1 5.5L5 9.5L15 1.5" stroke="#8696A0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`)
            : '';

        const imageHtml = (msg.type === 'image' && msg.image_url)
            ? `<a href="${escHtml(msg.image_url)}" target="_blank"><img src="${escHtml(msg.image_url)}" alt="Image" class="bubble-image"></a>`
            : '';

        const textHtml = msg.body
            ? `<p class="bubble-text">${escHtml(msg.body)}</p>`
            : '';

        const editedHtml = msg.is_edited ? `<span style="margin-right:4px;">(edited)</span>` : '';
        const senderName = (!msg.is_mine && window.CHAT.isGroup) ? `<div class="bubble-sender">${escHtml(msg.sender.name)}</div>` : '';

        // Reply context
        let replyHtml = '';
        if (msg.reply_to) {
            replyHtml = `<div class="bubble-reply-context" data-reply-id="${msg.reply_to.id}">
                <span class="reply-context-name">${escHtml(msg.reply_to.sender)}</span>
                <span class="reply-context-text">${msg.reply_to.type === 'image' ? '📷 Photo' : escHtml(msg.reply_to.body || '')}</span>
            </div>`;
        }

        // Forwarded label
        const forwardedHtml = msg.is_forwarded ? `<div class="bubble-forwarded">⤳ Forwarded</div>` : '';

        // Indicators
        const pinIndicator = msg.is_pinned ? `<span class="bubble-indicator" title="Pinned">📌</span>` : '';
        const starIndicator = msg.is_starred ? `<span class="bubble-indicator" title="Starred">⭐</span>` : '';
        const favIndicator = msg.is_favorited ? `<span class="bubble-indicator" title="Favorited">❤️</span>` : '';

        const bodyPreview = escHtml((msg.body || '').substring(0, 60));

        wrap.innerHTML = `
            <div class="bubble">
                ${senderName}
                ${forwardedHtml}
                ${replyHtml}
                ${imageHtml}
                ${textHtml}
                <div class="bubble-meta">
                    ${pinIndicator}${starIndicator}${favIndicator}
                    ${editedHtml}<span class="bubble-time">${escHtml(msg.time)}</span>
                    ${msg.is_mine ? `<span class="bubble-status ${escHtml(msg.status)}">${statusSvg}</span>` : ''}
                </div>
                <div class="bubble-menu">
                    <button class="bubble-menu-btn">&#8964;</button>
                    <div class="bubble-dropdown">
                        <button class="dropdown-item reply-msg" data-id="${msg.id}" data-body="${bodyPreview}" data-sender="${escHtml(msg.sender.name)}">↩️ Reply</button>
                        <button class="dropdown-item forward-msg" data-id="${msg.id}">➡️ Forward</button>
                        ${msg.is_mine && msg.type === 'text' ? `<button class="dropdown-item edit-msg" data-id="${msg.id}" data-body="${escHtml(msg.body)}">✏️ Edit</button>` : ''}
                        <button class="dropdown-item star-msg" data-id="${msg.id}">${msg.is_starred ? '⭐ Unstar' : '⭐ Star'}</button>
                        <button class="dropdown-item pin-msg" data-id="${msg.id}">${msg.is_pinned ? '📌 Unpin' : '📌 Pin'}</button>
                        <button class="dropdown-item fav-msg" data-id="${msg.id}">${msg.is_favorited ? '❤️ Unfavorite' : '❤️ Favorite'}</button>
                        <button class="dropdown-item info-msg" data-id="${msg.id}">ℹ️ Message Info</button>
                        <button class="dropdown-item delete-msg" data-id="${msg.id}" style="color:#FF6B6B">🗑️ Delete for me</button>
                    </div>
                </div>

                <div class="bubble-reactions hidden" data-id="${msg.id}"></div>

                <button class="reaction-trigger-btn" data-id="${msg.id}" title="React">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                </button>
            </div>`;

        container.appendChild(wrap);
    }

    function scrollToBottom(smooth = true) {
        const area = document.getElementById('messagesArea');
        if (!area) return;
        area.scrollTo({ top: area.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    // ── Send Message ───────────────────────────────────────────────────────
    async function sendMessage() {
        if (!CHAT) return;

        const text  = inputEl ? inputEl.value.trim() : '';
        const file  = imageInput ? imageInput.files[0] : null;

        if (!text && !file) return;

        const isEdit = !!inputEl.dataset.editId;
        const editId = inputEl.dataset.editId;
        const replyToId = inputEl.dataset.replyToId || null;

        const formData = new FormData();
        if (!isEdit) formData.append('conversation_id', CHAT.conversationId);
        formData.append('_token', window.APP.csrfToken);
        if (text) formData.append('body', text);
        if (file) formData.append('image', file);
        if (replyToId && !isEdit) formData.append('reply_to_id', replyToId);

        if (isEdit) {
            formData.append('_method', 'PUT'); // Laravel method spoofing for FormData
        }

        // Optimistic UI: disable send
        if (sendBtn) sendBtn.disabled = true;
        if (inputEl) { 
            inputEl.value = ''; 
            delete inputEl.dataset.editId;
            delete inputEl.dataset.replyToId;
            autoResize(inputEl); 
        }
        clearImagePreview();
        clearReplyPreview();

        const targetUrl = isEdit ? (window.APP.baseUrl + '/message/' + editId) : CHAT.sendUrl;

        // --- Continuous Translation (Outgoing) ---
        if (!isEdit && text && window.TRANSLATE && window.TRANSLATE.state.isContinuous) {
            try {
                const translated = await window.TRANSLATE.translate(text, window.TRANSLATE.state.targetLanguage);
                formData.set('body', translated);
            } catch (err) {
                console.error('Translation failed, sending original:', err);
            }
        }

        try {
            const response = await fetch(targetUrl, {
                method: 'POST', // POST with _method=PUT to send FormData
                headers: { 'X-CSRF-TOKEN': window.APP.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const data = await response.json();

            if (data.success && data.message) {
                if (isEdit) {
                    const el = document.querySelector(`.message-wrap[data-id="${editId}"]`);
                    if (el) {
                        const newText = el.querySelector('.bubble-text');
                        if (newText) newText.textContent = data.message.body;
                        const meta = el.querySelector('.bubble-meta');
                        if (meta && !meta.innerHTML.includes('(edited)')) {
                            meta.innerHTML = `<span style="margin-right:4px;">(edited)</span>` + meta.innerHTML;
                        }
                        // update the data-body on the edit button
                        const btn = el.querySelector('.edit-msg');
                        if (btn) btn.dataset.body = data.message.body;
                    }
                } else {
                    // --- Continuous Translation (Outgoing) ---
                    // If outgoing was translated, we append the translated version
                    await appendMessage(data.message);
                    if (lastIdEl) lastIdEl.value = data.message.id;
                    scrollToBottom();
                }
            } else if (data.error) {
                showToast(data.error);
            }
        } catch (err) {
            console.error('Send failed:', err);
        } finally {
            if (sendBtn) sendBtn.disabled = false;
            if (inputEl) inputEl.focus();
        }
    }

    // ── Reply Preview ──────────────────────────────────────────────────────
    function setReplyPreview(msgId, senderName, bodyText) {
        const preview = document.getElementById('replyPreview');
        const nameEl = document.getElementById('replyPreviewName');
        const textEl = document.getElementById('replyPreviewText');
        if (!preview || !nameEl || !textEl || !inputEl) return;

        inputEl.dataset.replyToId = msgId;
        nameEl.textContent = senderName;
        textEl.textContent = bodyText || '📷 Photo';
        preview.style.display = 'flex';
        inputEl.focus();
    }

    function clearReplyPreview() {
        const preview = document.getElementById('replyPreview');
        if (preview) preview.style.display = 'none';
        if (inputEl) delete inputEl.dataset.replyToId;
    }

    // ── Delete for me ──────────────────────────────────────────────────────
    function deleteMessage(msgId) {
        const url = CHAT.deleteUrl + '/' + msgId + '/delete-for-me';
        fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': window.APP.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const el = document.querySelector(`.message-wrap[data-id="${msgId}"]`);
                if (el) el.remove();
            }
        })
        .catch(() => {});
    }

    function bindDeleteBtn(btn) {
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (confirm('Delete this message for you?')) {
                deleteMessage(this.dataset.id);
            }
        });
    }

    function bindEditBtn(btn) {
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (inputEl) {
                inputEl.value = this.dataset.body;
                inputEl.dataset.editId = this.dataset.id;
                inputEl.focus();
                autoResize(inputEl);
            }
            // Close the dropdown
            const dd = this.closest('.bubble-dropdown');
            if (dd) dd.style.display = 'none';
        });
    }

    // ── Image attachment ───────────────────────────────────────────────────
    function clearImagePreview() {
        const wrap = document.getElementById('imagePreviewWrap');
        const prev = document.getElementById('imagePreview');
        if (wrap) wrap.style.display = 'none';
        if (prev) prev.src = '';
        if (imageInput) imageInput.value = '';
    }

    // ── AI Tone Enhance ────────────────────────────────────────────────────
    const enhanceBtn  = document.getElementById('enhanceBtn');
    const tonePopup   = document.getElementById('tonePopup');
    const toneClose   = document.getElementById('toneClose');
    const toneList    = document.getElementById('toneList');
    const toneCorrected = document.getElementById('toneCorrected');
    const aiLoading   = document.getElementById('aiLoading');

    function enhanceMessage() {
        if (!CHAT || !inputEl) return;
        const text = inputEl.value.trim();
        if (!text) {
            showToast('Type a message first.');
            return;
        }

        if (aiLoading) aiLoading.style.display = 'flex';
        if (tonePopup) tonePopup.style.display = 'none';

        fetch(CHAT.aiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.APP.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message: text }),
        })
        .then(r => r.json())
        .then(data => {
            if (aiLoading) aiLoading.style.display = 'none';
            
            // AUTOMATED: Apply correction immediately
            if (data.corrected && data.corrected !== text) {
                inputEl.value = data.corrected;
                autoResize(inputEl);
                showToast('Typo corrected!');
            }
            
            showTonePopup(data, text);
        })
        .catch(() => {
            if (aiLoading) aiLoading.style.display = 'none';
            showToast('AI unavailable. You can still send your original message.');
        });
    }

    function showTonePopup(data, originalText) {
        if (!tonePopup || !toneList) return;

        // Show auto-corrected
        if (toneCorrected) {
            if (data.corrected && data.corrected !== originalText) {
                toneCorrected.innerHTML = `<strong>Auto-corrected:</strong> ${escHtml(data.corrected)}`;
                toneCorrected.classList.add('visible');
            } else {
                toneCorrected.classList.remove('visible');
            }
        }

        // Render suggestions
        toneList.innerHTML = '';
        (data.suggestions || []).forEach(sug => {
            const item = document.createElement('div');
            item.className = 'tone-item';
            item.innerHTML = `
                <span class="tone-badge ${escHtml(sug.tone)}">${escHtml(sug.tone)}</span>
                <span class="tone-text">${escHtml(sug.text)}</span>`;
            item.addEventListener('click', () => {
                if (inputEl) inputEl.value = sug.text;
                autoResize(inputEl);
                closeTonePopup();
                inputEl.focus();
            });
            toneList.appendChild(item);
        });

        tonePopup.style.display = 'block';
    }

    function closeTonePopup() {
        if (tonePopup) tonePopup.style.display = 'none';
    }

    // ── User Search ────────────────────────────────────────────────────────
    const searchToggle  = document.getElementById('searchToggle');
    const searchBar     = document.getElementById('searchBar');
    const searchInput   = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    let searchTimer = null;

    function doSearch(q) {
        if (!q || q.length < 1) {
            if (searchResults) searchResults.style.display = 'none';
            return;
        }

        const url = window.SEARCH_URL + '?q=' + encodeURIComponent(q);
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.APP.csrfToken,
            },
        })
        .then(r => r.json())
        .then(users => {
            if (!searchResults) return;
            searchResults.innerHTML = '';
            if (users.length === 0) {
                searchResults.innerHTML = '<div style="padding:12px 16px;color:#8696A0;font-size:13px">No users found</div>';
            } else {
                users.forEach(u => {
                    const a = document.createElement('a');
                    a.href = window.APP.baseUrl + '/chat/u/' + u.id;
                    a.className = 'search-result-item';
                    a.innerHTML = `
                        <img src="${escHtml(u.avatar)}" alt="${escHtml(u.name)}" class="avatar avatar-sm">
                        <div>
                            <div style="font-size:14px;font-weight:500">${escHtml(u.name)}</div>
                            <div style="font-size:12px;color:#8696A0">${escHtml(u.email)}</div>
                        </div>`;
                    searchResults.appendChild(a);
                });
            }
            searchResults.style.display = 'block';
        })
        .catch(() => {});
    }

    // ── Toast helper ───────────────────────────────────────────────────────
    function showToast(msg) {
        const t = document.createElement('div');
        t.style.cssText = `
            position:fixed;bottom:20px;left:50%;transform:translateX(-50%);
            background:#1C2B33;border:1px solid #222D34;color:#E9EDF0;
            padding:10px 18px;border-radius:20px;font-size:13px;
            z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.4);
            animation:fadeIn .2s ease;
        `;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // ── Escape HTML ────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Global keyboard handler ────────────────────────────────────────────
    window.handleInputKey = function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        } else {
            setTimeout(() => autoResize(e.target), 0);
        }
    };

    // ── Mobile nav ────────────────────────────────────────────────────────
    function setupMobileNav() {
        const sidebar     = document.getElementById('sidebar');
        const chatWindow  = document.getElementById('chatWindow');
        const mobileBack  = document.getElementById('mobileBack');

        if (!sidebar || !chatWindow) return;

        if (window.innerWidth <= 768) {
            if (CHAT) {
                // Conversation open
                sidebar.classList.add('hidden');
                chatWindow.classList.add('active');
            }
        }

        if (mobileBack) {
            mobileBack.addEventListener('click', () => {
                sidebar.classList.remove('hidden');
                chatWindow.classList.remove('active');
            });
        }
    }

    // ── Init ───────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {

        // Scroll to bottom on load
        scrollToBottom(false);

        // Start polling
        startPolling();

        // Send button
        if (sendBtn) sendBtn.addEventListener('click', sendMessage);

        // Enhance button
        if (enhanceBtn) enhanceBtn.addEventListener('click', enhanceMessage);

        // Close tone popup
        if (toneClose) toneClose.addEventListener('click', closeTonePopup);

        // Close tone popup on outside click
        document.addEventListener('click', function (e) {
            if (tonePopup && !tonePopup.contains(e.target) && e.target !== enhanceBtn) {
                closeTonePopup();
            }
        });

        // Image input
        if (imageInput) {
            imageInput.addEventListener('change', function () {
                if (!this.files[0]) return;
                const wrap = document.getElementById('imagePreviewWrap');
                const prev = document.getElementById('imagePreview');
                const reader = new FileReader();
                reader.onload = e => {
                    if (prev) prev.src = e.target.result;
                    if (wrap) wrap.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            });
        }

        // Remove image
        const removeImg = document.getElementById('removeImage');
        if (removeImg) removeImg.addEventListener('click', clearImagePreview);

        // Delete message buttons (server-rendered)
        document.querySelectorAll('.delete-msg').forEach(bindDeleteBtn);

        // Search toggle
        if (searchToggle && searchBar) {
            searchToggle.addEventListener('click', () => {
                const visible = searchBar.style.display !== 'none';
                searchBar.style.display = visible ? 'none' : 'block';
                if (!visible && searchInput) searchInput.focus();
            });
        }

        // Search input
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => doSearch(this.value.trim()), 300);
            });

            // Hide results on outside click
            document.addEventListener('click', function (e) {
                if (searchResults && !searchBar.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        }

        // Auto-resize textarea on initial
        if (inputEl) {
            inputEl.addEventListener('input', () => autoResize(inputEl));
        }

        // ── Emoji Picker ────────────────────────────────────────────────────────
        const emojiBtn = document.getElementById('emojiBtn');
        const emojiPickerWrap = document.getElementById('emojiPickerWrap');
        const emojiGrid = document.getElementById('emojiGrid');
        const emojiSearchInput = document.getElementById('emojiSearch');
        const emojiTabs = document.getElementById('emojiTabs');

        const EMOJI_DATA = {
            smileys: {
                label: 'Smileys & Emotion',
                emojis: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🫡','🤐','🤨','😐','😑','😶','🫥','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','🫤','😟','🙁','☹️','😮','😯','😲','😳','🥺','🥹','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾','🫶','💋','💌','💘','💝','💖','💗','💓','💞','💕','💟','❣️','💔','❤️','🩷','🧡','💛','💚','💙','🩵','💜','🤎','🖤','🩶','🤍']
            },
            people: {
                label: 'People & Body',
                emojis: ['👋','🤚','🖐️','✋','🖖','🫱','🫲','🫳','🫴','👌','🤌','🤏','✌️','🤞','🫰','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','🫵','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂','🦻','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️','👅','👄','🫦','👶','🧒','👦','👧','🧑','👱','👨','🧔','👩','🧓','👴','👵','🙍','🙎','🙅','🙆','💁','🙋','🧏','🙇','🤦','🤷','🧑‍⚕️','🧑‍🎓','🧑‍🏫','🧑‍⚖️','🧑‍🌾','🧑‍🍳','🧑‍🔧','🧑‍🏭','🧑‍💼','🧑‍🔬','🧑‍💻','🧑‍🎤','🧑‍🎨','🧑‍✈️','🧑‍🚀','🧑‍🚒','👮','🕵️','💂','🥷','👷','🫅','🤴','👸','👳','👲','🧕','🤵','👰']
            },
            nature: {
                label: 'Animals & Nature',
                emojis: ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐽','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🐣','🐥','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🫎','🐝','🪱','🐛','🦋','🐌','🐞','🐜','🪰','🪲','🪳','🦟','🦗','🕷️','🦂','🐢','🐍','🦎','🦖','🦕','🐙','🦑','🦐','🦞','🦀','🪸','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🐕','🐩','🦮','🐈','🐓','🦃','🦤','🦚','🦜','🦢','🦩','🕊️','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🐁','🐀','🐿️','🦔','🐾','🐉','🐲','🌵','🎄','🌲','🌳','🌴','🪵','🌱','🌿','☘️','🍀','🎍','🪴','🎋','🍃','🍂','🍁','🪹','🪺','🍄','🌾','💐','🌷','🌹','🥀','🌺','🌸','🌼','🌻','🌞','🌝','🌛','🌜','🌚','🌕','🌖','🌗','🌘','🌑','🌒','🌓','🌔','🌙','🌎','🌍','🌏','🪐','💫','⭐','🌟','✨','⚡','☄️','💥','🔥','🌪️','🌈','☀️','🌤️','⛅','🌥️','☁️','🌦️','🌧️','⛈️','🌩️','🌨️','❄️','☃️','⛄','🌬️','💨','💧','💦','🫧','☔','☂️','🌊','🌫️']
            },
            food: {
                label: 'Food & Drink',
                emojis: ['🍇','🍈','🍉','🍊','🍋','🍌','🍍','🥭','🍎','🍏','🍐','🍑','🍒','🍓','🫐','🥝','🍅','🫒','🥥','🥑','🍆','🥔','🥕','🌽','🌶️','🫑','🥒','🥬','🥦','🧄','🧅','🥜','🫘','🌰','🫚','🫛','🍞','🥐','🥖','🫓','🥨','🥯','🥞','🧇','🧀','🍖','🍗','🥩','🥓','🍔','🍟','🍕','🌭','🥪','🌮','🌯','🫔','🥙','🧆','🥚','🍳','🥘','🍲','🫕','🥣','🥗','🍿','🧈','🧂','🥫','🍱','🍘','🍙','🍚','🍛','🍜','🍝','🍠','🍢','🍣','🍤','🍥','🥮','🍡','🥟','🥠','🥡','🦀','🦞','🦐','🦑','🦪','🍦','🍧','🍨','🍩','🍪','🎂','🍰','🧁','🥧','🍫','🍬','🍭','🍮','🍯','🍼','🥛','☕','🫖','🍵','🍶','🍾','🍷','🍸','🍹','🍺','🍻','🥂','🥃','🫗','🥤','🧋','🧃','🧉','🧊']
            },
            activities: {
                label: 'Activities',
                emojis: ['⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🥏','🎱','🪀','🏓','🏸','🏒','🏑','🥍','🏏','🪃','🥅','⛳','🪁','🏹','🎣','🤿','🥊','🥋','🎽','🛹','🛼','🛷','⛸️','🥌','🎿','⛷️','🏂','🪂','🏋️','🤼','🤸','🤺','⛹️','🤾','🏌️','🏇','🧘','🏄','🏊','🤽','🚣','🧗','🚵','🚴','🏆','🥇','🥈','🥉','🏅','🎖️','🏵️','🎗️','🎫','🎟️','🎪','🤹','🎭','🩰','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🪘','🎷','🎺','🪗','🎸','🪕','🎻','🪈','🎲','♟️','🎯','🎳','🎮','🕹️','🎰','🧩']
            },
            travel: {
                label: 'Travel & Places',
                emojis: ['🚗','🚕','🚙','🚌','🚎','🏎️','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🏍️','🛵','🦽','🦼','🛺','🚲','🛴','🛹','🚏','🛣️','🛤️','🛞','🚨','🚥','🚦','🛑','🚧','⚓','🛟','⛵','🛶','🚤','🛳️','⛴️','🛥️','🚢','✈️','🛩️','🛫','🛬','🪂','💺','🚁','🚟','🚠','🚡','🛰️','🚀','🛸','🏠','🏡','🏘️','🏚️','🏗️','🏭','🏢','🏬','🏣','🏤','🏥','🏦','🏨','🏪','🏫','🏩','💒','🏛️','⛪','🕌','🕍','🛕','🕋','⛩️','🛖','⛺','🌁','🌃','🏙️','🌄','🌅','🌆','🌇','🌉','♨️','🎠','🛝','🎡','🎢','💈','🎪','🗼','🗽','🗿','🏰','🏯','🏟️','🎆','🎇','🧨','✨','🎈','🎉','🎊','🎋','🎍','🎎','🎏','🎐','🎑','🧧','🎀','🎁','🎗️']
            },
            objects: {
                label: 'Objects',
                emojis: ['⌚','📱','📲','💻','⌨️','🖥️','🖨️','🖱️','🖲️','🕹️','🗜️','💽','💾','💿','📀','📼','📷','📸','📹','🎥','📽️','🎞️','📞','☎️','📟','📠','📺','📻','🎙️','🎚️','🎛️','🧭','⏱️','⏲️','⏰','🕰️','⌛','⏳','📡','🔋','🪫','🔌','💡','🔦','🕯️','🪔','🧯','🛢️','💸','💵','💴','💶','💷','🪙','💰','💳','🪪','💎','⚖️','🪜','🧰','🪛','🔧','🔨','⚒️','🛠️','⛏️','🪚','🔩','⚙️','🪤','🧱','⛓️','🧲','🔫','💣','🧨','🪓','🔪','🗡️','⚔️','🛡️','🚬','⚰️','🪦','⚱️','🏺','🔮','📿','🧿','🪬','💈','⚗️','🔭','🔬','🕳️','🩻','🩹','🩺','💊','💉','🩸','🧬','🦠','🧫','🧪','🌡️','🧹','🪠','🧺','🧻','🚽','🚰','🚿','🛁','🛀','🧼','🪥','🪒','🧽','🪣','🧴','🛎️','🔑','🗝️','🚪','🪑','🛋️','🛏️','🛌','🧸','🪆','🖼️','🪞','🪟','🛍️','🛒','🎁','🎈','🎏','🎀','🪄','🪅','🎊','🎉','🎎','🏮','🎐','🧧','✉️','📩','📨','📧','💌','📥','📤','📦','🏷️','🪧','📪','📫','📬','📭','📮','📯','📜','📃','📄','📑','🧾','📊','📈','📉','🗒️','🗓️','📆','📅','🗑️','📇','🗃️','🗳️','🗄️','📋','📁','📂','🗂️','🗞️','📰','📓','📔','📒','📕','📗','📘','📙','📚','📖','🔖','🧷','🔗','📎','🖇️','📐','📏','🧮','📌','📍','✂️','🖊️','🖋️','✒️','🖌️','🖍️','📝','✏️','🔍','🔎','🔏','🔐','🔒','🔓']
            },
            symbols: {
                label: 'Symbols',
                emojis: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️','✝️','☪️','🕉️','☸️','✡️','🔯','🕎','☯️','☦️','🛐','⛎','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','🆔','⚛️','🉑','☢️','☣️','📴','📳','🈶','🈚','🈸','🈺','🈷️','✴️','🆚','💮','🉐','㊙️','㊗️','🈴','🈵','🈹','🈲','🅰️','🅱️','🆎','🆑','🅾️','🆘','❌','⭕','🛑','⛔','📛','🚫','💯','💢','♨️','🚷','🚯','🚳','🚱','🔞','📵','🚭','❗','❕','❓','❔','‼️','⁉️','🔅','🔆','〽️','⚠️','🚸','🔱','⚜️','🔰','♻️','✅','🈯','💹','❇️','✳️','❎','🌐','💠','Ⓜ️','🌀','💤','🏧','🚾','♿','🅿️','🛗','🈳','🈂️','🛂','🛃','🛄','🛅','🚹','🚺','🚼','⚧️','🚻','🚮','🎦','📶','🈁','🔣','ℹ️','🔤','🔡','🔠','🆖','🆗','🆙','🆒','🆕','🆓','0️⃣','1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟','🔢','#️⃣','*️⃣','⏏️','▶️','⏸️','⏯️','⏹️','⏺️','⏭️','⏮️','⏩','⏪','⏫','⏬','◀️','🔼','🔽','➡️','⬅️','⬆️','⬇️','↗️','↘️','↙️','↖️','↕️','↔️','↪️','↩️','⤴️','⤵️','🔀','🔁','🔂','🔄','🔃','🎵','🎶','➕','➖','➗','✖️','🟰','♾️','💲','💱','™️','©️','®️','👁‍🗨','🔚','🔙','🔛','🔝','🔜','〰️','➰','➿','✔️','☑️','🔘','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','🔺','🔻','🔸','🔹','🔶','🔷','🔳','🔲','▪️','▫️','◾','◽','◼️','◻️','🟥','🟧','🟨','🟩','🟦','🟪','⬛','⬜','🟫','🔈','🔇','🔉','🔊','🔔','🔕','📣','📢','💬','💭','🗯️','♠️','♣️','♥️','♦️','🃏','🎴','🀄','🕐','🕑','🕒','🕓','🕔','🕕','🕖','🕗','🕘','🕙','🕚','🕛']
            }
        };

        let currentEmojiCategory = 'smileys';

        function renderEmojiGrid(category, filter) {
            if (!emojiGrid) return;
            emojiGrid.innerHTML = '';

            if (filter) {
                // Search across all categories
                const lowerFilter = filter.toLowerCase();
                let found = false;
                Object.keys(EMOJI_DATA).forEach(cat => {
                    EMOJI_DATA[cat].emojis.forEach(em => {
                        // Simple search: check if filter is in the category name
                        if (EMOJI_DATA[cat].label.toLowerCase().includes(lowerFilter) || !filter) {
                            const btn = document.createElement('button');
                            btn.className = 'emoji-item';
                            btn.textContent = em;
                            btn.type = 'button';
                            btn.addEventListener('click', () => insertEmoji(em));
                            emojiGrid.appendChild(btn);
                            found = true;
                        }
                    });
                });
                // If no category match, show all emojis and let user scan
                if (!found) {
                    Object.keys(EMOJI_DATA).forEach(cat => {
                        EMOJI_DATA[cat].emojis.forEach(em => {
                            const btn = document.createElement('button');
                            btn.className = 'emoji-item';
                            btn.textContent = em;
                            btn.type = 'button';
                            btn.addEventListener('click', () => insertEmoji(em));
                            emojiGrid.appendChild(btn);
                        });
                    });
                }
                return;
            }

            const data = EMOJI_DATA[category];
            if (!data) return;

            const label = document.createElement('div');
            label.className = 'emoji-category-label';
            label.textContent = data.label;
            emojiGrid.appendChild(label);

            data.emojis.forEach(em => {
                const btn = document.createElement('button');
                btn.className = 'emoji-item';
                btn.textContent = em;
                btn.type = 'button';
                btn.addEventListener('click', () => insertEmoji(em));
                emojiGrid.appendChild(btn);
            });
        }

        function insertEmoji(emoji) {
            if (!inputEl) return;
            const start = inputEl.selectionStart;
            const end = inputEl.selectionEnd;
            const text = inputEl.value;
            inputEl.value = text.substring(0, start) + emoji + text.substring(end);
            inputEl.selectionStart = inputEl.selectionEnd = start + emoji.length;
            autoResize(inputEl);
            inputEl.focus();
        }

        if (emojiBtn && emojiPickerWrap) {
            emojiBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isVisible = emojiPickerWrap.style.display !== 'none';
                emojiPickerWrap.style.display = isVisible ? 'none' : 'flex';
                if (!isVisible) {
                    renderEmojiGrid(currentEmojiCategory);
                }
            });

            // Tab switching
            if (emojiTabs) {
                emojiTabs.addEventListener('click', function(e) {
                    const tab = e.target.closest('.emoji-tab');
                    if (!tab) return;
                    emojiTabs.querySelectorAll('.emoji-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    currentEmojiCategory = tab.dataset.category;
                    if (emojiSearchInput) emojiSearchInput.value = '';
                    renderEmojiGrid(currentEmojiCategory);
                });
            }

            // Search
            if (emojiSearchInput) {
                emojiSearchInput.addEventListener('input', function() {
                    const q = this.value.trim();
                    if (q.length > 0) {
                        renderEmojiGrid(null, q);
                    } else {
                        renderEmojiGrid(currentEmojiCategory);
                    }
                });
            }

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (emojiPickerWrap.style.display !== 'none' && !emojiPickerWrap.contains(e.target) && e.target !== emojiBtn && !emojiBtn.contains(e.target)) {
                    emojiPickerWrap.style.display = 'none';
                }
            });
        }

        // Mobile navigation
        setupMobileNav();

        // ── Bubble Menu Delegation ──────────────────────────────────────────
        let forwardMsgId = null; // track which message to forward

        document.addEventListener('click', function (e) {
            const menuBtn = e.target.closest('.bubble-menu-btn');
            const editBtn = e.target.closest('.edit-msg');
            const deleteBtn = e.target.closest('.delete-msg');
            const replyBtn = e.target.closest('.reply-msg');
            const forwardBtn = e.target.closest('.forward-msg');
            const starBtn = e.target.closest('.star-msg');
            const pinBtn = e.target.closest('.pin-msg');
            const favBtn = e.target.closest('.fav-msg');
            const infoBtn = e.target.closest('.info-msg');

            function closeDropdown(el) {
                const dd = el ? el.closest('.bubble-dropdown') : null;
                if (dd) dd.style.display = 'none';
            }

            // 1. Toggle Menu
            if (menuBtn) {
                e.stopPropagation();
                const dd = menuBtn.nextElementSibling;
                const isOpen = dd.style.display === 'block';

                // Close all other dropdowns
                document.querySelectorAll('.bubble-dropdown').forEach(d => d.style.display = 'none');
                
                // Toggle this one
                dd.style.display = isOpen ? 'none' : 'block';
                return;
            }

            // 2. Reply Action
            if (replyBtn) {
                e.stopPropagation();
                setReplyPreview(replyBtn.dataset.id, replyBtn.dataset.sender, replyBtn.dataset.body);
                closeDropdown(replyBtn);
                return;
            }

            // 3. Forward Action
            if (forwardBtn) {
                e.stopPropagation();
                forwardMsgId = forwardBtn.dataset.id;
                const forwardModal = document.getElementById('forwardModal');
                if (forwardModal) forwardModal.style.display = 'flex';
                closeDropdown(forwardBtn);
                return;
            }

            // 4. Edit Action
            if (editBtn) {
                e.stopPropagation();
                if (inputEl) {
                    clearReplyPreview();
                    inputEl.value = editBtn.dataset.body;
                    inputEl.dataset.editId = editBtn.dataset.id;
                    inputEl.focus();
                    autoResize(inputEl);
                }
                closeDropdown(editBtn);
                return;
            }

            // 5. Star Action
            if (starBtn) {
                e.stopPropagation();
                const msgId = starBtn.dataset.id;
                fetch(window.APP.baseUrl + '/message/' + msgId + '/star', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        starBtn.textContent = data.starred ? '⭐ Unstar' : '⭐ Star';
                        const wrap = document.querySelector(`.message-wrap[data-id="${msgId}"]`);
                        if (wrap) {
                            const meta = wrap.querySelector('.bubble-meta');
                            const existingStar = meta ? meta.querySelector('.bubble-indicator[title="Starred"]') : null;
                            if (data.starred && !existingStar && meta) {
                                const ind = document.createElement('span');
                                ind.className = 'bubble-indicator';
                                ind.title = 'Starred';
                                ind.textContent = '⭐';
                                meta.insertBefore(ind, meta.firstChild);
                            } else if (!data.starred && existingStar) {
                                existingStar.remove();
                            }
                        }
                        showToast(data.starred ? 'Message starred' : 'Message unstarred');
                    }
                });
                closeDropdown(starBtn);
                return;
            }

            // 6. Pin Action
            if (pinBtn) {
                e.stopPropagation();
                const msgId = pinBtn.dataset.id;
                fetch(window.APP.baseUrl + '/message/' + msgId + '/pin', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        pinBtn.textContent = data.pinned ? '📌 Unpin' : '📌 Pin';
                        const wrap = document.querySelector(`.message-wrap[data-id="${msgId}"]`);
                        if (wrap) {
                            const meta = wrap.querySelector('.bubble-meta');
                            const existingPin = meta ? meta.querySelector('.bubble-indicator[title="Pinned"]') : null;
                            if (data.pinned && !existingPin && meta) {
                                const ind = document.createElement('span');
                                ind.className = 'bubble-indicator';
                                ind.title = 'Pinned';
                                ind.textContent = '📌';
                                meta.insertBefore(ind, meta.firstChild);
                            } else if (!data.pinned && existingPin) {
                                existingPin.remove();
                            }
                        }
                        showToast(data.pinned ? 'Message pinned' : 'Message unpinned');
                    }
                });
                closeDropdown(pinBtn);
                return;
            }

            // 7. Favorite Action
            if (favBtn) {
                e.stopPropagation();
                const msgId = favBtn.dataset.id;
                fetch(window.APP.baseUrl + '/message/' + msgId + '/favorite', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        favBtn.textContent = data.favorited ? '❤️ Unfavorite' : '❤️ Favorite';
                        const wrap = document.querySelector(`.message-wrap[data-id="${msgId}"]`);
                        if (wrap) {
                            const meta = wrap.querySelector('.bubble-meta');
                            const existingFav = meta ? meta.querySelector('.bubble-indicator[title="Favorited"]') : null;
                            if (data.favorited && !existingFav && meta) {
                                const ind = document.createElement('span');
                                ind.className = 'bubble-indicator';
                                ind.title = 'Favorited';
                                ind.textContent = '❤️';
                                meta.insertBefore(ind, meta.firstChild);
                            } else if (!data.favorited && existingFav) {
                                existingFav.remove();
                            }
                        }
                        showToast(data.favorited ? 'Added to favorites' : 'Removed from favorites');
                    }
                });
                closeDropdown(favBtn);
                return;
            }

            // 8. Message Info Action
            if (infoBtn) {
                e.stopPropagation();
                const msgId = infoBtn.dataset.id;
                const infoModal = document.getElementById('msgInfoModal');
                const infoBody = document.getElementById('msgInfoBody');
                if (infoModal && infoBody) {
                    infoBody.innerHTML = '<div class="msg-info-loading">Loading...</div>';
                    infoModal.style.display = 'flex';

                    fetch(window.APP.baseUrl + '/message/' + msgId + '/info', {
                        headers: {
                            'X-CSRF-TOKEN': window.APP.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const info = data.info;
                            const statusLabel = info.status === 'read' ? '✓✓ Read' : (info.status === 'delivered' ? '✓✓ Delivered' : '✓ Sent');
                            infoBody.innerHTML = `
                                ${info.body ? `<div class="msg-info-body-preview">${escHtml(info.body)}</div>` : ''}
                                <div class="msg-info-grid">
                                    <div class="msg-info-row">
                                        <span class="msg-info-label">From</span>
                                        <span class="msg-info-value">${escHtml(info.sender)}</span>
                                    </div>
                                    <div class="msg-info-row">
                                        <span class="msg-info-label">Sent at</span>
                                        <span class="msg-info-value">${escHtml(info.sent_at)}</span>
                                    </div>
                                    <div class="msg-info-row">
                                        <span class="msg-info-label">Status</span>
                                        <span class="msg-info-status ${escHtml(info.status)}">${statusLabel}</span>
                                    </div>
                                    <div class="msg-info-row">
                                        <span class="msg-info-label">Starred</span>
                                        <span class="msg-info-value">${info.is_starred ? '⭐ Yes' : 'No'}</span>
                                    </div>
                                    <div class="msg-info-row">
                                        <span class="msg-info-label">Pinned</span>
                                        <span class="msg-info-value">${info.is_pinned ? '📌 Yes' : 'No'}</span>
                                    </div>
                                    <div class="msg-info-row">
                                        <span class="msg-info-label">Favorited</span>
                                        <span class="msg-info-value">${info.is_favorited ? '❤️ Yes' : 'No'}</span>
                                    </div>
                                    ${info.is_edited ? `<div class="msg-info-row"><span class="msg-info-label">Edited</span><span class="msg-info-value">Yes</span></div>` : ''}
                                </div>`;
                        }
                    })
                    .catch(() => {
                        infoBody.innerHTML = '<div class="msg-info-loading">Failed to load info.</div>';
                    });
                }
                closeDropdown(infoBtn);
                return;
            }

            // 9. Delete Action
            if (deleteBtn) {
                e.stopPropagation();
                if (confirm('Delete this message for you?')) {
                    deleteMessage(deleteBtn.dataset.id);
                }
                closeDropdown(deleteBtn);
                return;
            }

            // 10. Close all on outside click
            document.querySelectorAll('.bubble-dropdown').forEach(dd => {
                if (!dd.contains(e.target)) {
                    dd.style.display = 'none';
                }
            });
        });

        // ── Reply Preview Close ─────────────────────────────────────────────
        const replyCloseBtn = document.getElementById('replyPreviewClose');
        if (replyCloseBtn) {
            replyCloseBtn.addEventListener('click', clearReplyPreview);
        }

        // ── Forward Modal ───────────────────────────────────────────────────
        const forwardModal = document.getElementById('forwardModal');
        const forwardClose = document.getElementById('forwardModalClose');
        const forwardConvList = document.getElementById('forwardConvList');

        if (forwardClose) {
            forwardClose.onclick = () => { forwardModal.style.display = 'none'; forwardMsgId = null; };
        }

        if (forwardConvList) {
            forwardConvList.addEventListener('click', function(e) {
                const item = e.target.closest('.forward-conv-item');
                if (!item || !forwardMsgId) return;

                const targetConvId = item.dataset.convId;
                fetch(window.APP.baseUrl + '/message/' + forwardMsgId + '/forward', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ conversation_id: targetConvId }),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message forwarded!');
                        // If forwarded to current conversation, append it
                        if (parseInt(targetConvId) === CHAT.conversationId) {
                            appendMessage(data.message);
                            if (lastIdEl) lastIdEl.value = data.message.id;
                            scrollToBottom();
                        }
                    } else {
                        showToast(data.error || 'Failed to forward.');
                    }
                })
                .catch(() => showToast('Failed to forward.'));

                forwardModal.style.display = 'none';
                forwardMsgId = null;
            });
        }

        // ── Message Info Modal Close ────────────────────────────────────────
        const msgInfoClose = document.getElementById('msgInfoClose');
        const msgInfoModal = document.getElementById('msgInfoModal');
        if (msgInfoClose) {
            msgInfoClose.onclick = () => { msgInfoModal.style.display = 'none'; };
        }



        // ── New Group Modal ──────────────────────────────────────────────────
        const groupBtn      = document.getElementById('newGroupBtn');
        const groupModal    = document.getElementById('groupModal');
        const groupClose    = document.getElementById('groupModalClose');
        const memberSearch  = document.getElementById('memberSearch');
        const memberList    = document.getElementById('memberList');
        const selectedList  = document.getElementById('selectedMembers');
        const createSubmit  = document.getElementById('createGroupSubmit');
        const groupNameInput= document.getElementById('groupName');

        let selectedUsers = [];

        if (groupBtn) {
            groupBtn.onclick = () => { groupModal.style.display = 'flex'; };
        }
        if (groupClose) {
            groupClose.onclick = () => { groupModal.style.display = 'none'; };
        }

        if (memberSearch) {
            memberSearch.oninput = (e) => {
                const q = e.target.value.trim();
                if (q.length < 2) { memberList.innerHTML = ''; return; }
                fetch(window.SEARCH_URL + '?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(users => {
                        memberList.innerHTML = '';
                        users.forEach(u => {
                            if (selectedUsers.some(s => s.id === u.id)) return;
                            const item = document.createElement('div');
                            item.className = 'member-search-item';
                            item.innerHTML = `<img src="${escHtml(u.avatar)}" class="avatar avatar-sm"><span>${escHtml(u.name)}</span>`;
                            item.onclick = () => {
                                selectedUsers.push(u);
                                renderSelected();
                                memberList.innerHTML = '';
                                memberSearch.value = '';
                            };
                            memberList.appendChild(item);
                        });
                    });
            };
        }

        function renderSelected() {
            selectedList.innerHTML = '';
            selectedUsers.forEach(u => {
                const chip = document.createElement('div');
                chip.className = 'member-chip';
                chip.innerHTML = `<span>${escHtml(u.name)}</span><button data-id="${u.id}">✕</button>`;
                chip.querySelector('button').onclick = () => {
                    selectedUsers = selectedUsers.filter(s => s.id !== u.id);
                    renderSelected();
                };
                selectedList.appendChild(chip);
            });
        }

        if (createSubmit) {
            createSubmit.onclick = () => {
                const name = groupNameInput.value.trim();
                if (!name) return showToast('Please enter a group name.');
                if (selectedUsers.length === 0) return showToast('Select at least one member.');

                createSubmit.disabled = true;
                fetch(window.APP.baseUrl + '/groups', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        name: name,
                        user_ids: selectedUsers.map(s => s.id),
                    }),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = window.APP.baseUrl + '/chat/' + data.conversation_id;
                    } else {
                        showToast(data.error || 'Failed to create group.');
                        createSubmit.disabled = false;
                    }
                });
            };
        }


    // ── Contact Management ────────────────────────────────────────────────
        const contactBtn    = document.getElementById('contactActionBtn');
        const contactModal  = document.getElementById('contactModal');
        const contactClose  = document.getElementById('contactModalClose');
        const saveContact   = document.getElementById('saveContactSubmit');
        const deleteContact = document.getElementById('deleteContactBtn');
        const contactAlias  = document.getElementById('contactAlias');

        if (contactBtn) {
            contactBtn.onclick = () => { contactModal.style.display = 'flex'; };
        }
        if (contactClose) {
            contactClose.onclick = () => { contactModal.style.display = 'none'; };
        }

        if (saveContact) {
            saveContact.onclick = () => {
                const alias = contactAlias.value.trim();
                const isUpdate = !!window.CHAT.contactId;

                const url = isUpdate ? (window.APP.baseUrl + '/contacts/' + window.CHAT.contactId) : (window.APP.baseUrl + '/contacts');
                const method = isUpdate ? 'PUT' : 'POST';

                saveContact.disabled = true;
                fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        contact_id: window.CHAT.otherUserId,
                        alias_name: alias
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showToast(data.error || 'Failed to save contact.');
                        saveContact.disabled = false;
                    }
                });
            };
        }

        if (deleteContact) {
            deleteContact.onclick = () => {
                if (!confirm('Are you sure you want to remove this contact?')) return;
                deleteContact.disabled = true;
                fetch(window.APP.baseUrl + '/contacts/' + window.CHAT.contactId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showToast('Failed to delete contact.');
                        deleteContact.disabled = false;
                    }
                });
            };
        }

        // ── Group Info Sidebar ───────────────────────────────────────────────
        const infoBtn      = document.getElementById('groupInfoBtn');
        const infoSidebar  = document.getElementById('groupInfoSidebar');
        const infoClose    = document.getElementById('closeGroupInfo');
        
        const editDescBtn   = document.getElementById('editGroupDescBtn');
        const descWrap      = document.getElementById('editGroupDescWrap');
        const descView      = document.getElementById('infoGroupDesc');
        const descInput     = document.getElementById('groupDescInput');
        const cancelDesc    = document.getElementById('cancelDescEdit');
        const saveDesc      = document.getElementById('saveDescBtn');

        const editNameBtn   = document.getElementById('editGroupNameBtn');

        if (infoBtn) {
            infoBtn.onclick = () => { infoSidebar.classList.toggle('active'); };
        }
        if (infoClose) {
            infoClose.onclick = () => { infoSidebar.classList.remove('active'); };
        }

        if (editDescBtn) {
            editDescBtn.onclick = () => {
                descView.style.display = 'none';
                descWrap.style.display = 'block';
                editDescBtn.style.display = 'none';
                descInput.focus();
            };
        }

        if (cancelDesc) {
            cancelDesc.onclick = () => {
                descView.style.display = 'block';
                descWrap.style.display = 'none';
                editDescBtn.style.display = 'block';
            };
        }

        if (saveDesc) {
            saveDesc.onclick = () => {
                const newDesc = descInput.value.trim();
                saveDesc.disabled = true;

                fetch(window.APP.baseUrl + '/groups/' + window.CHAT.conversationId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ description: newDesc })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        descView.textContent = data.description || 'No description added.';
                        descView.style.display = 'block';
                        descWrap.style.display = 'none';
                        editDescBtn.style.display = 'block';
                        showToast('Description updated!');
                    } else {
                        showToast(data.error || 'Failed to update description.');
                    }
                })
                .finally(() => { saveDesc.disabled = false; });
            };
        }

        if (editNameBtn) {
            editNameBtn.onclick = () => {
                const oldName = document.getElementById('infoGroupName').textContent;
                const newName = prompt('Enter new group name:', oldName);
                if (newName && newName !== oldName) {
                    fetch(window.APP.baseUrl + '/groups/' + window.CHAT.conversationId, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.APP.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ name: newName })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload(); // Hard refresh to update sidebar & header
                        }
                    });
                }
            };
        }

        // ── Header Dropdown ────────────────────────────────────────────────
        const headerMoreBtn = document.getElementById('headerMoreBtn');
        const headerDropdown = document.getElementById('headerDropdown');
        const clearChatBtn = document.getElementById('clearChatBtn');
        const exportChatBtn = document.getElementById('exportChatBtn');
        const chatInfoLink = document.getElementById('chatInfoLink');
        const mediaLink = document.getElementById('mediaLink');

        if (headerMoreBtn && headerDropdown) {
            headerMoreBtn.onclick = (e) => {
                e.stopPropagation();
                const isOpen = headerDropdown.style.display === 'block';
                headerDropdown.style.display = isOpen ? 'none' : 'block';
            };

            document.addEventListener('click', (e) => {
                if (!headerMoreBtn.contains(e.target) && !headerDropdown.contains(e.target)) {
                    headerDropdown.style.display = 'none';
                }
            });
        }

        if (clearChatBtn) {
            clearChatBtn.onclick = () => {
                if (!confirm('Are you sure you want to clear all messages in this chat? This cannot be undone.')) return;
                
                fetch(window.APP.baseUrl + '/chat/' + window.CHAT.conversationId + '/clear', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('messagesContainer');
                        if (container) container.innerHTML = '';
                        showToast('Chat cleared');
                    }
                });
                headerDropdown.style.display = 'none';
            };
        }

        if (exportChatBtn) {
            exportChatBtn.onclick = () => {
                window.location.href = window.APP.baseUrl + '/chat/' + window.CHAT.conversationId + '/export';
                headerDropdown.style.display = 'none';
            };
        }

        const exitGroupBtn = document.getElementById('exitGroupBtn');
        if (exitGroupBtn) {
            exitGroupBtn.onclick = () => {
                if (!confirm('Are you sure you want to leave this group? This action cannot be undone.')) return;
                
                fetch(window.APP.baseUrl + '/chat/' + window.CHAT.conversationId + '/exit', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = window.APP.baseUrl + '/chat';
                    } else {
                        showToast(data.error || 'Failed to exit group.');
                    }
                });
                headerDropdown.style.display = 'none';
            };
        }

        const blockUserBtn = document.getElementById('blockUserBtn');
        if (blockUserBtn) {
            blockUserBtn.onclick = () => {
                const userId = blockUserBtn.dataset.userId;
                const isUnblock = blockUserBtn.textContent.includes('Unblock');
                const msg = isUnblock ? 'Unblock this user?' : 'Block this user? They will not be able to send you messages.';
                
                if (!confirm(msg)) return;

                fetch(window.APP.baseUrl + '/chat/u/' + userId + '/toggle-block', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showToast(data.error || 'Action failed.');
                    }
                });
                headerDropdown.style.display = 'none';
            };
        }

        const unblockBannerBtn = document.getElementById('unblockBannerBtn');
        if (unblockBannerBtn) {
            unblockBannerBtn.onclick = () => {
                const userId = unblockBannerBtn.dataset.userId;
                fetch(window.APP.baseUrl + '/chat/u/' + userId + '/toggle-block', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        showToast(data.error || 'Action failed.');
                    }
                });
            };
        }

        function updateMessageReactions(msgId, reactions) {
            const msgWrap = document.querySelector(`.message-wrap[data-id="${msgId}"]`);
            if (!msgWrap) return;

            let reactionsContainer = msgWrap.querySelector('.bubble-reactions');
            if (!reactionsContainer) {
                // If it didn't exist, we might need to create it, but it should be in the blade
                return; 
            }

            const summary = reactions.summary || {};
            const userReaction = reactions.userReaction;

            if (Object.keys(summary).length === 0) {
                reactionsContainer.classList.add('hidden');
                reactionsContainer.innerHTML = '';
            } else {
                reactionsContainer.classList.remove('hidden');
                let html = '';
                for (const [emoji, count] of Object.entries(summary)) {
                    const isMine = (userReaction === emoji);
                    html += `
                        <span class="reaction-badge ${isMine ? 'mine' : ''}" data-emoji="${emoji}">
                            ${emoji} <span class="reaction-count">${count > 1 ? count : ''}</span>
                        </span>
                    `;
                }
                reactionsContainer.innerHTML = html;
            }
        }

        // ── Message Reactions ──
        const globalPicker = document.getElementById('globalReactionPicker');
        let currentReactionMsgId = null;

        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.reaction-trigger-btn');
            if (trigger) {
                e.stopPropagation();
                currentReactionMsgId = trigger.dataset.id;
                const rect = trigger.getBoundingClientRect();
                globalPicker.style.display = 'flex';
                globalPicker.style.top = (rect.top - 50) + 'px';
                globalPicker.style.left = (rect.left - 40) + 'px';
                return;
            }

            if (globalPicker && !e.target.closest('.reaction-picker')) {
                globalPicker.style.display = 'none';
            }
        });

        if (globalPicker) {
            globalPicker.querySelectorAll('.reaction-emoji').forEach(emojiEl => {
                emojiEl.onclick = () => {
                    const emoji = emojiEl.dataset.emoji;
                    if (!currentReactionMsgId) return;

                    fetch(window.APP.baseUrl + '/message/' + currentReactionMsgId + '/react', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.APP.csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ emoji })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            updateMessageReactions(currentReactionMsgId, data.reactions);
                        }
                    });

                    globalPicker.style.display = 'none';
                };
            });
        }

        // Click on existing badge to toggle same emoji
        document.addEventListener('click', (e) => {
            const badge = e.target.closest('.reaction-badge');
            if (badge) {
                const msgWrap = badge.closest('.message-wrap');
                if (!msgWrap) return;
                const msgId = msgWrap.dataset.id;
                const emoji = badge.dataset.emoji;

                fetch(window.APP.baseUrl + '/message/' + msgId + '/react', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ emoji })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        updateMessageReactions(msgId, data.reactions);
                    }
                });
            }
        });

        if (chatInfoLink) {
            chatInfoLink.onclick = () => {
                const infoSidebar = document.getElementById('groupInfoSidebar');
                if (infoSidebar) infoSidebar.classList.add('active');
                headerDropdown.style.display = 'none';
            };
        }

        if (mediaLink) {
            mediaLink.onclick = () => {
                 const infoSidebar = document.getElementById('groupInfoSidebar');
                 if (infoSidebar) {
                    infoSidebar.classList.add('active');
                    const mediaSec = document.getElementById('mediaSection');
                    if (mediaSec) mediaSec.scrollIntoView({ behavior: 'smooth' });
                 }
                 headerDropdown.style.display = 'none';
            };
        }

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.onclick = () => {
                const isLight = document.documentElement.classList.toggle('light-mode');
                localStorage.setItem('theme', isLight ? 'light' : 'dark');
            };
        }

        // Add 1-on-1 info toggle to header click if desired

        const headerInfoArea = document.querySelector('.chat-header-user');
        if (headerInfoArea) {
            headerInfoArea.style.cursor = 'pointer';
            headerInfoArea.onclick = () => {
                const infoSidebar = document.getElementById('groupInfoSidebar');
                if (infoSidebar) infoSidebar.classList.add('active');
            };
        }

        // ── Translation Management ───────────────────────────────────────────
        const translateToggleBtn = document.getElementById('translateToggleBtn');
        const translationBar     = document.getElementById('translationBar');
        const transLangSelect    = document.getElementById('transLang');
        const transContinuous    = document.getElementById('transContinuous');
        const transBtn           = document.getElementById('transBtn');

        const translationState = {
            isBarVisible: false,
            isContinuous: false,
            targetLanguage: 'English',
            cache: {}
        };

        if (translateToggleBtn && translationBar) {
            translateToggleBtn.addEventListener('click', () => {
                translationState.isBarVisible = !translationState.isBarVisible;
                translationBar.style.display = translationState.isBarVisible ? 'flex' : 'none';
                translateToggleBtn.classList.toggle('active', translationState.isBarVisible);
                if (translationState.isBarVisible) scrollToBottom();
            });
        }

        if (transLangSelect) {
            transLangSelect.addEventListener('change', (e) => {
                translationState.targetLanguage = e.target.value;
            });
            // Initial value
            translationState.targetLanguage = transLangSelect.value;
        }

        if (transContinuous) {
            transContinuous.addEventListener('change', (e) => {
                translationState.isContinuous = e.target.checked;
                if (translationState.isContinuous) {
                    showToast(`Continuous translation to ${translationState.targetLanguage} ON`);
                }
            });
        }

        if (transBtn) {
            transBtn.addEventListener('click', async () => {
                const text = inputEl ? inputEl.value.trim() : '';
                if (!text) return;

                transBtn.disabled = true;
                transBtn.textContent = '...';

                try {
                    const translated = await translateText(text, translationState.targetLanguage);
                    if (inputEl) {
                        inputEl.value = translated;
                        autoResize(inputEl);
                    }
                } catch (err) {
                    showToast('Translation failed');
                } finally {
                    transBtn.disabled = false;
                    transBtn.textContent = 'Translate';
                }
            });
        }

        async function translateText(text, targetLang) {
            const cacheKey = `${text}:${targetLang}`;
            if (translationState.cache[cacheKey]) return translationState.cache[cacheKey];

            const response = await fetch(window.CHAT.translateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.APP.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message: text,
                    target_language: targetLang
                })
            });

            const data = await response.json();
            if (data.success) {
                translationState.cache[cacheKey] = data.translated;
                return data.translated;
            }
            throw new Error(data.error || 'Translation failed');
        }

        // Expose translateText for integration into other functions
        window.TRANSLATE = {
            state: translationState,
            translate: translateText
        };

    });


    // ── Patching appendMessage and sendMessage to support Continuous mode ──
    // Note: We need to find the original functions and wrap them or modify them.
    // Since everything is in an IIFE, I'll need to locate where they are defined
    // and make sure my patches work.

    window.addEventListener('beforeunload', () => {
        clearInterval(pollTimer);
    });

})();
