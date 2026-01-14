<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CarrierController extends Controller
{
    protected function backend(string $token)
    {
        return Http::withToken($token)->baseUrl(config('services.backend.url'));
    }

    public function index(Request $request)
    {
        $token = $request->session()->get('admin_token');
        $carriers = collect();

        if ($token) {
            $response = $this->backend($token)->get('/admin/carriers');
            if ($response->ok()) {
                $carriers = collect($response->json());
            }
        }

        return view('admin.carriers', [
            'carriers' => $carriers,
        ]);
    }

    public function store(Request $request)
    {
        $token = $request->session()->get('admin_token');
        $data = $request->validate([
            'name' => ['required', 'string'],
            'callerId' => ['nullable', 'string'],
            'callerIdRequired' => ['nullable', 'boolean'],
            'transport' => ['required', 'in:udp,tcp,tls'],
            'sipDomain' => ['required', 'string'],
            'sipPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'registrationRequired' => ['nullable', 'boolean'],
            'registrationUsername' => ['nullable', 'string'],
            'registrationPassword' => ['nullable', 'string'],
            'prefix' => ['nullable', 'string'],
            'outboundProxy' => ['nullable', 'string'],
        ]);

        $payload = $data;
        $payload['registrationRequired'] = $request->boolean('registrationRequired');
        $payload['callerIdRequired'] = $request->boolean('callerIdRequired', false);
        $payload['registrationUsername'] = filled($data['registrationUsername'] ?? null) ? $data['registrationUsername'] : null;
        $payload['registrationPassword'] = filled($data['registrationPassword'] ?? null) ? $data['registrationPassword'] : null;
        $payload['prefix'] = filled($data['prefix'] ?? null) ? $data['prefix'] : null;
        $payload['outboundProxy'] = filled($data['outboundProxy'] ?? null) ? $data['outboundProxy'] : null;

        $response = $this->backend($token)->post('/admin/carriers', $payload);
        if ($response->failed()) {
            return back()->withErrors([
                'name' => 'Unable to create carrier. Verify data or try again.',
            ])->withInput();
        }

        return redirect()->route('admin.carriers.index')->with('status', 'Carrier added successfully.');
    }

    public function edit(Request $request, string $carrierId)
    {
        $token = $request->session()->get('admin_token');
        $carrierResponse = $this->backend($token)->get("/admin/carriers/{$carrierId}");
        if ($carrierResponse->failed()) {
            return redirect()->route('admin.carriers.index')->withErrors([
                'carrier' => 'Carrier not found.'
            ]);
        }

        return view('admin.edit-carrier', [
            'carrier' => $carrierResponse->json()
        ]);
    }

    public function update(Request $request, string $carrierId)
    {
        $token = $request->session()->get('admin_token');
        $data = $request->validate([
            'name' => ['required', 'string'],
            'callerId' => ['nullable', 'string'],
            'callerIdRequired' => ['nullable', 'boolean'],
            'transport' => ['required', 'in:udp,tcp,tls'],
            'sipDomain' => ['required', 'string'],
            'sipPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'registrationRequired' => ['nullable', 'boolean'],
            'registrationUsername' => ['nullable', 'string'],
            'registrationPassword' => ['nullable', 'string'],
            'prefix' => ['nullable', 'string'],
            'outboundProxy' => ['nullable', 'string'], // ✅ ADD
        ]);

        $payload = $data;
        $payload['registrationRequired'] = $request->boolean('registrationRequired');
        $payload['callerIdRequired'] = $request->boolean('callerIdRequired');
        $payload['registrationUsername'] = filled($data['registrationUsername'] ?? null) ? $data['registrationUsername'] : null;
        $payload['registrationPassword'] = filled($data['registrationPassword'] ?? null) ? $data['registrationPassword'] : null;
        $payload['prefix'] = filled($data['prefix'] ?? null) ? $data['prefix'] : null;
        $payload['outboundProxy'] = filled($data['outboundProxy'] ?? null) ? $data['outboundProxy'] : null;

        $response = $this->backend($token)->put("/admin/carriers/{$carrierId}", $payload);
        if ($response->failed()) {
            return back()->withErrors('Unable to update carrier.')->withInput();
        }

        return redirect()->route('admin.carriers.index')->with('status', 'Carrier updated.');
    }

    public function destroy(Request $request, string $carrierId)
    {
        $token = $request->session()->get('admin_token');
        $response = $this->backend($token)->delete("/admin/carriers/{$carrierId}");
        if ($response->failed()) {
            return redirect()->route('admin.carriers.index')->withErrors('Unable to delete carrier.');
        }

        return redirect()->route('admin.carriers.index')->with('status', 'Carrier deleted.');
    }
}
