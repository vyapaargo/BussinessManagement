<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Invoice;
use App\Models\RestaurantOrder;
use Illuminate\Support\Facades\Log;

class PublicComplaintController extends Controller
{
    public function create(Request $request)
    {
        $orgId = $request->query('org_id');
        $invoiceId = $request->query('invoice_id');
        $orderId = $request->query('order_id');
        
        // Security check: If invoice_id or order_id is provided, strictly require a valid cryptographic signature
        if ($invoiceId || $orderId) {
            if (!$request->hasValidSignature()) {
                abort(403, 'Invalid or expired secure complaint link.');
            }
        }
        
        $selectedOrg = $orgId ? Organization::find($orgId) : null;
        $invoice = null;
        $order = null;

        if ($invoiceId) {
            $candidateInvoice = Invoice::with(['client', 'organization'])->find($invoiceId);
            if ($candidateInvoice) {
                $invoice = $candidateInvoice;
                if (!$selectedOrg) {
                    $selectedOrg = $candidateInvoice->organization;
                }
            }
        }

        if ($orderId) {
            $candidateOrder = RestaurantOrder::with(['organization'])->find($orderId);
            if ($candidateOrder) {
                $order = $candidateOrder;
                if (!$selectedOrg) {
                    $selectedOrg = $candidateOrder->organization;
                }
            }
        }

        $organizations = Organization::where('is_active', true)->get(['id', 'name']);

        return view('public.complaint', compact('organizations', 'selectedOrg', 'invoice', 'order'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,filter|max:255',
            'phone' => ['nullable', 'string', 'regex:/^(?:\+91[\-\s]?|0)?[6-9](?:[\-\s]?[0-9]){9}$/'],
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'priority' => 'nullable|in:Low,Medium,High,Urgent',
            'invoice_id' => 'nullable|exists:invoices,id',
            'order_id' => 'nullable|exists:restaurant_orders,id',
        ], [
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please provide a valid email address.',
            'email.email' => 'Please enter a valid email address.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'subject.required' => 'Please enter the subject of your complaint.',
            'description.required' => 'Please provide a description of the issue.',
            'description.min' => 'Please provide a detailed description (at least 10 characters).',
        ]);

        $orgId = $request->organization_id;
        $normalizedPhone = $request->phone ? substr(preg_replace('/[^0-9]/', '', $request->phone), -10) : null;

        // Find existing client or create new client record
        $client = Client::where('organization_id', $orgId)
            ->where(function($q) use ($request, $normalizedPhone) {
                $q->where('email', $request->email);
                if ($normalizedPhone) {
                    $q->orWhere('phone', $normalizedPhone);
                }
            })->first();

        if (!$client) {
            $client = Client::create([
                'organization_id' => $orgId,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'is_active' => true,
            ]);
        }

        $complaint = Complaint::create([
            'organization_id' => $orgId,
            'client_id' => $client->id,
            'invoice_id' => $request->invoice_id ?: null,
            'order_id' => $request->order_id ?: null,
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority ?: 'Medium',
            'status' => 'Open',
        ]);

        Log::info("Public complaint #{$complaint->id} registered by client {$client->email} for Org #{$orgId}" . 
            ($request->invoice_id ? " (Linked Invoice #{$request->invoice_id})" : "") .
            ($request->order_id ? " (Linked Order #{$request->order_id})" : "")
        );

        return redirect()->route('public.complaint.success', $complaint->id)
            ->with('success', 'Your complaint ticket has been submitted successfully.');
    }

    public function success(Complaint $complaint)
    {
        $complaint->load(['organization', 'client', 'invoice', 'order']);
        return view('public.complaint_success', compact('complaint'));
    }
}
