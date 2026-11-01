{{-- HAVEN AI — Executive Automotive Assistant Widget (Seamless Adaptive Dark & Light Theme) --}}
<div id="haven-global-orb-wrapper" style="position:fixed;bottom:24px;right:24px;z-index:9999;font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

    {{-- Floating Assistant Panel (Seamlessly adapts to website theme: light or dark) --}}
    <div id="haven-orb-panel" class="haven-lexus-panel" style="display:none;position:fixed;bottom:88px;right:24px;width:390px;max-width:calc(100vw - 32px);height:585px;max-height:calc(100vh - 110px);border-radius:16px;overflow:hidden;flex-direction:column;z-index:9999;transition:transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;">
        
        {{-- Top Header (Adapts with website theme) --}}
        <div class="haven-lexus-header" style="padding:0.9rem 1.15rem;display:flex;align-items:center;justify-content:space-between;border-top-left-radius:16px;border-top-right-radius:16px;flex-shrink:0;user-select:none;transition:background 0.2s ease, border-color 0.2s ease;">
            {{-- Brand Left --}}
            <div style="display:flex;align-items:center;gap:0.65rem;min-width:0;">
                <div class="haven-lexus-logo-badge" style="width:28px;height:28px;border-radius:50%;border:1.5px solid transparent;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all 0.2s ease;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;min-width:0;">
                    <span class="haven-lexus-header-title" style="font-size:0.92rem;font-weight:600;letter-spacing:-0.01em;white-space:nowrap;transition:color 0.2s ease;">Haven AI Assistant</span>
                </div>
            </div>

            {{-- Action Controls Right (Clear Chat & Minimize) --}}
            <div style="display:flex;align-items:center;gap:0.35rem;flex-shrink:0;">
                {{-- Clear Chat Button --}}
                <button type="button" class="haven-lexus-header-btn" onclick="clearGlobalOrbHistory()" title="Clear conversation history" style="background:none;border:none;cursor:pointer;padding:6px;display:flex;align-items:center;justify-content:center;border-radius:6px;transition:all 0.15s ease;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>

                {{-- Chevron Down Minimize Button --}}
                <button type="button" class="haven-lexus-header-btn" onclick="toggleGlobalOrb()" title="Minimize" style="background:none;border:none;cursor:pointer;padding:6px;display:flex;align-items:center;justify-content:center;border-radius:6px;transition:all 0.15s ease;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Chat Stream Canvas (Theme adaptive) --}}
        <div id="panel-chat-stream" class="haven-lexus-stream" style="flex:1;overflow-y:auto;padding:1.15rem 1.15rem;display:flex;flex-direction:column;gap:0.9rem;transition:background 0.2s ease;">
            
            {{-- Date Marker: Today --}}
            <div class="haven-lexus-date-marker" style="text-align:center;font-size:0.72rem;font-weight:500;margin:0.25rem 0 0.5rem 0;user-select:none;">
                Today
            </div>

            {{-- Initial AI Welcome Message --}}
            <div class="haven-lexus-msg-bot">
                <div class="haven-lexus-avatar">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="haven-lexus-bubble-bot">
                        Welcome to Haven. Where craftsmanship meets automotive intelligence. I can help you explore diagnostic history, interpret acoustic noise signatures, analyze vibration FFT telemetry, or guide your powertrain repairs. How may I assist you today?
                    </div>
                    <div class="haven-lexus-meta-bot">
                        Haven AI Assistant | <span id="initial-bot-time">12:00 AM</span>
                    </div>
                </div>
            </div>

            {{-- Disclaimer & Verification Notice --}}
            <div class="haven-lexus-disclaimer">
                <span>
                    You are interacting with an AI-powered assistant. Some responses may be incorrect, and image or video generation is not supported. For verified specifications and the latest diagnostic procedures, please refer to official technical manuals: <a href="{{ route('learning.index') }}" target="_blank">haven-diagnostics.local</a> ↗ <span id="disclaimer-time">· 12:00 AM</span>
                </span>
            </div>

        </div>

        {{-- Quick Suggestion Pills (Horizontally Scrollable) --}}
        <div id="haven-quick-chips-row" class="haven-lexus-chips-row" style="padding:0.5rem 1rem;display:flex;align-items:center;gap:0.4rem;overflow-x:auto;white-space:nowrap;scrollbar-width:none;-webkit-overflow-scrolling:touch;transition:background 0.2s ease, border-color 0.2s ease;">
            <button type="button" class="haven-clean-chip" onclick="askGlobalOrb('What cars do I have in my garage?')">🚗 My Garage</button>
            <button type="button" class="haven-clean-chip" onclick="askGlobalOrb('Difference between rod knock and lifter tick?')">⚡ Knock vs Tick</button>
            <button type="button" class="haven-clean-chip" onclick="askGlobalOrb('What does OBD-II code P0300 mean?')">🔍 P0300 Code</button>
            <button type="button" class="haven-clean-chip" onclick="askGlobalOrb('Explain Peak G, RMS G, and Crest Factor')">📈 Vibration FFT</button>
            <button type="button" class="haven-clean-chip" onclick="askGlobalOrb('Is it safe to drive with a flashing check engine light?')">⚠️ Safe to Drive?</button>
        </div>

        {{-- Plus Tools Drawer (Hidden by default, toggled with +) --}}
        <div id="haven-plus-menu" class="haven-lexus-plus-drawer" style="display:none;padding:0.6rem 1rem;grid-template-columns:repeat(2, 1fr);gap:0.5rem;transition:background 0.2s ease, border-color 0.2s ease;">
            <button type="button" class="haven-plus-item" onclick="startVoiceFromPlus();">
                <span style="font-size:1rem;">🎙️</span> Voice Dictation
            </button>
            <button type="button" class="haven-plus-item" onclick="askGlobalOrb('How to run a diagnostic in Haven'); togglePlusMenu();">
                <span style="font-size:1rem;">🚀</span> How to Test
            </button>
            <button type="button" class="haven-plus-item" onclick="askGlobalOrb('Show my registered cars in garage'); togglePlusMenu();">
                <span style="font-size:1rem;">🚗</span> Garage Telemetry
            </button>
            <button type="button" class="haven-plus-item" onclick="clearGlobalOrbHistory(); togglePlusMenu();" style="color:#ef4444 !important;">
                <span style="font-size:1rem;">🧹</span> Clear History
            </button>
        </div>

        {{-- Voice Dictation Listening Banner --}}
        <div id="panel-voice-status" class="haven-lexus-voice-banner" style="display:none;padding:0.45rem 1rem;font-size:0.75rem;align-items:center;gap:0.5rem;">
            <span class="haven-voice-dot"></span>
            <span id="panel-voice-status-text">Listening... Speak your automotive question</span>
        </div>

        {{-- Bottom Input Bar: Minimalist & Clean with +, Input, and Send --}}
        <div class="haven-lexus-input-bar" style="padding:0.85rem 1.15rem;display:flex;align-items:center;gap:0.75rem;flex-shrink:0;transition:background 0.2s ease, border-color 0.2s ease;">
            {{-- Plus Button --}}
            <button type="button" id="panel-plus-btn" class="haven-plus-btn" onclick="togglePlusMenu()" title="Quick Tools & Voice">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>

            {{-- Text Input --}}
            <input type="text" id="panel-orb-input" class="haven-lexus-input-field" placeholder="Send a message" style="flex:1;border:none;outline:none;background:transparent;font-size:0.9rem;padding:0.35rem 0.2rem;font-family:inherit;" onkeydown="if(event.key === 'Enter') submitGlobalOrbQuestion();" oninput="handleInputState(this);">

            {{-- Paper Plane Send Button --}}
            <button type="button" id="panel-send-btn" class="haven-send-btn" onclick="submitGlobalOrbQuestion()" title="Send message">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>

    </div>

    {{-- Floating Assistant Launcher Button --}}
    <div id="haven-orb-trigger" onclick="toggleGlobalOrb()" title="Haven AI Assistant" style="cursor:pointer;position:relative;display:flex;align-items:center;justify-content:center;user-select:none;">
        {{-- Subtle ambient aura --}}
        <div class="haven-lexus-launcher-aura"></div>
        
        {{-- Launcher Disc --}}
        <div id="haven-master-orb" class="haven-lexus-launcher-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
        </div>

        {{-- Floating Hover Tooltip --}}
        <div class="haven-lexus-tooltip">
            <span>Haven AI Assistant</span>
        </div>
    </div>

