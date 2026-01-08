@extends('layouts.app')

@section('content')
    <style>
        .required-marker {
            color: #c0392b;
            margin-left: 0.25rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.65rem;
            border-radius: 999px;
            font-size: 0.85rem;
            gap: 0.35rem;
        }
        .status-pill::before {
            content: '';
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: #95a5a6;
        }
        .status-pill.success {
            background: #e6f7ed;
            color: #1e824c;
        }
        .status-pill.success::before {
            background: #2ecc71;
        }
        .status-pill.error {
            background: #fdecea;
            color: #c0392b;
        }
        .status-pill.error::before {
            background: #e74c3c;
        }
        .status-pill.neutral {
            background: #f6f8fa;
            color: #2d3436;
        }
    </style>
    <section>
        <header>
            <h2>Carriers</h2>
        </header>
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Default Caller ID</th>
                <th>Domain / Port</th>
                <th>Transport</th>
                <th>Registration</th>
                <th>Prefixes</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($carriers as $carrier)
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
                        @php
                            $status = $carrier['registration_status'] ?? null;
                            $state = $status['state'] ?? null;
                            $pillClass = match($state) {
                                'success' => 'status-pill success',
                                'error' => 'status-pill error',
                                default => 'status-pill neutral'
                            };
                            $label = $status['label'] ?? (!empty($carrier['registration_required']) ? 'Unknown' : 'Not Required');
                        @endphp
                        @if($status)
                            <span class="{{ $pillClass }}">{{ $label }}</span>
                            @if(!empty($status['detail']))
                                <br><small>{{ $status['detail'] }}</small>
                            @endif
                        @else
                            <span class="status-pill neutral">{{ !empty($carrier['registration_required']) ? 'Unknown' : 'Not Required' }}</span>
                        @endif
                        @if(!empty($carrier['registration_username']))
                            <br><small>{{ $carrier['registration_username'] }}</small>
                        @endif
                    </td>
                    <td>
                        @if(!empty($carrier['prefixes']))
                            <ul>
                                @foreach($carrier['prefixes'] as $prefix)
                                    <li>{{ $prefix['prefix'] }} ({{ $prefix['callerId'] }})</li>
                                @endforeach
                            </ul>
                        @else
                            <span>—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.carriers.edit', $carrier['id']) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.carriers.destroy', $carrier['id']) }}" style="display:inline" onsubmit="return confirm('Delete this carrier?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="secondary">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section style="margin-top: 2rem;">
        <header>
            <h2>Add Carrier</h2>
        </header>
        <form method="POST" action="{{ route('admin.carriers.store') }}" id="create-carrier-form">
            @csrf
            <label>Name <span class="required-marker">*</span>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </label>
            <label>Default Caller ID <span class="required-marker">*</span>
                <input type="text" name="callerId" value="{{ old('callerId') }}" required>
            </label>
            <label>Transport <span class="required-marker">*</span>
                <select name="transport" required>
                    @php($selectedTransport = old('transport', 'udp'))
                    <option value="udp" {{ $selectedTransport === 'udp' ? 'selected' : '' }}>UDP</option>
                    <option value="tcp" {{ $selectedTransport === 'tcp' ? 'selected' : '' }}>TCP</option>
                    <option value="tls" {{ $selectedTransport === 'tls' ? 'selected' : '' }}>TLS</option>
                </select>
            </label>
            <label>Domain / IP <span class="required-marker">*</span>
                <input type="text" name="sipDomain" value="{{ old('sipDomain') }}" placeholder="sip.provider.com" required>
            </label>
            <label>Port <span class="required-marker">*</span>
                <input type="number" name="sipPort" value="{{ old('sipPort', 5060) }}" min="1" max="65535" required>
            </label>
            <label>
                <input type="checkbox" name="registrationRequired" value="1" {{ old('registrationRequired') ? 'checked' : '' }}>
                Requires Registration
            </label>
            <div id="registration-fields" style="display: {{ old('registrationRequired') ? 'block' : 'none' }};">
                <label>Registration Username
                    <input type="text" name="registrationUsername" value="{{ old('registrationUsername') }}">
                </label>
                <label>Registration Password
                    <input type="password" name="registrationPassword" value="{{ old('registrationPassword') }}">
                </label>
            </div>
            <button type="submit">Add Carrier</button>
        </form>
    </section>

    <p style="margin-top: 2rem;">
        <a href="{{ route('admin.dashboard') }}">Back to Dashboard</a>
    </p>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkbox = document.querySelector('input[name="registrationRequired"]');
            const regFields = document.getElementById('registration-fields');
            if (!checkbox || !regFields) {
                return;
            }
            checkbox.addEventListener('change', function () {
                regFields.style.display = this.checked ? 'block' : 'none';
            });
        });
    </script>
@endsection
