@extends('layouts.app')

@section('content')
    <section>
        @if(!session()->has('user_token'))
            <article class="error">
                You must be signed in as a portal user to place calls.
            </article>
            <p><a href="{{ route('user.login') }}">Go to user login</a></p>
        @endif
        @if(session()->has('user_token'))
            <header>
                <h2>WebPhone Dialer</h2>
                <p>Calls launch in a new tab with live controls.</p>
            </header>
            <form id="dialer-form" method="POST" action="{{ route('dialer.dial') }}">
                @csrf
                <label>Destination Number
                    <input type="text" name="destination" required>
                </label>
                <label>Caller ID (optional)
                    <input type="text" name="callerId">
                </label>
                <button type="submit">Start Call</button>
            </form>
            <article id="dialer-alert" class="error" style="display:none;margin-top:1rem;"></article>
        @endif

        <form method="POST" action="{{ route('user.logout') }}" style="margin-top: 1rem;">
            @csrf
            <button type="submit">Logout</button>
        </form>
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
                    const token = form.querySelector('input[name="_token"]').value;
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