</div>

{{-- Component CSS Styles: Pure Automatic Theme Adaptation (Follows Website Theme) --}}
<style>
/* ============================================================
   1. LIGHT THEME (When Website is in Light Mode)
   ============================================================ */
html[data-theme="light"] .haven-lexus-panel,
[data-theme="light"] .haven-lexus-panel {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.04) !important;
}

html[data-theme="light"] .haven-lexus-header,
[data-theme="light"] .haven-lexus-header {
    background: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
}

html[data-theme="light"] .haven-lexus-logo-badge,
[data-theme="light"] .haven-lexus-logo-badge {
    background: #0f172a !important;
    border-color: #0f172a !important;
}

html[data-theme="light"] .haven-lexus-header-title,
[data-theme="light"] .haven-lexus-header-title {
    color: #0f172a !important;
}

html[data-theme="light"] .haven-lexus-header-btn,
[data-theme="light"] .haven-lexus-header-btn {
    color: #64748b !important;
}

html[data-theme="light"] .haven-lexus-header-btn:hover,
[data-theme="light"] .haven-lexus-header-btn:hover {
    color: #0f172a !important;
    background: #f1f5f9 !important;
}

html[data-theme="light"] .haven-lexus-stream,
[data-theme="light"] .haven-lexus-stream {
    background: #ffffff !important;
}

