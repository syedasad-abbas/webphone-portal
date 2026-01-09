@extends('layouts.app')

@section('content')
    <section class="card">
        <header class="hero" style="margin-bottom:1rem;">
            <div>
                <h2 class="card-title">Configured carriers</h2>
                <p class="card-subtitle">Monitor registration health and manage routing rules.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost" style="text-decoration:none;">Back to dashboard</a>
        </header>
        <div class="table-wrapper">
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Default Caller ID</th>
                    <th>Domain / Port</th>
                    <th>Transport</th>
                    <th>Registration</th>
                    <th>Prefixes</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($carriers as $carrier)
                    @php
                        $status = $carrier['registration_status'] ?? null;
                        $state = $status['state'] ?? null;
                        $statusClass = match($state) {
                            'success' => 'status-chip ready',
                            'error' => 'status-chip error',
                            default => 'status-chip'
                        };
                        $statusLabel = $status['label'] ?? (!empty($carrier['registration_required']) ? 'Pending' : 'Not required');
                    @endphp
                    <tr>
                        <td>{{ $carrier['name'] }}</td>
                        <td>{{ $carrier['default_caller_id'] }}</td>
                        <td>
                            {{ $carrier['sip_domain'] ?? '—' }}
                            @if(!empty($carrier['sip_port']))
                                :{{ $carrier['sip_port'] }}
                            @endif
                        </td>
                        <td>{{ strtoupper($carrier['transport'] ?? 'udp') }}</td>
                        <td>
                            <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                            @if(!empty($status['detail']))
                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.35rem;">
                                    {{ $status['detail'] }}
                                </div>
                            @endif
                            @if(!empty($carrier['registration_username']))
                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.35rem;">
                                    {{ $carrier['registration_username'] }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if(!empty($carrier['prefixes']))
                                <div style="display:flex; flex-direction:column; gap:0.35rem;">
                                    @foreach($carrier['prefixes'] as $prefix)
                                        <span class="badge">
                                            {{ $prefix['prefix'] ?: '—' }} · {{ $prefix['callerId'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="badge">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('admin.carriers.edit', $carrier['id']) }}" class="btn btn-ghost" style="margin-right:0.35rem;">Edit</a>
                            <form method="POST" action="{{ route('admin.carriers.destroy', $carrier['id']) }}" style="display:inline" onsubmit="return confirm('Delete this carrier?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <header>
            <h2 class="card-title">Add carrier</h2>
            <p class="card-subtitle">Define SIP transport details, caller ID, and optional registration.</p>
        </header>
        <form method="POST" action="{{ route('admin.carriers.store') }}" class="form-grid" id="create-carrier-form">
            @csrf
            <label><span class="required-badge"></span>Name*
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Provider name">
            </label>
            <label>Default Caller ID
                <input type="text" name="callerId" value="{{ old('callerId') }}" placeholder="Optional caller ID">
            </label>
            @php($requiresCallerId = old('callerIdRequired', '1'))
            <label style="grid-column:1/-1; flex-direction:row; align-items:center; gap:0.6rem;">
                <input type="hidden" name="callerIdRequired" value="0">
                <input type="checkbox" name="callerIdRequired" value="1" {{ $requiresCallerId === '1' ? 'checked' : '' }}>
                Requires Caller ID
            </label>
            <label>Prefix (optional)
                <input type="text" name="prefix" value="{{ old('prefix') }}" placeholder="e.g. 100">
            </label>
            <label>Transport
                @php($selectedTransport = old('transport', 'udp'))
                <select name="transport">
                    <option value="udp" {{ $selectedTransport === 'udp' ? 'selected' : '' }}>UDP</option>
                    <option value="tcp" {{ $selectedTransport === 'tcp' ? 'selected' : '' }}>TCP</option>
                    <option value="tls" {{ $selectedTransport === 'tls' ? 'selected' : '' }}>TLS</option>
                </select>
            </label>
            <label><span class="required-badge"></span>Domain / IP*
                <input type="text" name="sipDomain" value="{{ old('sipDomain') }}" placeholder="sip.provider.com" required>
            </label>
            <label><span class="required-badge"></span>Port*
                <input type="number" name="sipPort" value="{{ old('sipPort', 5062) }}" min="1" max="65535" required>
            </label>
            <label style="grid-column:1/-1; flex-direction:row; align-items:center; gap:0.6rem;">
                <input type="checkbox" name="registrationRequired" value="1" {{ old('registrationRequired') ? 'checked' : '' }}>
                Requires Registration
            </label>
            <div id="registration-fields" style="display: {{ old('registrationRequired') ? 'grid' : 'none' }};" class="form-grid">
                <label>Registration Username
                    <input type="text" name="registrationUsername" value="{{ old('registrationUsername') }}" placeholder="Trunk username">
                </label>
                <label>Registration Password
                    <input type="password" name="registrationPassword" value="{{ old('registrationPassword') }}" placeholder="Trunk password">
                </label>
            </div>
            <div style="grid-column:1/-1; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Add carrier</button>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkbox = document.querySelector('input[name="registrationRequired"]');
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
