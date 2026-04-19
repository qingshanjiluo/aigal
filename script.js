let currentUser = null;
let conversationHistory = [];
let characters = [];
let affection = {};
let currentScene = "";
let currentOptions = [];
let autoPlay = false;
let autoTimer = null;
let isProcessing = false;
let currentCharId = null;
let autoVoice = true;
let audioQueue = [];
let isPlayingAudio = false;
let pendingAudio = null;
let gameSettings = {};
let hasCustomApiKey = false;
let currentChapter = 1;
let chapterTitle = '第一章：初遇';
let importantEvents = {};
let portraits = {};
let currentEmotion = 'normal';

const loginPanel = document.getElementById('login-panel');
const gamePanel = document.getElementById('game-panel');
const charListDiv = document.getElementById('char-list');
const affectionSummary = document.getElementById('affection-summary');
const messagesContainer = document.getElementById('messages-container');
const sceneText = document.getElementById('scene-text');
const sceneTextHeader = document.getElementById('scene-text-header');
const optionsBar = document.getElementById('options-bar');
const messageInput = document.getElementById('message-input');
const sendBtn = document.getElementById('send-btn');
const portraitEmoji = document.getElementById('portrait-emoji');
const portraitImage = document.getElementById('portrait-image');
const portraitImageContainer = document.getElementById('portrait-image-container');
const emotionIndicator = document.getElementById('emotion-indicator');
const portraitName = document.getElementById('portrait-name');
const affectionBadge = document.getElementById('affection-badge');
const affectionBar = document.getElementById('affection-bar');
const affectionValue = document.getElementById('affection-value');
const chapterBadge = document.getElementById('chapter-badge');
const autoPlayToggle = document.getElementById('auto-play-toggle');
const autoVoiceToggle = document.getElementById('auto-voice-toggle');

async function api(endpoint, method, body = null) {
    const options = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) options.body = JSON.stringify(body);
    try {
        const res = await fetch(endpoint, options);
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('API返回非JSON:', text.substring(0, 500));
            return { error: '服务器返回格式错误: ' + text.substring(0, 200) };
        }
    } catch (e) {
        console.error('网络错误:', e);
        return { error: '网络请求失败' };
    }
}

function detectEmotionFromText(text) {
    const keywords = {
        happy: ['开心', '高兴', '笑', '喜', '乐', '愉快', '幸福', '满足'],
        angry: ['生气', '愤怒', '怒', '讨厌', '恨', '烦'],
        sad: ['难过', '伤心', '哭', '悲', '泪', '痛苦', '失望'],
        shy: ['害羞', '羞', '脸红', '不好意思'],
        surprised: ['惊讶', '吃惊', '震惊', '意外', '吓']
    };
    for (const [emotion, words] of Object.entries(keywords)) {
        for (const word of words) {
            if (text.includes(word)) return emotion;
        }
    }
    return 'normal';
}

async function loadGameData() {
    const res = await api('game.php?action=load', 'GET');
    if (res.success) {
        conversationHistory = res.data.conversation_history || [];
        affection = res.data.affection || {};
        currentScene = res.data.current_scene || '校园';
        characters = res.data.characters || [];
        if (Object.keys(affection).length === 0) {
            characters.forEach(c => affection[c.id] = c.affection);
        }
        currentOptions = res.data.options || [];
        gameSettings = res.data.settings || getDefaultSettings();
        hasCustomApiKey = res.data.has_custom_api_key || false;
        currentChapter = res.data.current_chapter || 1;
        chapterTitle = res.data.chapter_title || '第一章：初遇';
        importantEvents = res.data.important_events || {};
        portraits = res.data.portraits || {};
        if (characters.length > 0) {
            currentCharId = characters[0].id;
            currentEmotion = characters[0].current_emotion || 'normal';
        } else {
            currentCharId = null;
            currentEmotion = 'normal';
        }
        renderUI();
        renderEvents();
    }
}

function getDefaultSettings() {
    return {
        story_background: '现代校园',
        global_prompt: '',
        auto_scene: true,
        auto_options: true,
        group_mode: false,
        happy_ending_threshold: 95,
        sad_ending_threshold: 10,
        happy_ending_text: '✨ 达成【幸福结局】与{name} ✨',
        sad_ending_text: '💔 达成【离别结局】'
    };
}

async function saveGameData() {
    await api('game.php?action=save', 'POST', {
        conversation_history: conversationHistory,
        affection: affection,
        current_scene: currentScene,
        characters: characters,
        options: currentOptions,
        current_chapter: currentChapter,
        chapter_title: chapterTitle
    });
}

async function saveSettingsToServer(settings) {
    const res = await api('game.php?action=save_settings', 'POST', settings);
    if (res.success) {
        gameSettings = { ...gameSettings, ...settings };
    }
    return res;
}

// =================== 渲染 ===================

function renderUI() {
    renderCharList();
    renderMessages();
    renderOptions();
    updateAffectionDisplay();
    updatePortrait();
    updateChapterDisplay();
    updateMetaDisplay();
    sceneText.innerText = currentScene;
    sceneTextHeader.innerText = currentScene;
}

function renderCharList() {
    charListDiv.innerHTML = '';
    characters.forEach(c => {
        const div = document.createElement('div');
        div.className = `char-item ${currentCharId === c.id ? 'active' : ''}`;
        div.innerHTML = `<span>${c.emoji} ${c.name}<span class="edit-indicator">✎</span></span><span>❤️ ${affection[c.id]}</span>`;
        div.onclick = () => {
            currentCharId = c.id;
            currentEmotion = c.current_emotion || 'normal';
            renderCharList();
            updatePortrait();
        };
        div.ondblclick = () => {
            openEditCharModal(c);
        };
        charListDiv.appendChild(div);
    });
    affectionSummary.innerHTML = characters.map(c => `${c.name}: ${affection[c.id]}`).join(' · ');
}