html[data-theme="light"] .haven-lexus-date-marker,
[data-theme="light"] .haven-lexus-date-marker {
    color: #94a3b8 !important;
}

html[data-theme="light"] .haven-lexus-bubble-bot,
[data-theme="light"] .haven-lexus-bubble-bot {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
}

html[data-theme="light"] .haven-lexus-bubble-bot strong,
[data-theme="light"] .haven-lexus-bubble-bot strong {
    color: #0284c7 !important;
}

html[data-theme="light"] .haven-lexus-bubble-bot code,
[data-theme="light"] .haven-lexus-bubble-bot code {
    background: #f1f5f9 !important;
    color: #0284c7 !important;
}

html[data-theme="light"] .haven-lexus-meta-bot,
[data-theme="light"] .haven-lexus-meta-bot {
    color: #94a3b8 !important;
}

html[data-theme="light"] .haven-lexus-bubble-user,
[data-theme="light"] .haven-lexus-bubble-user {
    background: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
}

html[data-theme="light"] .haven-lexus-meta-user,
[data-theme="light"] .haven-lexus-meta-user {
    color: #94a3b8 !important;
}

html[data-theme="light"] .haven-lexus-disclaimer,
[data-theme="light"] .haven-lexus-disclaimer {
    color: #64748b !important;
}

html[data-theme="light"] .haven-lexus-disclaimer a,
[data-theme="light"] .haven-lexus-disclaimer a {
    color: #0284c7 !important;
}

html[data-theme="light"] .haven-lexus-chips-row,
[data-theme="light"] .haven-lexus-chips-row {
    background: #fafafa !important;
    border-top: 1px solid #f1f5f9 !important;
}

html[data-theme="light"] .haven-clean-chip,
[data-theme="light"] .haven-clean-chip {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #334155 !important;
}

html[data-theme="light"] .haven-clean-chip:hover,
[data-theme="light"] .haven-clean-chip:hover {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

html[data-theme="light"] .haven-lexus-plus-drawer,
[data-theme="light"] .haven-lexus-plus-drawer {
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
}

html[data-theme="light"] .haven-plus-item,
[data-theme="light"] .haven-plus-item {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
}

html[data-theme="light"] .haven-plus-item:hover,
[data-theme="light"] .haven-plus-item:hover {
    background: #f1f5f9 !important;
}

html[data-theme="light"] .haven-lexus-voice-banner,
[data-theme="light"] .haven-lexus-voice-banner {
    background: #f0fdf4 !important;
    border-top: 1px solid #bbf7d0 !important;
    color: #15803d !important;
}

html[data-theme="light"] .haven-lexus-input-bar,
[data-theme="light"] .haven-lexus-input-bar {
    background: #ffffff !important;
    border-top: 1px solid #e2e8f0 !important;
}

html[data-theme="light"] .haven-lexus-input-field,
[data-theme="light"] .haven-lexus-input-field {
    color: #0f172a !important;
}

html[data-theme="light"] .haven-lexus-input-field::placeholder,
[data-theme="light"] .haven-lexus-input-field::placeholder {
    color: #94a3b8 !important;
}

html[data-theme="light"] .haven-plus-btn,
[data-theme="light"] .haven-plus-btn {
    color: #64748b !important;
}

html[data-theme="light"] .haven-send-btn,
[data-theme="light"] .haven-send-btn {
    color: #94a3b8 !important;
}

html[data-theme="light"] .haven-send-btn.active,
[data-theme="light"] .haven-send-btn.active {
    color: #0f172a !important;
}


/* ============================================================
   2. DARK THEME (When Website is in Dark Mode or Default)
   ============================================================ */
html:not([data-theme="light"]) .haven-lexus-panel,
html[data-theme="dark"] .haven-lexus-panel,
[data-theme="dark"] .haven-lexus-panel {
    background: #0d111a !important;
    border: 1px solid #1e293b !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.65), 0 0 0 1px #1e293b !important;
}

html:not([data-theme="light"]) .haven-lexus-header,
html[data-theme="dark"] .haven-lexus-header,
[data-theme="dark"] .haven-lexus-header {
    background: #07090e !important;
    border-bottom: 1px solid #1e293b !important;
}

html:not([data-theme="light"]) .haven-lexus-logo-badge,
html[data-theme="dark"] .haven-lexus-logo-badge,
[data-theme="dark"] .haven-lexus-logo-badge {
    background: #000000 !important;
    border-color: rgba(255, 255, 255, 0.25) !important;
}

html:not([data-theme="light"]) .haven-lexus-header-title,
html[data-theme="dark"] .haven-lexus-header-title,
[data-theme="dark"] .haven-lexus-header-title {
    color: #ffffff !important;
}

html:not([data-theme="light"]) .haven-lexus-header-btn,
html[data-theme="dark"] .haven-lexus-header-btn,
[data-theme="dark"] .haven-lexus-header-btn {
    color: #94a3b8 !important;
}

html:not([data-theme="light"]) .haven-lexus-header-btn:hover,
html[data-theme="dark"] .haven-lexus-header-btn:hover,
[data-theme="dark"] .haven-lexus-header-btn:hover {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.08) !important;
}

