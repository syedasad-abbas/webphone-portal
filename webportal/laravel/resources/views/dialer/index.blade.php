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
                <label>Destination Number
                    <input type="text" name="destination" required placeholder="+1 555 123 4567">
                </label>
                <label>Caller ID (optional)
                    <input type="text" name="callerId" placeholder="Override default caller ID">
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
                if (!form) {
                    return;
                }
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
