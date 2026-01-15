@extends('layouts.app')

@section('content')
    <section class="card call-panel">
        @if(!session()->has('user_token'))
            <div class="alert alert-error">
                You must be signed in as a portal user to place calls.
            </div>
            <a href="{{ route('user.login') }}" class="btn btn-primary" style="width:max-content;">Go to user login</a>
        @endif

        @if(session()->has('user_token'))
            <header>
                <h2 class="card-title">WebPhone dialer</h2>
                <p class="card-subtitle">Launch real PSTN calls with live controls, recording, and status badges.</p>
            </header>

            <div class="status-chip ready">
                Ready · Calls open in a focused window with mute/unmute, speaker, and hang up controls.
            </div>

            <form id="dialer-form" method="POST" action="{{ route('dialer.dial') }}" class="form-grid">
                @csrf
                <div class="dialpad" style="grid-column:1/-1;">
                    <label>Destination Number</label>
                    <div class="dialpad-display">
                        <input
                            type="text"
                            id="dialpad-display"
                            placeholder="Tap digits to dial"
                            readonly
                        >
                        <input type="hidden" name="destination" id="dialpad-input" required>
                    </div>

                    <div class="dialpad-grid" aria-label="Dial pad">
                        <button type="button" class="dialpad-key" data-value="1">1<span>&nbsp;</span></button>
                        <button type="button" class="dialpad-key" data-value="2">2<span>ABC</span></button>
                        <button type="button" class="dialpad-key" data-value="3">3<span>DEF</span></button>
                        <button type="button" class="dialpad-key" data-value="4">4<span>GHI</span></button>
                        <button type="button" class="dialpad-key" data-value="5">5<span>JKL</span></button>
                        <button type="button" class="dialpad-key" data-value="6">6<span>MNO</span></button>
                        <button type="button" class="dialpad-key" data-value="7">7<span>PQRS</span></button>
                        <button type="button" class="dialpad-key" data-value="8">8<span>TUV</span></button>
                        <button type="button" class="dialpad-key" data-value="9">9<span>WXYZ</span></button>
                        <button type="button" class="dialpad-key dialpad-key--secondary" data-value="*">*</button>
                        <button type="button" class="dialpad-key" data-value="0">0<span>+</span></button>
                        <button type="button" class="dialpad-key dialpad-key--secondary" data-value="#">#</button>
                    </div>

                    <div class="dialpad-actions">
                        <button type="button" class="btn btn-ghost" id="dialpad-clear">Clear</button>
                        <button type="button" class="btn btn-ghost" id="dialpad-backspace">Delete</button>
                    </div>
                </div>

                <label>Caller ID (optional)
                    <input type="text" name="callerId" placeholder="Leave blank to send no caller ID">
                </label>

                <div style="grid-column:1/-1; display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Start call</button>
                </div>
            </form>

            <div id="dialer-alert" class="alert alert-error" style="display:none;"></div>

            {{-- Embedded Live Call Session (same page) --}}
            <div id="live-call-session" style="display:none; margin-top: 16px;">
                <header>
                    <h2 class="card-title">Live call session</h2>
                    <p class="card-subtitle">Monitor status and control the call in real time.</p>
                </header>
                <div id="call-status" class="status-chip">Connecting</div>
                <div class="call-actions">
                    <button type="button" class="btn btn-ghost" data-action="mute" disabled>Mute</button>
                    <button type="button" class="btn btn-ghost" data-action="unmute" disabled>Unmute</button>
                    <button type="button" class="btn btn-danger" data-action="hangup" disabled>Hang up</button>
                </div>
                <div id="call-alert" class="alert alert-error" style="display:none;"></div>
                <div class="divider"></div>
                <div class="badge" id="call-id-badge" style="display:none;"></div>
                <div class="badge" id="call-timer-badge" style="display:none; margin-top: 8px;">Duration · <span id="call-timer">00:00</span></div>
            </div>
        @endif
    </section>

    @if(session()->has('user_token'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('dialer-form');
                const alertBox = document.getElementById('dialer-alert');
                const displayInput = document.getElementById('dialpad-display');
                const hiddenInput = document.getElementById('dialpad-input');
                const dialpadButtons = document.querySelectorAll('.dialpad-key');
                const clearButton = document.getElementById('dialpad-clear');
                const backspaceButton = document.getElementById('dialpad-backspace');
                const dtmfMap = {
                    '1': [697, 1209],
                    '2': [697, 1336],
                    '3': [697, 1477],
                    '4': [770, 1209],
                    '5': [770, 1336],
                    '6': [770, 1477],
                    '7': [852, 1209],
                    '8': [852, 1336],
                    '9': [852, 1477],
                    '*': [941, 1209],
                    '0': [941, 1336],
                    '#': [941, 1477]
                };
                let toneContext = null;
                let toneGain = null;
                let toneOscillators = [];
                if (!form) {
                    return;
                }

                const ensureToneContext = () => {
                    if (!toneContext) {
                        toneContext = new (window.AudioContext || window.webkitAudioContext)();
                        toneGain = toneContext.createGain();
                        toneGain.gain.value = 0.12;
                        toneGain.connect(toneContext.destination);
                    }
                };

                const stopTone = () => {
                    toneOscillators.forEach((osc) => {
                        try {
                            osc.stop();
                        } catch (err) {
                            // Ignore stop errors.
                        }
                    });
                    toneOscillators = [];
                };

                const playTone = async (value) => {
                    const freqs = dtmfMap[value];
                    if (!freqs) {
                        return;
                    }
                    ensureToneContext();
                    try {
                        if (toneContext.state === 'suspended') {
                            await toneContext.resume();
                        }
                    } catch (err) {
                        return;
                    }
                    stopTone();
                    toneOscillators = freqs.map((freq) => {
                        const osc = toneContext.createOscillator();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        osc.connect(toneGain);
                        osc.start();
                        return osc;
                    });
                    setTimeout(stopTone, 120);
                };

                const syncDisplay = (value) => {
                    displayInput.value = value;
                    hiddenInput.value = value;
                };

                // ===== Option (B): keep readonly, but allow paste by catching paste and writing ourselves =====
                const sanitizePhone = (value) => {
                    return (value || '').toString().replace(/[^\d+*#]/g, '');
                };

                const applyPastedValue = (text) => {
                    const cleaned = sanitizePhone(text);
                    syncDisplay(cleaned);
                };

                displayInput.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text');
                    applyPastedValue(text);
                });

                document.addEventListener('paste', (e) => {
                    if (document.activeElement !== displayInput) {
                        return;
                    }
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text');
                    applyPastedValue(text);
                });

                displayInput.addEventListener('click', () => {
                    displayInput.focus();
                });

                // ===== Embedded live session state (same page) =====
                const liveSession = document.getElementById('live-call-session');
                const statusEl = document.getElementById('call-status');
                const alertEl = document.getElementById('call-alert');
                const actionButtons = document.querySelectorAll('#live-call-session [data-action]');
                const csrfToken = form.querySelector('input[name="_token"]').value;
                const startCallButton = form.querySelector('button[type="submit"]');
                const callIdBadge = document.getElementById('call-id-badge');
                const callTimerBadge = document.getElementById('call-timer-badge');
                const callTimerEl = document.getElementById('call-timer');

                let callUuid = null;
                let pollHandle = null;
                let ringState = null;
                let callActive = false;

                let lastStatus = null;

                // timer state
                let callConnectedAt = null;
                let timerHandle = null;

                const formatDuration = (seconds) => {
                    const s = Math.max(0, Number(seconds) || 0);
                    const mm = Math.floor(s / 60);
                    const ss = s % 60;
                    const mmStr = mm < 10 ? `0${mm}` : `${mm}`;
                    const ssStr = ss < 10 ? `0${ss}` : `${ss}`;
                    return `${mmStr}:${ssStr}`;
                };

                const updateTimer = () => {
                    if (!callConnectedAt) {
                        return;
                    }
                    const seconds = Math.floor((Date.now() - callConnectedAt) / 1000);
                    callTimerEl.textContent = formatDuration(seconds);
                };

                const startTimer = () => {
                    if (timerHandle) {
                        return;
                    }
                    callConnectedAt = callConnectedAt || Date.now();
                    callTimerBadge.style.display = 'inline-flex';
                    updateTimer();
                    timerHandle = setInterval(updateTimer, 1000);
                };

                const stopTimer = () => {
                    if (timerHandle) {
                        clearInterval(timerHandle);
                        timerHandle = null;
                    }
                    callConnectedAt = null;
                    callTimerEl.textContent = '00:00';
                    callTimerBadge.style.display = 'none';
                };

                const ringback = (() => {
                    let audioContext = null;
                    let osc = null;
                    let gain = null;
                    let cadenceTimer = null;

                    const ensureContext = () => {
                        if (!audioContext) {
                            audioContext = new (window.AudioContext || window.webkitAudioContext)();
                            osc = audioContext.createOscillator();
                            gain = audioContext.createGain();
                            osc.type = 'sine';
                            osc.frequency.value = 440;
                            gain.gain.value = 0;
                            osc.connect(gain).connect(audioContext.destination);
                            osc.start();
                        }
                    };

                    const resumeContext = async () => {
                        try {
                            if (audioContext && audioContext.state === 'suspended') {
                                await audioContext.resume();
                            }
                        } catch (err) {
                            // Ignore autoplay errors.
                        }
                    };

                    const start = async () => {
                        if (cadenceTimer) {
                            return;
                        }
                        ensureContext();
                        await resumeContext();
                        gain.gain.value = 0.15;
                        cadenceTimer = setInterval(() => {
                            gain.gain.value = 0.15;
                            setTimeout(() => {
                                if (gain) {
                                    gain.gain.value = 0;
                                }
                            }, 2000);
                        }, 6000);
                    };

                    const stop = () => {
                        if (cadenceTimer) {
                            clearInterval(cadenceTimer);
                            cadenceTimer = null;
                        }
                        if (gain) {
                            gain.gain.value = 0;
                        }
                    };

                    return { start, stop };
                })();

                const setControls = (enabled) => {
                    actionButtons.forEach((btn) => {
                        btn.disabled = !enabled;
                    });
                };

                const setStartButton = (disabled) => {
                    startCallButton.disabled = !!disabled;
                };

                setControls(false);
                setStartButton(false);

                const setStatus = (status, data = {}) => {
                    const normalized = (status || '').toLowerCase();
                    lastStatus = normalized;

                    const statusMap = {
                        queued: { label: 'Trying', className: '' },
                        ringing: { label: 'Ringing', className: '' },
                        in_call: { label: 'Answered', className: 'ready' },
                        completed: { label: 'Completed', className: 'ready' },
                        ended: { label: 'Ended', className: 'error' }
                    };
                    const info = statusMap[normalized] || { label: 'Unknown', className: '' };
                    statusEl.textContent = info.label;
                    statusEl.className = `status-chip ${info.className}`.trim();

                    const sipStatus = Number(data.sipStatus);
                    const hasRingingResponse = sipStatus === 180 || sipStatus === 183;

                    if (normalized === 'ringing' && hasRingingResponse) {
                        if (ringState !== 'ringing') {
                            ringback.start();
                            ringState = 'ringing';
                        }
                        return;
                    }

                    if (ringState === 'ringing') {
                        ringback.stop();
                        ringState = null;
                    }

                    if (normalized === 'in_call') {
                        startTimer();
                    }

                    if (normalized === 'ended' || normalized === 'completed') {
                        stopTimer();
                    }
                };

                const showError = (message) => {
                    alertEl.textContent = message || 'Unable to update call.';
                    alertEl.style.display = 'block';
                };

                const renderDiagnostics = (data) => {
                    if (!data) {
                        return;
                    }
                    const sipStatus = Number(data.sipStatus);
                    if (!Number.isNaN(sipStatus) && sipStatus >= 300) {
                        const reason = data.sipReason ? ` ${data.sipReason}` : '';
                        showError(`SIP ${sipStatus}${reason}`);
                        return;
                    }
                    if (data.hangupCause && (data.status === 'ended' || data.status === 'completed')) {
                        showError(`Hangup cause: ${data.hangupCause}`);
                    }
                };

                const pollStatus = async () => {
                    try {
                        const response = await fetch(`/dialer/calls/${callUuid}/status`, {
                            headers: { 'Accept': 'application/json' }
                        });

                        if (!response.ok) {
                            setStatus('ended');
                            showError(`HTTP ${response.status}`);
                            callActive = false;
                            setControls(false);
                            setStartButton(false);
                            startCallButton.disabled = false;
                            stopTimer();
                            return;
                        }

                        const data = await response.json();
                        setStatus(data.status, data);
                        renderDiagnostics(data);

                        if (data.status === 'in_call' || data.status === 'ringing' || data.status === 'queued') {
                            callActive = true;
                            setControls(true);
                            setStartButton(true);
                        }

                        if (data.status === 'ended' || data.status === 'completed') {
                            clearInterval(pollHandle);
                            callActive = false;
                            setControls(false);
                            setStartButton(false);
                            startCallButton.disabled = false;
                            stopTimer();
                        }
                    } catch (error) {
                        setStatus('ended');
                        showError('Network error while updating the call.');
                        callActive = false;
                        setControls(false);
                        setStartButton(false);
                        startCallButton.disabled = false;
                        stopTimer();
                    }
                };

                actionButtons.forEach((button) => {
                    button.addEventListener('click', async () => {
                        const action = button.dataset.action;
                        alertEl.style.display = 'none';
                        try {
                            const response = await fetch(`/dialer/calls/${callUuid}/${action}`, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                }
                            });

                            if (!response.ok) {
                                const data = await response.json();
                                showError(data.message || `HTTP ${response.status}`);
                                return;
                            }

                            if (action === 'hangup') {
                                setStatus('completed');
                                clearInterval(pollHandle);
                                callActive = false;
                                setControls(false);
                                setStartButton(false);
                                startCallButton.disabled = false;
                                stopTimer();
                            }
                        } catch (error) {
                            showError('Network error while updating the call.');
                        }
                    });
                });

                document.addEventListener('click', () => {
                    if (ringState === 'ringing') {
                        ringback.start();
                    }
                }, { once: true });

                // ===== Dialpad logic (before call: build number; during call: send DTMF) =====
                const LONG_PRESS_MS = 500;
                let longPressTimer = null;
                let longPressActive = false;

                dialpadButtons.forEach((button) => {
                    const value = button.dataset.value || '';

                    const handlePress = () => {
                        if (value !== '0') {
                            return;
                        }
                        longPressActive = false;
                        clearTimeout(longPressTimer);
                        longPressTimer = setTimeout(() => {
                            longPressActive = true;

                            if (callActive && callUuid) {
                                ringback.stop();
                                playTone('0');
                                setTimeout(() => {
                                    if (lastStatus === 'ringing') {
                                        ringback.start();
                                    }
                                }, 160);
                            } else {
                                syncDisplay(`${hiddenInput.value || ''}+`);
                                playTone('0');
                            }
                        }, LONG_PRESS_MS);
                    };

                    const handleRelease = () => {
                        if (value !== '0') {
                            return;
                        }
                        clearTimeout(longPressTimer);
                    };

                    button.addEventListener('mousedown', handlePress);
                    button.addEventListener('touchstart', handlePress, { passive: true });
                    button.addEventListener('mouseup', handleRelease);
                    button.addEventListener('mouseleave', handleRelease);
                    button.addEventListener('touchend', handleRelease);
                    button.addEventListener('touchcancel', handleRelease);

                    button.addEventListener('click', async () => {
                        if (value === '0' && longPressActive) {
                            longPressActive = false;
                            return;
                        }

                        if (callActive && callUuid) {
                            ringback.stop();
                            playTone(value);
                            alertEl.style.display = 'none';

                            setTimeout(() => {
                                if (lastStatus === 'ringing') {
                                    ringback.start();
                                }
                            }, 160);

                            try {
                                const response = await fetch(`/dialer/calls/${callUuid}/dtmf`, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({ digits: value })
                                });

                                if (!response.ok) {
                                    const data = await response.json();
                                    showError(data.message || `HTTP ${response.status}`);
                                }
                            } catch (error) {
                                showError('Network error while sending DTMF.');
                            }
                            return;
                        }

                        syncDisplay(`${hiddenInput.value || ''}${value}`);
                        playTone(value);
                    });
                });

                clearButton.addEventListener('click', () => {
                    if (callActive) {
                        return;
                    }
                    syncDisplay('');
                });

                backspaceButton.addEventListener('click', () => {
                    if (callActive) {
                        return;
                    }
                    const current = hiddenInput.value || '';
                    syncDisplay(current.slice(0, -1));
                });

                // ===== Start call (no popup; same page session) =====
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    alertBox.style.display = 'none';

                    liveSession.style.display = 'block';
                    callIdBadge.style.display = 'none';
                    alertEl.style.display = 'none';
                    setStatus('queued');
                    stopTimer();

                    const formData = new FormData(form);
                    const token = form.querySelector('input[name=\"_token\"]').value;
                    const payload = {
                        destination: formData.get('destination'),
                        callerId: formData.get('callerId')
                    };

                    startCallButton.disabled = true;

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify(payload)
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            alertBox.textContent = error.message || `HTTP ${response.status}`;
                            alertBox.style.display = 'block';
                            startCallButton.disabled = false;
                            setStatus('ended');
                            showError(`HTTP ${response.status}`);
                            return;
                        }

                        const data = await response.json();
                        if (data.callUuid) {
                            callUuid = data.callUuid;
                            callIdBadge.style.display = 'block';
                            callIdBadge.textContent = `Call ID · ${callUuid}`;

                            callActive = true;
                            setControls(true);
                            setStartButton(true);

                            pollStatus();
                            if (pollHandle) {
                                clearInterval(pollHandle);
                            }
                            pollHandle = setInterval(pollStatus, 1000);
                        } else {
                            alertBox.textContent = 'Call queued but no call identifier returned.';
                            alertBox.style.display = 'block';
                            startCallButton.disabled = false;
                            setStatus('ended');
                        }
                    } catch (error) {
                        alertBox.textContent = 'Network error while queuing the call.';
                        alertBox.style.display = 'block';
                        startCallButton.disabled = false;
                        setStatus('ended');
                        showError('Network error while queuing the call.');
                    }
                });
            });
        </script>
    @endif
@endsection
