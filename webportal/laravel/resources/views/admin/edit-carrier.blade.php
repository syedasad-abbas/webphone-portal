@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Edit carrier</h2>
            <p class="card-subtitle">Update SIP routing, registration, and caller ID defaults.</p>
        </header>
        <form method="POST" action="{{ route('admin.carriers.update', $carrier['id']) }}" class="form-grid" id="edit-carrier-form">
            @csrf
            @method('PUT')
            <label><span class="required-badge"></span>Name*
                <input type="text" name="name" value="{{ old('name', $carrier['name'] ?? '') }}" required>
            </label>
            <label>Default Caller ID
                <input type="text" name="callerId" value="{{ old('callerId', $carrier['default_caller_id'] ?? '') }}" placeholder="Optional caller ID">
            </label>
            @php($requiresCallerId = old('callerIdRequired', $carrier['caller_id_required'] ?? false))
            <label style="grid-column:1/-1; flex-direction:row; align-items:center; gap:0.6rem;">
                <input type="hidden" name="callerIdRequired" value="0">
                <input type="checkbox" name="callerIdRequired" value="1" {{ $requiresCallerId ? 'checked' : '' }}>
                Requires Caller ID
            </label>
            <label>Prefix (optional)
                <input type="text" name="prefix" value="{{ old('prefix') }}" placeholder="e.g. 100">
            </label>
            <label>Transport
                @php($selectedTransport = old('transport', $carrier['transport'] ?? 'udp'))
                <select name="transport">
                    <option value="udp" {{ $selectedTransport === 'udp' ? 'selected' : '' }}>UDP</option>
                    <option value="tcp" {{ $selectedTransport === 'tcp' ? 'selected' : '' }}>TCP</option>
                    <option value="tls" {{ $selectedTransport === 'tls' ? 'selected' : '' }}>TLS</option>
                </select>
            </label>
            <label>Outbound Proxy (optional)
                <input type="text"
                    name="outboundProxy"
                    value="{{ old('outboundProxy', $carrier['outbound_proxy'] ?? '') }}"
                    placeholder="sip:169.197.85.204:5060">
            </label>

            <label><span class="required-badge"></span>Domain / IP*
                <input type="text" name="sipDomain" value="{{ old('sipDomain', $carrier['sip_domain'] ?? '') }}" required>
            </label>
            <label><span class="required-badge"></span>Port*
                <input type="number" name="sipPort" value="{{ old('sipPort', $carrier['sip_port'] ?? 5062) }}" min="1" max="65535" required>
            </label>
            @php($registrationRequired = old('registrationRequired', $carrier['registration_required'] ?? false))
            <label style="grid-column:1/-1; flex-direction:row; align-items:center; gap:0.6rem;">
                <input type="hidden" name="registrationRequired" value="0">
                <input type="checkbox" name="registrationRequired" value="1" {{ $registrationRequired ? 'checked' : '' }}>
                Requires Registration
            </label>
            <div id="registration-fields" style="display: {{ $registrationRequired ? 'grid' : 'none' }};" class="form-grid">
                <label>Registration Username
                    <input type="text" name="registrationUsername" value="{{ old('registrationUsername', $carrier['registration_username'] ?? '') }}" placeholder="Trunk username">
                </label>
                <label>Registration Password (leave blank to keep)
                    <input type="password" name="registrationPassword" value="{{ old('registrationPassword') }}" placeholder="Optional new password">
                </label>
            </div>
            <div style="grid-column:1/-1; display:flex; justify-content:space-between; gap:1rem;">
                <a href="{{ route('admin.carriers.index') }}" class="btn btn-ghost" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary">Update carrier</button>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkbox = document.querySelector('input[name="registrationRequired"][type="checkbox"]');
            const regFields = document.getElementById('registration-fields');
            if (!checkbox || !regFields) {
                return;
            }
            checkbox.addEventListener('change', function () {
                regFields.style.display = this.checked ? 'grid' : 'none';
            });
        });
    </script>
@endsection