html:not([data-theme="light"]) .haven-lexus-stream,
html[data-theme="dark"] .haven-lexus-stream,
[data-theme="dark"] .haven-lexus-stream {
    background: #0d111a !important;
}

html:not([data-theme="light"]) .haven-lexus-date-marker,
html[data-theme="dark"] .haven-lexus-date-marker,
[data-theme="dark"] .haven-lexus-date-marker {
    color: #64748b !important;
}

html:not([data-theme="light"]) .haven-lexus-bubble-bot,
html[data-theme="dark"] .haven-lexus-bubble-bot,
[data-theme="dark"] .haven-lexus-bubble-bot {
    background: #131a27 !important;
    border: 1px solid #1e293b !important;
    color: #f8fafc !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
}

html:not([data-theme="light"]) .haven-lexus-bubble-bot strong,
html[data-theme="dark"] .haven-lexus-bubble-bot strong,
[data-theme="dark"] .haven-lexus-bubble-bot strong {
    color: #38bdf8 !important;
}

html:not([data-theme="light"]) .haven-lexus-bubble-bot code,
html[data-theme="dark"] .haven-lexus-bubble-bot code,
[data-theme="dark"] .haven-lexus-bubble-bot code {
    background: #1a2436 !important;
    color: #38bdf8 !important;
}

html:not([data-theme="light"]) .haven-lexus-meta-bot,
html[data-theme="dark"] .haven-lexus-meta-bot,
[data-theme="dark"] .haven-lexus-meta-bot {
    color: #64748b !important;
}

html:not([data-theme="light"]) .haven-lexus-bubble-user,
html[data-theme="dark"] .haven-lexus-bubble-user,
[data-theme="dark"] .haven-lexus-bubble-user {
    background: #1e293b !important;
    border: 1px solid #2e3d55 !important;
    color: #f8fafc !important;
}

html:not([data-theme="light"]) .haven-lexus-meta-user,
html[data-theme="dark"] .haven-lexus-meta-user,
[data-theme="dark"] .haven-lexus-meta-user {
    color: #64748b !important;
}

html:not([data-theme="light"]) .haven-lexus-disclaimer,
html[data-theme="dark"] .haven-lexus-disclaimer,
[data-theme="dark"] .haven-lexus-disclaimer {
    color: #64748b !important;
}

html:not([data-theme="light"]) .haven-lexus-disclaimer a,
html[data-theme="dark"] .haven-lexus-disclaimer a,
[data-theme="dark"] .haven-lexus-disclaimer a {
    color: #38bdf8 !important;
}

html:not([data-theme="light"]) .haven-lexus-chips-row,
html[data-theme="dark"] .haven-lexus-chips-row,
[data-theme="dark"] .haven-lexus-chips-row {
    background: #090d14 !important;
    border-top: 1px solid #1e293b !important;
}

html:not([data-theme="light"]) .haven-clean-chip,
html[data-theme="dark"] .haven-clean-chip,
[data-theme="dark"] .haven-clean-chip {
    background: #131a27 !important;
    border: 1px solid #1e293b !important;
    color: #94a3b8 !important;
}

