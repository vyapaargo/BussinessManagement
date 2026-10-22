@extends('layouts.super-admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800">Manage Subscription Plans</h2>
                <p class="text-xs text-gray-500 mt-1">Configure flexible duration plans (Days, Months, Years) and module features.</p>
            </div>
            <a href="{{ route('super-admin.plans.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow hover:bg-indigo-700 transition">+ Add Plan</a>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-4 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl mb-4 text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100">
            <div class="p-6 text-gray-900">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="p-3 border-b text-xs font-bold text-gray-600 uppercase">Plan Name</th>
                            <th class="p-3 border-b text-xs font-bold text-gray-600 uppercase">Duration</th>
                            <th class="p-3 border-b text-xs font-bold text-gray-600 uppercase">Price</th>
                            <th class="p-3 border-b text-xs font-bold text-gray-600 uppercase">Type / Target</th>
                            <th class="p-3 border-b text-xs font-bold text-gray-600 uppercase">Features</th>
                            <th class="p-3 border-b text-xs font-bold text-gray-600 uppercase">Status</th>
                            <th class="p-3 border-b text-right text-xs font-bold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($plans as $plan)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="p-3 font-bold text-gray-900">
                                    {{ $plan->name }}
                                    <div class="text-[11px] text-gray-400 font-mono">ID: {{ $plan->id }}</div>
                                </td>
                                <td class="p-3 font-semibold text-gray-700">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200">
                                        ⏱️ {{ $plan->formatted_duration }}
                                    </span>
                                </td>
                                <td class="p-3 font-mono font-black text-gray-900">
                                    ₹{{ number_format($plan->effective_price, 2) }}
                                </td>
                                <td class="p-3 text-xs">
                                    <span class="font-semibold text-gray-800 uppercase">{{ $plan->type }}</span>
                                    <span class="text-gray-400 block text-[10px]">Target: {{ ucfirst($plan->category) }}</span>
                                </td>
                                <td class="p-3 text-xs text-gray-500">
                                    <span class="bg-indigo-50 text-indigo-700 font-semibold px-2.5 py-0.5 rounded-full border border-indigo-100">{{ $plan->features->count() }} features</span>
                                </td>

                                <td class="p-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $plan->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <a href="{{ route('super-admin.plans.show', $plan) }}" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Plan Details">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a href="{{ route('super-admin.plans.edit', $plan) }}" class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edit Plan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('super-admin.plans.toggle-status', $plan) }}" method="POST" onsubmit="return confirm('Change status for plan {{ $plan->name }}?');" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="p-1.5 {{ $plan->is_active ? 'text-gray-500 hover:text-amber-600 hover:bg-amber-50' : 'text-gray-500 hover:text-emerald-600 hover:bg-emerald-50' }} rounded-lg transition" title="{{ $plan->is_active ? 'Deactivate Plan' : 'Activate Plan' }}">
                                                @if($plan->is_active)
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @else
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                @endif
                                            </button>
                                        </form>
                                        <form action="{{ route('super-admin.plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this plan? If active subscriptions depend on it, it will be safely deactivated to protect billing integrity.');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-gray-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete / Deactivate Plan">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($plans->hasPages())
                <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                    {{ $plans->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
