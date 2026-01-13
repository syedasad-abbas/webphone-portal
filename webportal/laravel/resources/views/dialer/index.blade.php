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
                        <input type="text" id="dialpad-display" placeholder="Tap digits to dial" readonly>
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
                            syncDisplay(`${hiddenInput.value || ''}+`);
                            playTone('0');
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

                    button.addEventListener('click', () => {
                        if (value === '0' && longPressActive) {
                            longPressActive = false;
                            return;
                        }
                        syncDisplay(`${hiddenInput.value || ''}${value}`);
                        playTone(value);
                    });
                });
                clearButton.addEventListener('click', () => syncDisplay(''));
                backspaceButton.addEventListener('click', () => {
                    const current = hiddenInput.value || '';
                    syncDisplay(current.slice(0, -1));
                });
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    alertBox.style.display = 'none';
                    const formData = new FormData(form);
                    const token = form.querySelector('input[name=\"_token\"]').value;
                    const payload = {
                        destination: formData.get('destination'),
                        callerId: formData.get('callerId')
                    };
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
                            alertBox.textContent = error.message || 'Unable to start the call.';
                            alertBox.style.display = 'block';
                            return;
                        }
                        const data = await response.json();
                        if (data.callUuid) {
                            window.open(`{{ url('/dialer/session') }}/${data.callUuid}`, '_blank', 'width=480,height=540');
                        } else {
                            alertBox.textContent = 'Call queued but no call identifier returned.';
                            alertBox.style.display = 'block';
                        }
                    } catch (error) {
                        alertBox.textContent = 'Network error while queuing the call.';
                        alertBox.style.display = 'block';
                    }
                });
            });
        </script>
    @endif
@endsection