html:not([data-theme="light"]) .haven-clean-chip:hover,
html[data-theme="dark"] .haven-clean-chip:hover,
[data-theme="dark"] .haven-clean-chip:hover {
    background: #1a2436 !important;
    border-color: #38bdf8 !important;
    color: #ffffff !important;
}

html:not([data-theme="light"]) .haven-lexus-plus-drawer,
html[data-theme="dark"] .haven-lexus-plus-drawer,
[data-theme="dark"] .haven-lexus-plus-drawer {
    background: #090d14 !important;
    border-top: 1px solid #1e293b !important;
}

html:not([data-theme="light"]) .haven-plus-item,
html[data-theme="dark"] .haven-plus-item,
[data-theme="dark"] .haven-plus-item {
    background: #131a27 !important;
    border: 1px solid #1e293b !important;
    color: #f8fafc !important;
}

html:not([data-theme="light"]) .haven-plus-item:hover,
html[data-theme="dark"] .haven-plus-item:hover,
[data-theme="dark"] .haven-plus-item:hover {
    background: #1a2436 !important;
}

html:not([data-theme="light"]) .haven-lexus-voice-banner,
html[data-theme="dark"] .haven-lexus-voice-banner,
[data-theme="dark"] .haven-lexus-voice-banner {
    background: rgba(22, 163, 74, 0.15) !important;
    border-top: 1px solid rgba(22, 163, 74, 0.3) !important;
    color: #4ade80 !important;
}

html:not([data-theme="light"]) .haven-lexus-input-bar,
html[data-theme="dark"] .haven-lexus-input-bar,
[data-theme="dark"] .haven-lexus-input-bar {
    background: #0d111a !important;
    border-top: 1px solid #1e293b !important;
}

html:not([data-theme="light"]) .haven-lexus-input-field,
html[data-theme="dark"] .haven-lexus-input-field,
[data-theme="dark"] .haven-lexus-input-field {
    color: #f8fafc !important;
}

html:not([data-theme="light"]) .haven-lexus-input-field::placeholder,
html[data-theme="dark"] .haven-lexus-input-field::placeholder,
[data-theme="dark"] .haven-lexus-input-field::placeholder {
    color: #64748b !important;
}

html:not([data-theme="light"]) .haven-plus-btn,
html[data-theme="dark"] .haven-plus-btn,
[data-theme="dark"] .haven-plus-btn {
    color: #94a3b8 !important;
}

html:not([data-theme="light"]) .haven-send-btn,
html[data-theme="dark"] .haven-send-btn,
[data-theme="dark"] .haven-send-btn {
    color: #64748b !important;
}

html:not([data-theme="light"]) .haven-send-btn.active,
html[data-theme="dark"] .haven-send-btn.active,
[data-theme="dark"] .haven-send-btn.active {
    color: #38bdf8 !important;
}

/* ============================================================
   3. SHARED STRUCTURAL RULES
   ============================================================ */
.haven-lexus-launcher-btn {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    position: relative;
    cursor: pointer;
    background: #000000;
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35), 0 0 20px rgba(2, 132, 199, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease;
    overflow: hidden;
}

.haven-lexus-launcher-btn:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45), 0 0 28px rgba(2, 132, 199, 0.45);
}

.haven-lexus-launcher-btn.active {
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.4), 0 10px 28px rgba(0, 0, 0, 0.45);
}

.haven-lexus-launcher-aura {
    position: absolute;
    width: 66px;
    height: 66px;
    border-radius: 50%;
    border: 1.5px solid rgba(2, 132, 199, 0.4);
    animation: havenPulseAura 3s cubic-bezier(0, 0.2, 0.8, 1) infinite;
    pointer-events: none;
}

@keyframes havenPulseAura {
    0% { transform: scale(0.85); opacity: 0.8; }
    100% { transform: scale(1.55); opacity: 0; }
}

.haven-lexus-tooltip {
    position: absolute;
    right: 66px;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 500;
    white-space: nowrap;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
    opacity: 0;
    transform: translateX(6px);
    transition: all 0.2s ease;
    pointer-events: none;
}

#haven-orb-trigger:hover .haven-lexus-tooltip {
    opacity: 1;
    transform: translateX(0);
}

.haven-lexus-msg-bot {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    animation: havenFadeIn 0.25s ease;
}

.haven-lexus-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #000000;
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}

