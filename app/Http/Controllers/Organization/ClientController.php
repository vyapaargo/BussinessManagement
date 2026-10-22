<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $orgId = auth()->user()->organization_id;
        $locationId = \App\Services\LocationManager::getActiveLocationId();
        
        $clientsCount = Client::where('organization_id', $orgId)->count();
        $isClientLimitReached = \App\Services\SubscriptionService::hasReachedLimit($orgId, 'max_clients', $clientsCount);
        $maxClientsAllowed = \App\Services\SubscriptionService::getFeatureValue($orgId, 'max_clients', 'Unlimited');

        $query = Client::where('organization_id', $orgId);

        // Branch filtering: Org Admins see all, Staff see active branch + unassigned
        if (!auth()->user()->hasRole('Organization Admin') && !auth()->user()->hasRole('Super Admin')) {
            $query->where(function($q) use ($locationId) {
                $q->where('location_id', $locationId)
                  ->orWhereNull('location_id');
            });
        } elseif ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gst_number', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->paginate(15)->withQueryString();

        return view('organization.clients.index', [
            'clients' => $clients,
            'isClientLimitReached' => $isClientLimitReached,
            'limitReached' => $isClientLimitReached,
            'clientsCount' => $clientsCount,
            'totalClients' => $clientsCount,
            'maxClientsAllowed' => $maxClientsAllowed,
            'maxClients' => $maxClientsAllowed,
        ]);
    }

    public function create()
    {
        $orgId = auth()->user()->organization_id;
        $clientsCount = Client::where('organization_id', $orgId)->count();

        if (\App\Services\SubscriptionService::hasReachedLimit($orgId, 'max_clients', $clientsCount)) {
            $limit = \App\Services\SubscriptionService::getFeatureValue($orgId, 'max_clients');
            return redirect()->route('organization.clients.index')
                ->with('error', "Client limit reached ({$clientsCount}/{$limit}). Please upgrade your plan to add more clients.");
        }

        return view('organization.clients.create');
    }

    public function store(Request $request)
    {
        $orgId = auth()->user()->organization_id;
        $currentClientsCount = Client::where('organization_id', $orgId)->count();

        if (\App\Services\SubscriptionService::hasReachedLimit($orgId, 'max_clients', $currentClientsCount)) {
            $limit = \App\Services\SubscriptionService::getFeatureValue($orgId, 'max_clients');
            return back()->withInput()->with('error', "Client limit reached ({$currentClientsCount}/{$limit}). Please upgrade your plan to add more clients.");
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'regex:/^(?:\+91[\-\s]?|0)?[6-9](?:[\-\s]?[0-9]){9}$/'],
            'email' => 'nullable|email:rfc,filter|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ], [
            'name.required' => 'Client / Company name is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $normalizedPhone = $request->phone ? substr(preg_replace('/[^0-9]/', '', $request->phone), -10) : null;

        Client::create([
            'organization_id' => auth()->user()->organization_id,
            'location_id' => \App\Services\LocationManager::getActiveLocationId(),
            'name' => $request->name,
            'phone' => $normalizedPhone,
            'email' => $request->email,
            'address' => $request->address,
            'gst_number' => $request->gst_number,
            'notes' => $request->notes,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('organization.clients.index')->with('success', 'Client created successfully.');
    }

    public function show(Client $client)
    {
        abort_if($client->organization_id !== auth()->user()->organization_id, 403);
        return view('organization.clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        abort_if($client->organization_id !== auth()->user()->organization_id, 403);
        return view('organization.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        abort_if($client->organization_id !== auth()->user()->organization_id, 403);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'regex:/^(?:\+91[\-\s]?|0)?[6-9](?:[\-\s]?[0-9]){9}$/'],
            'email' => 'nullable|email:rfc,filter|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ], [
            'name.required' => 'Client / Company name is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $normalizedPhone = $request->phone ? substr(preg_replace('/[^0-9]/', '', $request->phone), -10) : null;

        $client->update([
            'name' => $request->name,
            'phone' => $normalizedPhone,
            'email' => $request->email,
            'address' => $request->address,
            'gst_number' => $request->gst_number,
            'notes' => $request->notes,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('organization.clients.index')->with('success', 'Client updated successfully.');
    }

    public function apiSearch(Request $request)
    {
        $search = $request->q;
        $locationId = \App\Services\LocationManager::getActiveLocationId();
        
        $clients = Client::where('organization_id', auth()->user()->organization_id)
            ->where('is_active', true)
            ->when(!auth()->user()->hasRole('Organization Admin') && !auth()->user()->hasRole('Super Admin'), function($q) use ($locationId) {
                $q->where(function($sub) use ($locationId) {
                    $sub->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                });
            })
            ->when($search, function($q) use ($search) {
                $q->where(function($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->select('id', 'name', 'phone')
            ->limit(10)
            ->get();
            
        return response()->json($clients);
    }

    public function quickStore(Request $request)
    {
        $orgId = auth()->user()->organization_id;
        $currentClientsCount = Client::where('organization_id', $orgId)->count();

        if (\App\Services\SubscriptionService::hasReachedLimit($orgId, 'max_clients', $currentClientsCount)) {
            $limit = \App\Services\SubscriptionService::getFeatureValue($orgId, 'max_clients');
            return response()->json([
                'success' => false,
                'message' => "Client limit reached ({$currentClientsCount}/{$limit}). Please upgrade your plan to add more clients."
            ], 422);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'regex:/^(?:\+91[\-\s]?|0)?[6-9](?:[\-\s]?[0-9]){9}$/'],
            'email' => 'nullable|email:rfc,filter|max:255',
        ], [
            'name.required' => 'Client name is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $normalizedPhone = $request->phone ? substr(preg_replace('/[^0-9]/', '', $request->phone), -10) : null;

        $client = Client::create([
            'organization_id' => auth()->user()->organization_id,
            'location_id' => \App\Services\LocationManager::getActiveLocationId(),
            'name' => $request->name,
            'phone' => $normalizedPhone,
            'email' => $request->email,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone
            ]
        ]);
    }

    public function destroy(Client $client)
    {
        abort_if($client->organization_id !== auth()->user()->organization_id, 403);
        $client->delete();
        return redirect()->route('organization.clients.index')->with('success', 'Client deleted successfully.');
    }
}