function renderMessages() {
    messagesContainer.innerHTML = '';
    for (let i = 0; i < conversationHistory.length; i++) {
        const msg = conversationHistory[i];
        const isPlayer = msg.speaker === '玩家';
        const char = characters.find(c => c.name === msg.speaker);
        const avatar = isPlayer ? '🧑' : (char?.emoji || '😊');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isPlayer ? 'message-right' : 'message-left'}`;
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'bubble';
        bubbleDiv.innerText = msg.text;
        messageDiv.appendChild(bubbleDiv);
        if (!isPlayer && msg.speaker !== '系统') {
            const voiceBtn = document.createElement('button');
            voiceBtn.innerText = '🔊';
            voiceBtn.className = 'voice-btn';
            voiceBtn.setAttribute('data-text', msg.text);
            voiceBtn.setAttribute('data-speaker', msg.speaker);
            voiceBtn.onclick = (e) => {
                e.stopPropagation();
                playVoice(msg.text, msg.speaker, voiceBtn);
            };
            messageDiv.appendChild(voiceBtn);
        }
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'avatar';
        avatarDiv.innerText = avatar;
        if (isPlayer) messageDiv.appendChild(avatarDiv);
        else messageDiv.insertBefore(avatarDiv, messageDiv.firstChild);
        messagesContainer.appendChild(messageDiv);
    }
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function renderOptions() {
    optionsBar.innerHTML = '';
    currentOptions.forEach(opt => {
        const btn = document.createElement('button');
        btn.className = 'option-btn';
        btn.innerText = opt;
        btn.onclick = throttle(() => {
            messageInput.value = opt;
            sendMessage();
        }, 800);
        optionsBar.appendChild(btn);
    });
}

function updateAffectionDisplay() {
    if (!currentCharId || affection[currentCharId] === undefined) {
        if (affectionBadge) affectionBadge.innerHTML = '❤️ -';
        if (affectionBar) affectionBar.style.width = '0%';
        if (affectionValue) affectionValue.innerText = '-/100';
        return;
    }
    const val = affection[currentCharId];
    if (affectionBadge) affectionBadge.innerHTML = `❤️ ${val}`;
    if (affectionBar) affectionBar.style.width = `${val}%`;
    if (affectionValue) affectionValue.innerText = `${val}/100`;
}

function updatePortrait() {
    const char = characters.find(c => c.id === currentCharId);
    if (!char) return;
    
    portraitName.innerText = char.name;
    
    const charPortraits = portraits[char.id] || {};
    const emotion = currentEmotion || 'normal';
    
    if (charPortraits[emotion]) {
        portraitImage.src = charPortraits[emotion];
        portraitImage.style.display = 'block';
        portraitEmoji.style.display = 'none';
    } else if (charPortraits['normal']) {
        portraitImage.src = charPortraits['normal'];
        portraitImage.style.display = 'block';
        portraitEmoji.style.display = 'none';
    } else {
        portraitImage.style.display = 'none';
        portraitEmoji.style.display = 'block';
        portraitEmoji.innerText = char.emoji;
    }
    
    const emotionLabels = {
        normal: '😊 正常',
        happy: '😄 开心',
        angry: '😠 生气',
        sad: '😢 难过',
        shy: '😳 害羞',
        surprised: '😲 惊讶'
    };
    emotionIndicator.innerText = emotionLabels[emotion] || emotionLabels.normal;
}

function updateChapterDisplay() {
    chapterBadge.innerText = `第${currentChapter}章：${chapterTitle}`;
}

function renderEvents() {
    const eventsSection = document.getElementById('events-section');
    const eventsList = document.getElementById('events-list');
    
    if (Object.keys(importantEvents).length === 0) {
        eventsSection.style.display = 'none';
        return;
    }
    
    eventsSection.style.display = 'block';
    eventsList.innerHTML = '';
    
    Object.values(importantEvents).forEach(event => {
        const div = document.createElement('div');
        div.className = 'event-item';
        div.innerText = event.description;
        eventsList.appendChild(div);
    });
}

// =================== 工具函数 ===================

function throttle(fn, delay) {
    let lastCall = 0;
    return function(...args) {
        const now = Date.now();
        if (now - lastCall < delay) return;
        lastCall = now;
        fn.apply(this, args);
    };
}

// =================== 语音播放 ===================

async function playVoice(text, speakerName, btnElement = null) {
    return new Promise((resolve) => {
        if (pendingAudio && pendingAudio.text === text && pendingAudio.speakerName === speakerName) {
            if (pendingAudio.reject) pendingAudio.reject('cancelled');
        }
        audioQueue.push({ text, speakerName, btnElement, resolve });
        processAudioQueue();
    });
}

async function processAudioQueue() {
    if (isPlayingAudio || audioQueue.length === 0) return;
    isPlayingAudio = true;
    const { text, speakerName, btnElement, resolve } = audioQueue.shift();
    if (btnElement) {
        btnElement.classList.add('playing');
        btnElement.disabled = true;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 20000);
    pendingAudio = { text, speakerName, reject: () => {} };

    try {
        const res = await fetch(`game.php?action=tts&text=${encodeURIComponent(text)}&speaker=${encodeURIComponent(speakerName)}`, {
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        const data = await res.json();
        if (data.success && data.audio_url) {
            const audio = new Audio(data.audio_url);
            const playPromise = audio.play();
            if (playPromise !== undefined) {
                playPromise.catch(err => {
                    console.warn('音频播放被阻止:', err);
                    cleanupVoice(btnElement, resolve);
                });
            }
            audio.onended = () => { cleanupVoice(btnElement, resolve); };
            audio.onerror = () => { cleanupVoice(btnElement, resolve); };
            setTimeout(() => {
                if (isPlayingAudio) cleanupVoice(btnElement, resolve);
            }, 60000);
        } else {
            cleanupVoice(btnElement, resolve);
        }
    } catch (e) {
        clearTimeout(timeoutId);
        console.warn('语音错误', e);
        cleanupVoice(btnElement, resolve);
    }
}

function cleanupVoice(btnElement, resolve) {
    if (btnElement) {
        btnElement.classList.remove('playing');
        btnElement.disabled = false;
    }
    isPlayingAudio = false;
    if (resolve) resolve();
    pendingAudio = null;
    setTimeout(() => processAudioQueue(), 100);
}

// =================== 消息发送 ===================

async function sendMessage() {
    if (isProcessing) return;
    if (characters.length === 0) {
        alert('请先添加至少一个角色才能开始对话');
        return;
    }
    const text = messageInput.value.trim();
    if (!text) return;
    messageInput.value = '';
    conversationHistory.push({ speaker: '玩家', text: text });
    renderMessages();
    isProcessing = true;
    sendBtn.disabled = true;
    disableOptionButtons(true);
    
    try {
        const res = await api('game.php?action=chat', 'POST', {
            player_input: text,
            history: conversationHistory,
            characters: characters,
            affection: affection,
            current_scene: currentScene,
            current_chapter: currentChapter,
            chapter_title: chapterTitle,
            important_events: importantEvents,
            global_prompt: gameSettings.global_prompt || '',
            world_prompt: gameSettings.world_prompt || '',
            story_background: gameSettings.story_background || currentScene
        });
        
        if (res.success) {
            const data = res.data;
            
            // 处理场景变化
            if (data.new_scene && data.new_scene !== currentScene) {
                currentScene = data.new_scene;
                conversationHistory.push({ speaker: '系统', text: `🎬 场景切换：${currentScene}` });
                renderMessages();
            }
            
            // 处理重要事件
            if (data.important_event) {
                const eventKey = 'event_' + Date.now();
                importantEvents[eventKey] = {
                    description: data.important_event,
                    timestamp: Date.now()
                };
                await api('game.php?action=record_event', 'POST', {
                    event_key: eventKey,
                    event_description: data.important_event
                });
                renderEvents();
            }
            
            // 处理检测到的关键词事件
            if (data.detected_events) {
                for (const event of data.detected_events) {
                    importantEvents[event.key] = {
                        description: event.description,
                        timestamp: Date.now()
                    };
                    await api('game.php?action=record_event', 'POST', {
                        event_key: event.key,
                        event_description: event.description
                    });
                }
                renderEvents();
            }
            
            // 处理角色回复
            if (data.speaker && data.reply) {
                // 从回复文本检测情感并更新立绘
                const detectedEmotion = detectEmotionFromText(data.reply);
                if (detectedEmotion !== 'normal') {
                    currentEmotion = detectedEmotion;
                    const char = characters.find(c => c.name === data.speaker);
                    if (char) {
                        char.current_emotion = detectedEmotion;
                    }
                    updatePortrait();
                }
                
                // 如果后端也返回了emotion，优先使用
                if (data.emotion) {
                    currentEmotion = data.emotion;
                    const char = characters.find(c => c.name === data.speaker);
                    if (char) char.current_emotion = data.emotion;
                    updatePortrait();
                }
                
                // 好感度变化
                let delta = 0;
                if (data.reply.includes('喜欢') || data.reply.includes('开心')) delta = 3;
                else if (data.reply.includes('讨厌') || data.reply.includes('生气')) delta = -4;
                const targetChar = characters.find(c => c.name === data.speaker);
                if (targetChar && delta !== 0) {
                    affection[targetChar.id] = Math.min(100, Math.max(0, affection[targetChar.id] + delta));
                    updateAffectionDisplay();
                    renderCharList();
                }
                
                conversationHistory.push({ speaker: data.speaker, text: data.reply });
                renderMessages();
                
                // 自动语音
                if (autoVoice && data.speaker !== '系统') {
                    const lastMsgDiv = messagesContainer.lastChild;
                    const voiceBtn = lastMsgDiv?.querySelector('.voice-btn');
                    if (voiceBtn) {
                        await playVoice(data.reply, data.speaker, voiceBtn);
                    } else {
                        await playVoice(data.reply, data.speaker);
                    }
                }
                
                // 结局判定
                const happyTh = gameSettings.happy_ending_threshold ?? 95;
                const sadTh = gameSettings.sad_ending_threshold ?? 10;
                const happyText = (gameSettings.happy_ending_text || '✨ 达成【幸福结局】与{name} ✨').replace('{name}', data.speaker);
                const sadText = gameSettings.sad_ending_text || '💔 达成【离别结局】';
                
                if (targetChar && affection[targetChar.id] >= happyTh) {
                    conversationHistory.push({ speaker: '系统', text: happyText });
                    currentOptions = [];
                    renderMessages();
                    // 解锁结局
                    await api('game.php?action=unlock_ending', 'POST', {
                        ending_key: 'happy_' + targetChar.id,
                        ending_title: '幸福结局 · ' + data.speaker,
                        ending_description: happyText,
                        triggered_by: targetChar.id
                    });
                } else if (targetChar && affection[targetChar.id] <= sadTh) {
                    conversationHistory.push({ speaker: '系统', text: sadText });
                    currentOptions = [];
                    renderMessages();
                    await api('game.php?action=unlock_ending', 'POST', {
                        ending_key: 'sad_' + targetChar.id,
                        ending_title: '离别结局 · ' + data.speaker,
                        ending_description: sadText,
                        triggered_by: targetChar.id
                    });
                }
            }
            
            currentOptions = data.options || [];
            renderOptions();
            await saveGameData();
        } else {
            alert(res.error || 'AI错误');
        }
    } catch(e) {
        alert('网络错误');
        console.error(e);
    }
    
    isProcessing = false;
    sendBtn.disabled = false;
    disableOptionButtons(false);
    
    if (autoPlay) {
        if (autoTimer) clearTimeout(autoTimer);
        autoTimer = setTimeout(() => {
            if (messageInput.value.trim()) sendMessage();
        }, 3000);
    }
}

function disableOptionButtons(disabled) {
    const btns = optionsBar.querySelectorAll('.option-btn');
    btns.forEach(btn => btn.disabled = disabled);
}

// =================== 事件监听 ===================

if (autoPlayToggle) {
    autoPlayToggle.onchange = (e) => {
        autoPlay = e.target.checked;
        if (!autoPlay && autoTimer) {
            clearTimeout(autoTimer);
            autoTimer = null;
        }
    };
}
if (autoVoiceToggle) {
    autoVoiceToggle.onchange = (e) => {
        autoVoice = e.target.checked;
    };
}

sendBtn.onclick = sendMessage;
messageInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });

// =================== 角色管理 ===================

const addCharModal = document.getElementById('add-char-modal');
document.getElementById('add-char-btn').onclick = () => addCharModal.style.display = 'flex';
document.getElementById('cancel-add-char').onclick = () => addCharModal.style.display = 'none';
document.getElementById('confirm-add-char').onclick = async () => {
    const name = document.getElementById('new-char-name').value.trim();
    const prompt = document.getElementById('new-char-prompt').value.trim();
    let aff = parseInt(document.getElementById('new-char-aff').value);
    if (!name) return;
    const newId = 'char_' + Date.now();
    characters.push({
        id: newId,
        name: name,
        prompt: prompt || `你是${name}`,
        emoji: '😊',
        affection: aff,
        default_emotion: 'normal',
        current_emotion: 'normal'
    });
    affection[newId] = aff;
    await saveGameData();
    renderUI();
    addCharModal.style.display = 'none';
    document.getElementById('new-char-name').value = '';
    document.getElementById('new-char-prompt').value = '';
    document.getElementById('new-char-aff').value = '50';
};

// =================== 角色编辑 ===================

const editCharModal = document.getElementById('edit-char-modal');
function openEditCharModal(char) {
    document.getElementById('edit-char-id').value = char.id;
    document.getElementById('edit-char-name').value = char.name;
    document.getElementById('edit-char-prompt').value = char.prompt || '';
    document.getElementById('edit-char-aff').value = affection[char.id] ?? 50;
    document.getElementById('delete-char-btn').style.display = characters.length > 1 ? 'block' : 'none';
    editCharModal.style.display = 'flex';
}
document.getElementById('cancel-edit-char').onclick = () => editCharModal.style.display = 'none';
document.getElementById('confirm-edit-char').onclick = async () => {
    const charId = document.getElementById('edit-char-id').value;
    const name = document.getElementById('edit-char-name').value.trim();
    const prompt = document.getElementById('edit-char-prompt').value.trim();
    const aff = parseInt(document.getElementById('edit-char-aff').value);
    if (!name) return;
    const res = await api('game.php?action=update_character', 'POST', {
        char_id: charId,
        updates: { name, prompt, affection: aff }
    });
    if (res.success) {
        characters = res.characters;
        affection[charId] = aff;
        if (currentCharId === charId) updatePortrait();
        renderUI();
        editCharModal.style.display = 'none';
    } else {
        alert(res.error || '保存失败');
    }
};
document.getElementById('delete-char-btn').onclick = async () => {
    if (characters.length <= 1) {
        alert('至少保留一个角色');
        return;
    }
    const charId = document.getElementById('edit-char-id').value;
    if (!confirm('确定要删除该角色吗？')) return;
    const res = await api('game.php?action=delete_character', 'POST', { char_id: charId });
    if (res.success) {
        characters = res.characters;
        affection = res.affection;
        if (currentCharId === charId) currentCharId = characters[0]?.id;
        renderUI();
        editCharModal.style.display = 'none';
    } else {
        alert(res.error || '删除失败');
    }
};

// =================== 立绘管理 ===================

const portraitModal = document.getElementById('portrait-modal');
document.getElementById('manage-portraits-btn').onclick = () => {
    loadPortraitPreview();
    portraitModal.style.display = 'flex';
};
document.getElementById('close-portrait-modal').onclick = () => portraitModal.style.display = 'none';

async function loadPortraitPreview() {
    const container = document.getElementById('portrait-preview-list');
    container.innerHTML = '';
    
    const char = characters.find(c => c.id === currentCharId);
    if (!char) return;
    
    const charPortraits = portraits[char.id] || {};
    
    const emotionLabels = {
        normal: '正常', happy: '开心', angry: '生气', sad: '难过',
        shy: '害羞', surprised: '惊讶', closeup: '近景', side: '侧身'
    };
    
    for (const [type, url] of Object.entries(charPortraits)) {
        const item = document.createElement('div');
        item.className = 'portrait-preview-item';
        item.innerHTML = `<img src="${url}" alt="${type}"><div class="portrait-label">${emotionLabels[type] || type}</div>`;
        container.appendChild(item);
    }
    
    if (Object.keys(charPortraits).length === 0) {
        container.innerHTML = '<div style="color:rgba(255,255,255,0.5);font-size:12px;text-align:center;padding:20px;">暂无立绘</div>';
    }
}

document.getElementById('upload-portrait-confirm').onclick = async () => {
    const fileInput = document.getElementById('portrait-file-input');
    const typeSelect = document.getElementById('portrait-type-select');
    
    if (!fileInput.files[0]) {
        alert('请选择图片文件');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    
    try {
        // 这里使用Base64编码图片，实际项目中应该上传到服务器
        const reader = new FileReader();
        reader.onload = async (e) => {
            const imageUrl = e.target.result;
            const charId = currentCharId;
            const portraitType = typeSelect.value;
            
            if (!portraits[charId]) portraits[charId] = {};
            portraits[charId][portraitType] = imageUrl;
            
            await api('game.php?action=save_portrait', 'POST', {
                character_id: charId,
                portrait_type: portraitType,
                image_url: imageUrl
            });
            
            updatePortrait();
            loadPortraitPreview();
            fileInput.value = '';
            alert('立绘上传成功');
        };
        reader.readAsDataURL(fileInput.files[0]);
    } catch (err) {
        alert('上传失败: ' + err.message);
    }
};

// =================== 全局设置 ===================

const settingsModal = document.getElementById('settings-modal');
const apiKeyInput = document.getElementById('setting-api-key');
const toggleKeyBtn = document.getElementById('toggle-key-visible');
const viewKeyBtn = document.getElementById('view-current-key-btn');

document.getElementById('settings-btn').onclick = async () => {
    // 获取当前密钥状态
    const keyStatus = await api('game.php?action=get_api_key', 'GET');
    hasCustomApiKey = keyStatus.success && keyStatus.has_key;
    
    apiKeyInput.value = hasCustomApiKey ? '••••••••••••••••' : '';
    apiKeyInput.type = 'password';
    toggleKeyBtn.innerText = '👁️ 显示';
    viewKeyBtn.style.display = hasCustomApiKey ? 'inline-block' : 'none';
    
    document.getElementById('setting-story-bg').value = gameSettings.story_background || '';
    document.getElementById('setting-global-prompt').value = gameSettings.global_prompt || '';
    document.getElementById('setting-auto-scene').checked = gameSettings.auto_scene ?? true;
    document.getElementById('setting-auto-options').checked = gameSettings.auto_options ?? true;
    document.getElementById('setting-group-mode').checked = gameSettings.group_mode ?? false;
    document.getElementById('setting-happy-th').value = gameSettings.happy_ending_threshold ?? 95;
    document.getElementById('setting-sad-th').value = gameSettings.sad_ending_threshold ?? 10;
    document.getElementById('setting-happy-text').value = gameSettings.happy_ending_text || '';
    document.getElementById('setting-sad-text').value = gameSettings.sad_ending_text || '';
    settingsModal.style.display = 'flex';
};

toggleKeyBtn.onclick = () => {
    if (apiKeyInput.type === 'password') {
        apiKeyInput.type = 'text';
        toggleKeyBtn.innerText = '🙈 隐藏';
    } else {
        apiKeyInput.type = 'password';
        toggleKeyBtn.innerText = '👁️ 显示';
    }
};

viewKeyBtn.onclick = async () => {
    const res = await api('game.php?action=get_api_key', 'GET');
    if (res.success && res.has_key) {
        alert('已保存的密钥前缀: ' + res.key_preview + '\n\n如需修改，请直接输入新密钥并保存。\n如需删除，清空输入框后保存。');
    } else {
        alert('当前使用默认密钥');
    }
};

document.getElementById('close-settings-btn').onclick = () => {
    settingsModal.style.display = 'none';
};

document.getElementById('save-settings-btn').onclick = async () => {
    const newKey = apiKeyInput.value.trim();
    
    // 处理API密钥
    if (newKey && newKey !== '••••••••••••••••') {
        // 输入了新密钥，保存
        const keyRes = await api('game.php?action=save_api_key', 'POST', { api_key: newKey });
        if (keyRes.success) {
            hasCustomApiKey = true;
            alert('API密钥已更新');
        } else {
            alert('密钥保存失败: ' + (keyRes.error || '未知错误'));
        }
    } else if (newKey === '' && hasCustomApiKey) {
        // 清空了已保存的密钥，删除
        const keyRes = await api('game.php?action=save_api_key', 'POST', { api_key: '' });
        if (keyRes.success) {
            hasCustomApiKey = false;
            alert('已恢复使用默认密钥');
        }
    }

    const newSettings = {
        story_background: document.getElementById('setting-story-bg').value.trim(),
        global_prompt: document.getElementById('setting-global-prompt').value.trim(),
        auto_scene: document.getElementById('setting-auto-scene').checked,
        auto_options: document.getElementById('setting-auto-options').checked,
        group_mode: document.getElementById('setting-group-mode').checked,
        happy_ending_threshold: parseInt(document.getElementById('setting-happy-th').value),
        sad_ending_threshold: parseInt(document.getElementById('setting-sad-th').value),
        happy_ending_text: document.getElementById('setting-happy-text').value.trim(),
        sad_ending_text: document.getElementById('setting-sad-text').value.trim()
    };
    const settingsRes = await saveSettingsToServer(newSettings);
    if (settingsRes.success) {
        settingsModal.style.display = 'none';
        alert('设置已保存');
    } else {
        alert('设置保存失败: ' + (settingsRes.error || '未知错误'));
    }
};

// =================== 导入/导出 ===================

document.getElementById('export-config-btn').onclick = () => {
    window.open('game.php?action=export_config', '_blank');
};

document.getElementById('import-config-btn').onclick = () => {
    document.getElementById('import-file-input').click();
};

document.getElementById('import-file-input').onchange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    try {
        const text = await file.text();
        const config = JSON.parse(text);
        if (!config.export_version) {
            alert('无效的配置文件');
            return;
        }
        if (!confirm('导入配置将覆盖当前所有游戏数据，确定继续吗？')) return;
        const res = await api('game.php?action=import_config', 'POST', { config });
        if (res.success) {
            alert('配置导入成功，页面将刷新');
            location.reload();
        } else {
            alert(res.error || '导入失败');
        }
    } catch (err) {
        alert('文件解析失败：' + err.message);
    }
    e.target.value = '';
};

document.getElementById('export-story-btn').onclick = () => {
    window.open('game.php?action=export_story', '_blank');
};

document.getElementById('export-html-btn').onclick = () => {
    window.open('game.php?action=export_html_game', '_blank');
};

// =================== 剧情回溯（可点击跳转） ===================

const storyReviewModal = document.getElementById('story-review-modal');
document.getElementById('story-review-btn').onclick = () => {
    const container = document.getElementById('story-review-container');
    container.innerHTML = '';
    
    if (conversationHistory.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:rgba(255,255,255,0.5);padding:40px;">暂无剧情记录</div>';
    } else {
        conversationHistory.forEach((msg, i) => {
            const div = document.createElement('div');
            div.className = 'story-review-item';
            div.innerHTML = `<div class="review-speaker">${msg.speaker}</div><div>${msg.text}</div><div class="review-jump">⏱ 点击回溯至此</div>`;
            div.onclick = () => {
                if (confirm(`回溯到第 ${i + 1} 条对话？\n之后的剧情将被清除。`)) {
                    conversationHistory = conversationHistory.slice(0, i + 1);
                    renderMessages();
                    storyReviewModal.style.display = 'none';
                    saveGameData();
                }
            };
            container.appendChild(div);
        });
    }
    storyReviewModal.style.display = 'flex';
};
document.getElementById('close-story-review').onclick = () => storyReviewModal.style.display = 'none';

// =================== 存档/读档系统 ===================

const saveLoadModal = document.getElementById('save-load-modal');
const saveSlotsDiv = document.getElementById('save-slots');
let isSaveMode = true;

function getLocalSaves() {
    try { return JSON.parse(localStorage.getItem('ai_galgame_saves') || '{}'); }
    catch (e) { return {}; }
}
function setLocalSaves(saves) {
    localStorage.setItem('ai_galgame_saves', JSON.stringify(saves));
}

function renderSaveSlots() {
    const saves = getLocalSaves();
    saveSlotsDiv.innerHTML = '';
    for (let i = 1; i <= 9; i++) {
        const slot = saves[i];
        const div = document.createElement('div');
        div.className = 'save-slot' + (slot ? '' : ' empty');
        if (slot) {
            div.innerHTML = `<div class="slot-number">💾 ${i}</div><div class="slot-time">${slot.time}</div><div class="slot-preview">${slot.preview}</div>`;
            div.onclick = () => {
                if (isSaveMode) {
                    if (confirm(`覆盖存档 ${i}？`)) {
                        saves[i] = {
                            time: new Date().toLocaleString('zh-CN'),
                            preview: conversationHistory.length > 0 ? conversationHistory[conversationHistory.length - 1].text.substring(0, 20) + '...' : '空',
                            data: { conversationHistory, characters, affection, currentScene, currentOptions, currentChapter, chapterTitle, importantEvents, gameSettings }
                        };
                        setLocalSaves(saves);
                        renderSaveSlots();
                    }
                } else {
                    if (confirm(`读取存档 ${i}？当前进度将丢失。`)) {
                        const data = saves[i].data;
                        conversationHistory = data.conversationHistory || [];
                        characters = data.characters || [];
                        affection = data.affection || {};
                        currentScene = data.currentScene || '现代校园';
                        currentOptions = data.currentOptions || [];
                        currentChapter = data.currentChapter || 1;
                        chapterTitle = data.chapterTitle || '第一章：初遇';
                        importantEvents = data.importantEvents || {};
                        if (data.gameSettings) gameSettings = data.gameSettings;
                        renderUI();
                        saveGameData();
                        saveLoadModal.style.display = 'none';
                    }
                }
            };
        } else {
            div.innerHTML = `<div class="slot-number">${i}</div><div class="slot-time">空档位</div>`;
            if (isSaveMode) {
                div.onclick = () => {
                    saves[i] = {
                        time: new Date().toLocaleString('zh-CN'),
                        preview: conversationHistory.length > 0 ? conversationHistory[conversationHistory.length - 1].text.substring(0, 20) + '...' : '空',
                        data: { conversationHistory, characters, affection, currentScene, currentOptions, currentChapter, chapterTitle, importantEvents, gameSettings }
                    };
                    setLocalSaves(saves);
                    renderSaveSlots();
                };
            }
        }
        saveSlotsDiv.appendChild(div);
    }
}

document.getElementById('save-game-btn').onclick = () => {
    isSaveMode = true;
    document.getElementById('save-load-title').innerText = '💾 存档';
    renderSaveSlots();
    saveLoadModal.style.display = 'flex';
};
document.getElementById('load-game-btn').onclick = () => {
    isSaveMode = false;
    document.getElementById('save-load-title').innerText = '📂 读档';
    renderSaveSlots();
    saveLoadModal.style.display = 'flex';
};
document.getElementById('close-save-load').onclick = () => saveLoadModal.style.display = 'none';

// =================== 世界观切换 ===================

const worldSwitchModal = document.getElementById('world-switch-modal');
const worldListDiv = document.getElementById('world-list');

const defaultWorlds = [
    { id: 'campus', name: '现代校园', icon: '🏫', desc: '青涩的校园恋爱物语', bg: '现代校园', prompt: '', system: true },
    { id: 'fantasy', name: '异世界冒险', icon: '⚔️', desc: '剑与魔法的奇幻旅程', bg: '异世界王城', prompt: '这是一个剑与魔法的世界，冒险者们在公会接受任务。', system: true },
    { id: 'cyberpunk', name: '赛博朋克', icon: '🌃', desc: '霓虹闪烁的未来都市', bg: '未来都市', prompt: '高科技与低生活并存的反乌托邦未来。', system: true },
    { id: 'ancient', name: '古风仙侠', icon: '🏔️', desc: '御剑飞行的修仙世界', bg: '仙山云海', prompt: '御剑飞行、修炼成仙的东方玄幻世界。', system: true },
    { id: 'horror', name: '恐怖悬疑', icon: '🕯️', desc: '阴森诡异的未知恐怖', bg: '废弃病院', prompt: '充满未知恐怖的悬疑世界，步步惊心。', system: true },
    { id: 'daily', name: '温馨日常', icon: '🏠', desc: '平淡却温暖的生活', bg: '温馨小屋', prompt: '平凡生活中的温暖小故事。', system: true }
];

function getWorlds() {
    try {
        const custom = JSON.parse(localStorage.getItem('ai_galgame_custom_worlds') || '[]');
        return [...defaultWorlds, ...custom];
    } catch (e) { return [...defaultWorlds]; }
}
function saveCustomWorlds(customWorlds) {
    localStorage.setItem('ai_galgame_custom_worlds', JSON.stringify(customWorlds));
}

function renderWorldList() {
    worldListDiv.innerHTML = '';
    const currentWorldId = gameSettings.world_id || 'campus';
    const allWorlds = getWorlds();
    allWorlds.forEach(world => {
        const div = document.createElement('div');
        div.className = 'world-item' + (world.id === currentWorldId ? ' active' : '') + (world.system ? ' system-world' : '');
        const actions = world.system
            ? ''
            : `<div class="world-actions">
                <button class="world-action-btn" data-action="edit" data-id="${world.id}">✏️ 编辑</button>
                <button class="world-action-btn delete" data-action="delete" data-id="${world.id}">🗑️ 删除</button>
               </div>`;
        div.innerHTML = `<div class="world-icon">${world.icon}</div><div class="world-info"><div class="world-name">${world.name}</div><div class="world-desc">${world.desc || world.bg}</div></div>${actions}`;

        // 点击世界切换（绑定到整个 world-item，排除操作按钮区域）
        div.addEventListener('click', (e) => {
            if (e.target.closest('.world-actions')) return;
            if (world.id === currentWorldId) return;
            if (confirm(`切换到「${world.name}」？\n当前角色和剧情将被重置，建议先存档。`)) {
                gameSettings.world_id = world.id;
                gameSettings.story_background = world.bg;
                gameSettings.world_prompt = world.prompt || '';
                currentScene = world.bg;
                characters = [];
                affection = {};
                conversationHistory = [];
                currentOptions = [];
                currentChapter = 1;
                chapterTitle = '第一章：初遇';
                importantEvents = {};
                messagesContainer.innerHTML = '';
                renderUI();
                saveGameData();
                saveSettingsToServer(gameSettings);
                worldSwitchModal.style.display = 'none';
            }
        });

        // 编辑/删除按钮事件
        const editBtn = div.querySelector('[data-action="edit"]');
        const delBtn = div.querySelector('[data-action="delete"]');
        if (editBtn) editBtn.onclick = (e) => { e.stopPropagation(); openWorldEdit(world); };
        if (delBtn) delBtn.onclick = (e) => { e.stopPropagation(); deleteWorld(world.id); };

        worldListDiv.appendChild(div);
    });
}

document.getElementById('world-switch-btn').onclick = () => {
    renderWorldList();
    worldSwitchModal.style.display = 'flex';
};
document.getElementById('close-world-switch').onclick = () => worldSwitchModal.style.display = 'none';

// =================== 新建/编辑世界观 ===================

const worldEditModal = document.getElementById('world-edit-modal');
let editingWorldId = null;

document.getElementById('add-world-btn').onclick = () => {
    editingWorldId = null;
    document.getElementById('world-edit-title').innerText = '✨ 新建世界观';
    document.getElementById('world-edit-id').value = '';
    document.getElementById('world-edit-name').value = '';
    document.getElementById('world-edit-icon').value = '🏰';
    document.getElementById('world-edit-bg').value = '';
    document.getElementById('world-edit-desc').value = '';
    document.getElementById('world-edit-prompt').value = '';
    worldEditModal.style.display = 'flex';
};

function openWorldEdit(world) {
    editingWorldId = world.id;
    document.getElementById('world-edit-title').innerText = '✏️ 编辑世界观';
    document.getElementById('world-edit-id').value = world.id;
    document.getElementById('world-edit-name').value = world.name;
    document.getElementById('world-edit-icon').value = world.icon;
    document.getElementById('world-edit-bg').value = world.bg;
    document.getElementById('world-edit-desc').value = world.desc || '';
    document.getElementById('world-edit-prompt').value = world.prompt || '';
    worldEditModal.style.display = 'flex';
}

function deleteWorld(id) {
    if (!confirm('确定删除这个世界观？此操作不可恢复。')) return;
    let custom = JSON.parse(localStorage.getItem('ai_galgame_custom_worlds') || '[]');
    custom = custom.filter(w => w.id !== id);
    saveCustomWorlds(custom);
    renderWorldList();
}

document.getElementById('confirm-world-edit').onclick = () => {
    const name = document.getElementById('world-edit-name').value.trim();
    const icon = document.getElementById('world-edit-icon').value.trim();
    const bg = document.getElementById('world-edit-bg').value.trim();
    const desc = document.getElementById('world-edit-desc').value.trim();
    const prompt = document.getElementById('world-edit-prompt').value.trim();
    if (!name || !bg) { alert('请填写世界名称和初始场景'); return; }

    let custom = JSON.parse(localStorage.getItem('ai_galgame_custom_worlds') || '[]');
    if (editingWorldId) {
        const idx = custom.findIndex(w => w.id === editingWorldId);
        if (idx >= 0) {
            custom[idx] = { ...custom[idx], name, icon: icon || '🏰', bg, desc, prompt };
        }
    } else {
        const id = 'custom_' + Date.now();
        custom.push({ id, name, icon: icon || '🏰', bg, desc, prompt, system: false });
    }
    saveCustomWorlds(custom);
    worldEditModal.style.display = 'none';
    renderWorldList();
};
document.getElementById('cancel-world-edit').onclick = () => worldEditModal.style.display = 'none';
worldEditModal.addEventListener('click', (e) => { if (e.target === worldEditModal) worldEditModal.style.display = 'none'; });

// 更新左侧元信息显示
function updateMetaDisplay() {
    const chapterEl = document.getElementById('chapter-display');
    const sceneEl = document.getElementById('scene-display');
    if (chapterEl) chapterEl.innerText = `第${currentChapter}章：${chapterTitle}`;
    if (sceneEl) sceneEl.innerText = currentScene;
}

// =================== CG图鉴 ===================

const cgModal = document.getElementById('cg-modal');
document.getElementById('cg-gallery-btn').onclick = async () => {
    const res = await api('game.php?action=get_cg_gallery', 'GET');
    if (res.success) {
        const container = document.getElementById('cg-list');
        container.innerHTML = '';
        if (res.list.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:rgba(255,255,255,0.5);">暂无CG，游戏中解锁</div>';
        } else {
            res.list.forEach(cg => {
                const div = document.createElement('div');
                div.className = 'cg-item';
                div.innerHTML = `<div style="font-size:40px;margin-bottom:8px;">🖼️</div><div>${cg.cg_key}</div><small style="color:rgba(255,255,255,0.6);">${cg.cg_description || ''}</small>`;
                container.appendChild(div);
            });
        }
        cgModal.style.display = 'flex';
    }
};
document.getElementById('close-cg-modal').onclick = () => cgModal.style.display = 'none';

// =================== 结局图鉴 ===================

const endingModal = document.getElementById('ending-modal');
document.getElementById('ending-gallery-btn').onclick = async () => {
    const res = await api('game.php?action=get_endings', 'GET');
    if (res.success) {
        const container = document.getElementById('ending-list');
        container.innerHTML = '';
        if (res.endings.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:rgba(255,255,255,0.5);">暂无解锁的结局</div>';
        } else {
            res.endings.forEach(ending => {
                const div = document.createElement('div');
                div.className = 'ending-item';
                div.innerHTML = `
                    <div class="ending-title">${ending.ending_title}</div>
                    <div class="ending-desc">${ending.ending_description || ''}</div>
                    <div class="ending-time">${new Date(ending.unlocked_at).toLocaleString()}</div>
                `;
                container.appendChild(div);
            });
        }
        endingModal.style.display = 'flex';
    }
};
document.getElementById('close-ending-modal').onclick = () => endingModal.style.display = 'none';

// =================== 其他 ===================

document.getElementById('upload-portrait-btn').onclick = () => {
    document.getElementById('manage-portraits-btn').click();
};

document.getElementById('logout-btn').onclick = async () => {
    await fetch('user.php?action=logout');
    location.reload();
};

// =================== 登录 ===================

document.getElementById('login-btn').onclick = async () => {
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    const fd = new URLSearchParams();
    fd.append('action', 'login');
    fd.append('username', username);
    fd.append('password', password);
    const res = await fetch('user.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        currentUser = username;
        await loadGameData();
        loginPanel.style.display = 'none';
        gamePanel.style.display = 'flex';
        renderUI();
    } else {
        alert(data.message);
    }
};
document.getElementById('show-register-btn').onclick = async () => {
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    const fd = new URLSearchParams();
    fd.append('action', 'register');
    fd.append('username', username);
    fd.append('password', password);
    const res = await fetch('user.php', { method: 'POST', body: fd });
    const data = await res.json();
    alert(data.message);
};

// =================== 模态框外部点击关闭 ===================

const modals = [addCharModal, editCharModal, settingsModal, cgModal, endingModal, portraitModal, storyReviewModal, saveLoadModal, worldSwitchModal, worldEditModal];
modals.forEach(modal => {
    if (!modal) return;
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.style.display = 'none';
    });
});