.haven-lexus-bubble-bot {
    border-radius: 16px;
    border-top-left-radius: 4px;
    padding: 0.85rem 1.1rem;
    font-size: 0.85rem;
    line-height: 1.58;
    word-break: break-word;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.haven-lexus-bubble-bot p {
    margin: 0.35rem 0;
}
.haven-lexus-bubble-bot p:first-child { margin-top: 0; }
.haven-lexus-bubble-bot p:last-child { margin-bottom: 0; }

.haven-lexus-bubble-bot strong {
    font-weight: 600;
}

.haven-lexus-bubble-bot code {
    padding: 0.1rem 0.35rem;
    border-radius: 3px;
    font-size: 0.75rem;
    font-family: monospace;
}

.haven-lexus-bubble-bot ul {
    margin: 0.4rem 0;
    padding-left: 1.15rem;
}
.haven-lexus-bubble-bot li {
    margin: 0.25rem 0;
    line-height: 1.5;
}

.haven-lexus-meta-bot {
    font-size: 0.68rem;
    margin-top: 0.35rem;
    margin-left: 0.25rem;
    font-weight: 500;
}

.haven-lexus-msg-user {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    animation: havenFadeIn 0.2s ease;
}

.haven-lexus-bubble-user {
    border-radius: 16px;
    border-bottom-right-radius: 4px;
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    line-height: 1.45;
    max-width: 82%;
    word-break: break-word;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.haven-lexus-meta-user {
    font-size: 0.68rem;
    text-align: right;
    margin-top: 0.35rem;
    margin-right: 0.25rem;
    font-weight: 500;
}

.haven-lexus-disclaimer {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 0.68rem;
    line-height: 1.45;
    margin: 0.75rem 0;
    padding: 0 0.5rem;
    user-select: none;
}

.haven-lexus-disclaimer a {
    text-decoration: none;
}

.haven-clean-chip {
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.74rem;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    flex-shrink: 0;
    font-family: inherit;
}

.haven-plus-item {
    border-radius: 8px;
    padding: 0.45rem 0.65rem;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    text-align: left;
    transition: background 0.15s ease;
}

.haven-plus-btn,
.haven-send-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s ease;
}

.haven-voice-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #16a34a;
    display: inline-block;
    animation: havenPulseDot 1s infinite alternate;
}

@keyframes havenPulseDot {
    from { opacity: 0.4; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1.2); }
}

@keyframes havenFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Mobile Viewport Overrides ── */
@media (max-width: 640px) {
    #haven-global-orb-wrapper {
        bottom: 16px !important;
        right: 16px !important;
    }
    .haven-lexus-launcher-btn {
        width: 48px !important;
        height: 48px !important;
    }
    .haven-lexus-launcher-aura {
        width: 58px !important;
        height: 58px !important;
    }
    .haven-lexus-tooltip {
        display: none !important;
    }
    #haven-orb-panel {
        position: fixed !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        max-width: none !important;
        max-height: 100vh !important;
        border-radius: 0 !important;
        bottom: 0 !important;
        right: 0 !important;
    }
}
</style>

{{-- Assistant Client Scripts --}}
<script>
(function() {
    let orbPanelOpen = false;
    let voiceEnabled = true;
    let isListening = false;
    let recognition = null;

    const panel = document.getElementById('haven-orb-panel');
    const masterOrb = document.getElementById('haven-master-orb');
    const stream = document.getElementById('panel-chat-stream');
    const input = document.getElementById('panel-orb-input');
    const sendBtn = document.getElementById('panel-send-btn');
    const voiceStatus = document.getElementById('panel-voice-status');
    const voiceStatusText = document.getElementById('panel-voice-status-text');
    const plusMenu = document.getElementById('haven-plus-menu');

    const STORAGE_KEY = 'haven_lexus_assistant_history_v1';

    function getFormattedTime() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // Set initial timestamps
    const nowTime = getFormattedTime();
    const initBot = document.getElementById('initial-bot-time');
    if (initBot) initBot.textContent = nowTime;
    const discTime = document.getElementById('disclaimer-time');
    if (discTime) discTime.textContent = '· ' + nowTime;

    // Toggle assistant panel
    window.toggleGlobalOrb = function() {
        orbPanelOpen = !orbPanelOpen;
        if (orbPanelOpen) {
            panel.style.display = 'flex';
            masterOrb.classList.add('active');
            setTimeout(() => {
                input.focus();
                stream.scrollTop = stream.scrollHeight;
            }, 60);
        } else {
            panel.style.display = 'none';
            masterOrb.classList.remove('active');
            if (plusMenu) plusMenu.style.display = 'none';
            if (isListening && recognition) {
                recognition.stop();
            }
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        }
    };

    // Toggle Plus tools drawer
    window.togglePlusMenu = function() {
        if (!plusMenu) return;
        const isShown = plusMenu.style.display === 'grid';
        plusMenu.style.display = isShown ? 'none' : 'grid';
        const plusBtn = document.getElementById('panel-plus-btn');
        if (plusBtn) {
            plusBtn.style.color = isShown ? '#0284c7' : '';
        }
    };

    // Handle input field text state
    window.handleInputState = function(el) {
        if (!sendBtn) return;
        if (el.value.trim().length > 0) {
            sendBtn.classList.add('active');
        } else {
            sendBtn.classList.remove('active');
        }
    };

    // Web Speech API Voice Recognition
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';

        recognition.onstart = function() {
            isListening = true;
            voiceStatus.style.display = 'flex';
            voiceStatusText.textContent = 'Listening... Speak your automotive question';
        };

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            input.value = transcript;
            handleInputState(input);
            voiceStatusText.textContent = `Heard: "${transcript}"`;
            setTimeout(() => {
                submitGlobalOrbQuestion();
            }, 400);
        };

        recognition.onerror = function() {
            stopListening();
            voiceStatusText.textContent = 'Could not catch voice audio. Please try again.';
            setTimeout(() => { voiceStatus.style.display = 'none'; }, 2000);
        };

        recognition.onend = function() {
            stopListening();
        };
    }

    function startListening() {
        if (!recognition) {
            alert('Speech recognition is supported in Google Chrome, Microsoft Edge, and Safari.');
            return;
        }
        try {
            recognition.start();
        } catch (e) {
            console.error(e);
        }
    }

    function stopListening() {
        isListening = false;
        if (voiceStatus) {
            setTimeout(() => { voiceStatus.style.display = 'none'; }, 1000);
        }
    }

    window.startVoiceFromPlus = function() {
        togglePlusMenu();
        startListening();
    };

    // Text to Speech
    function speakText(text) {
        if (!voiceEnabled || !('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();

        const clean = text
            .replace(/[*#_~`]/g, '')
            .replace(/([^\w\s.,!?'"/-])/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        const utterance = new SpeechSynthesisUtterance(clean);
        utterance.rate = 1.02;
        utterance.pitch = 1.0;
        window.speechSynthesis.speak(utterance);
    }

    // Local Storage History Management
    function getHistory() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch(e) {
            return [];
        }
    }

    function saveMessageToHistory(role, text, time) {
        try {
            const hist = getHistory();
            hist.push({
                id: 'lexus-msg-' + Date.now(),
                role: role,
                text: text,
                time: time || getFormattedTime()
            });
            if (hist.length > 50) hist.splice(0, hist.length - 50);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(hist));
        } catch(e) {
            console.warn('Failed to save to history:', e);
        }
    }

    window.clearGlobalOrbHistory = function() {
        if (!confirm('Clear your Haven AI Assistant conversation?')) return;
        localStorage.removeItem(STORAGE_KEY);
        const t = getFormattedTime();
        stream.innerHTML = `
            <div class="haven-lexus-date-marker" style="text-align:center;font-size:0.72rem;font-weight:500;margin:0.25rem 0 0.5rem 0;user-select:none;">Today</div>
            <div class="haven-lexus-msg-bot">
                <div class="haven-lexus-avatar">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="haven-lexus-bubble-bot">
                        Welcome to Haven. Where craftsmanship meets automotive intelligence. I can help you explore diagnostic history, interpret acoustic noise signatures, analyze vibration FFT telemetry, or guide your powertrain repairs. How may I assist you today?
                    </div>
                    <div class="haven-lexus-meta-bot">
                        Haven AI Assistant | ${escapeHtml(t)}
                    </div>
                </div>
            </div>
            <div class="haven-lexus-disclaimer">
                <span>
                    You are interacting with an AI-powered assistant. Some responses may be incorrect, and image or video generation is not supported. For verified specifications and the latest diagnostic procedures, please refer to official technical manuals. · ${escapeHtml(t)}
                </span>
            </div>
        `;
    };

    function parseMarkdown(text) {
        const lines = text.split('\n');
        let html = '';
        let inList = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (!line) {
                if (inList) { html += '</ul>'; inList = false; }
                continue;
            }

            if (line.startsWith('•') || line.startsWith('- ') || line.startsWith('* ')) {
                if (!inList) {
                    html += '<ul style="margin:0.35rem 0;padding-left:1.15rem;">';
                    inList = true;
                }
                const bulletContent = line.replace(/^[•\-\*]\s*/, '');
                html += `<li style="margin:0.25rem 0;">${formatInlineMd(bulletContent)}</li>`;
                continue;
            } else {
                if (inList) { html += '</ul>'; inList = false; }
            }

            if (line.startsWith('### ')) {
                html += `<div style="font-weight:700;color:var(--h-teal);margin:0.4rem 0 0.2rem 0;">${escapeHtml(line.replace('### ', ''))}</div>`;
                continue;
            }

            html += `<p style="margin:0.35rem 0;">${formatInlineMd(line)}</p>`;
        }

        if (inList) html += '</ul>';
        return html;
    }

    function formatInlineMd(str) {
        return str
            .replace(/\*\*(.*?)\*\*/g, '<strong style="font-weight:600;">$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
    }

    function createBotMessageNode(text, timeStr) {
        timeStr = timeStr || getFormattedTime();
        const wrap = document.createElement('div');
        wrap.className = 'haven-lexus-msg-bot';
        wrap.innerHTML = `
            <div class="haven-lexus-avatar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <div style="flex:1;min-width:0;">
                <div class="haven-lexus-bubble-bot">
                    ${parseMarkdown(text)}
                </div>
                <div class="haven-lexus-meta-bot">
                    Haven AI Assistant | ${escapeHtml(timeStr)}
                </div>
            </div>
        `;
        return wrap;
    }

    function createUserMessageNode(text, timeStr) {
        timeStr = timeStr || getFormattedTime();
        const wrap = document.createElement('div');
        wrap.className = 'haven-lexus-msg-user';
        wrap.innerHTML = `
            <div class="haven-lexus-bubble-user">
                <span>${escapeHtml(text)}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <polyline points="18 6 9 17 4 12"></polyline>
                    <polyline points="22 10 15 17 13 15"></polyline>
                </svg>
            </div>
            <div class="haven-lexus-meta-user">
                You | ${escapeHtml(timeStr)}
            </div>
        `;
        return wrap;
    }

    // Load Chat History
    function loadHistory() {
        const history = getHistory();
        if (!history || history.length === 0) return;

        history.forEach(item => {
            if (item.role === 'user') {
                stream.appendChild(createUserMessageNode(item.text, item.time));
            } else {
                stream.appendChild(createBotMessageNode(item.text, item.time));
            }
        });
        setTimeout(() => { stream.scrollTop = stream.scrollHeight; }, 60);
    }

    // Quick chip questions
    window.askGlobalOrb = function(q) {
        input.value = q;
        handleInputState(input);
        submitGlobalOrbQuestion();
    };

    window.submitGlobalOrbQuestion = function() {
        const question = input.value.trim();
        if (!question) return;

        input.value = '';
        handleInputState(input);
        input.focus();

        const currentTime = getFormattedTime();

        // Append user bubble & save
        stream.appendChild(createUserMessageNode(question, currentTime));
        saveMessageToHistory('user', question, currentTime);
        stream.scrollTop = stream.scrollHeight;

        // Append clean placeholder
        const tempId = 'lexus-typing-' + Date.now();
        const thinkingWrap = document.createElement('div');
        thinkingWrap.className = 'haven-lexus-msg-bot';
        thinkingWrap.id = tempId;
        thinkingWrap.innerHTML = `
            <div class="haven-lexus-avatar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <div style="flex:1;min-width:0;">
                <div class="haven-lexus-bubble-bot" style="opacity:0.7;font-style:italic;">
                    Haven AI is formulating your diagnosis...
                </div>
            </div>
        `;
        stream.appendChild(thinkingWrap);
        stream.scrollTop = stream.scrollHeight;

        // Post request to /ai/ask
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch('/ai/ask', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ question: question })
        })
        .then(res => {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(data => {
            const botTime = getFormattedTime();
            const botNode = createBotMessageNode(data.answer, botTime);

            const placeholder = document.getElementById(tempId);
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.replaceChild(botNode, placeholder);
            } else {
                stream.appendChild(botNode);
            }

            saveMessageToHistory('bot', data.answer, botTime);
            stream.scrollTop = stream.scrollHeight;
            speakText(data.answer);
        })
        .catch(err => {
            console.error('Haven AI Error:', err);
            const placeholder = document.getElementById(tempId);
            if (placeholder) {
                placeholder.innerHTML = `
                    <div class="haven-lexus-avatar" style="background:#ef4444;border-color:#ef4444;">
                        <span style="color:#fff;font-weight:700;font-size:0.75rem;">!</span>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="haven-lexus-bubble-bot" style="border-color:rgba(239,68,68,0.4);color:#ef4444;">
                            Unable to reach Haven AI service right now. Please check your network connection and try again.
                        </div>
                    </div>
                `;
            }
        });
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize saved history
    loadHistory();
})();
</script>