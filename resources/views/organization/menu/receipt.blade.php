<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Receipt #{{ $order->order_number }}</title>
    @php
        $paperSize = request('paper_size', '80mm');
        $is58mm = in_array(strtolower($paperSize), ['58mm', '58', '2inch', '2']);
        $isDirect = request('direct') == '1';
    @endphp
    <style id="print-stylesheet">
        @page {
            size: {{ $is58mm ? '58mm auto' : '80mm auto' }};
            margin: 0;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        body {
            font-family: 'Consolas', 'Courier New', Courier, monospace, sans-serif;
            width: {{ $is58mm ? '54mm' : '78mm' }};
            max-width: {{ $is58mm ? '54mm' : '78mm' }};
            margin: 0 auto;
            padding: {{ $is58mm ? '4px' : '8px' }};
            color: #000000 !important;
            background: #ffffff !important;
            font-size: {{ $is58mm ? '10px' : '12px' }};
            line-height: {{ $is58mm ? '1.25' : '1.35' }};
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: 900; }
        .divider {
            border-top: 1.5px dashed #000000;
            margin: {{ $is58mm ? '5px 0' : '8px 0' }};
        }
        .header-title {
            font-size: {{ $is58mm ? '13px' : '16px' }};
            font-weight: 900;
            text-transform: uppercase;
            color: #000000 !important;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: {{ $is58mm ? '3px 0' : '5px 0' }};
        }
        th {
            border-bottom: 1.5px solid #000000;
            border-top: 1.5px solid #000000;
            padding: {{ $is58mm ? '2px 0' : '4px 0' }};
            text-align: left;
            font-size: {{ $is58mm ? '9.5px' : '11px' }};
            font-weight: 900;
            color: #000000 !important;
        }
        td {
            padding: {{ $is58mm ? '2.5px 0' : '4px 0' }};
            vertical-align: top;
            font-size: {{ $is58mm ? '9.5px' : '11.5px' }};
            color: #000000 !important;
        }
        .badge {
            display: inline-block;
            padding: 1px 4px;
            background: #000000;
            color: #ffffff !important;
            font-size: {{ $is58mm ? '9px' : '10.5px' }};
            font-weight: 900;
            border-radius: 3px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1.5px solid #000000;
            font-weight: 900;
            font-size: {{ $is58mm ? '9.5px' : '11px' }};
            text-transform: uppercase;
        }
        .paid-badge {
            background: #000000 !important;
            color: #ffffff !important;
        }
        .pending-badge {
            background: #ffffff !important;
            color: #000000 !important;
        }
        @media print {
            .no-print { display: none !important; }
            html, body {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: {{ $is58mm ? '2px' : '4px' }} !important;
                background: #ffffff !important;
                color: #000000 !important;
            }
            * {
                color: #000000 !important;
                text-shadow: none !important;
                box-shadow: none !important;
            }
            .paid-badge {
                background: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    @if(!$isDirect)
    <div class="no-print" style="margin-bottom: 12px; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; align-items: center;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 6px; font-weight: 900; cursor: pointer; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                🖨️ Print Receipt
            </button>
            <div style="font-size: 11px; font-weight: bold; color: #475569; display: flex; align-items: center; gap: 4px;">
                <span>Paper:</span>
                <select id="paperSizeSelect" onchange="changePaperSize(this.value)" style="padding: 4px 8px; font-size: 11px; border: 1px solid #cbd5e1; border-radius: 4px; font-weight: bold; background: #ffffff;">
                    <option value="58mm" {{ $is58mm ? 'selected' : '' }}>58 mm (2-inch Thermal)</option>
                    <option value="80mm" {{ !$is58mm ? 'selected' : '' }}>80 mm (3-inch Thermal)</option>
                </select>
            </div>
            <button onclick="window.close()" style="padding: 8px 12px; background: #ffffff; color: #64748b; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 11px;">
                ✕ Close
            </button>
        </div>
    </div>
    @endif

    <!-- Header -->
    <div class="text-center">
        @if($order->organization && $order->organization->logo)
            <img src="{{ asset('storage/' . $order->organization->logo) }}" style="max-height: {{ $is58mm ? '30px' : '40px' }}; margin-bottom: 4px;">
        @else
            <img src="{{ asset('images/logo.png') }}" style="max-height: {{ $is58mm ? '26px' : '34px' }}; margin-bottom: 4px; display: inline-block;" alt="Vyapaargo">
        @endif
        <div class="header-title">{{ $order->organization->name ?? 'RESTRO POS' }}</div>
        <div class="bold">{{ $order->location->name ?? 'Main Branch' }}</div>
        @if($order->location->address)
            <div style="font-size: {{ $is58mm ? '9px' : '10.5px' }};">{{ $order->location->address }}</div>
        @endif
        @if($order->location->phone)
            <div class="bold">Ph: {{ $order->location->phone }}</div>
        @endif
        @if($order->organization && $order->organization->gst_number)
            <div style="font-size: {{ $is58mm ? '9px' : '10px' }}; font-weight: bold;">GSTIN: {{ $order->organization->gst_number }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <!-- Order Info -->
    <div>
        <div><span class="bold">Order #:</span> {{ $order->order_number }}</div>
        <div><span class="bold">Date:</span> {{ $order->created_at->format('d/m/Y h:i A') }}</div>
        <div><span class="bold">Type / Table:</span> <span class="badge">{{ $order->table ? $order->table->name : ($order->order_type ?? 'TAKEAWAY') }}</span></div>
        <div><span class="bold">Customer:</span> {{ $order->customer_name ?? 'Guest' }}</div>
        @if($order->customer_phone)
            <div><span class="bold">Phone:</span> {{ $order->customer_phone }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th class="text-left" style="width: 50%;">ITEM</th>
                <th class="text-center" style="width: 15%;">QTY</th>
                <th class="text-right" style="width: 15%;">PRICE</th>
                <th class="text-right" style="width: 20%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php
                $itemsToPrint = isset($allOrders) ? $allOrders->pluck('items')->flatten() : $order->items;
                $grossSubtotal = isset($allOrders) ? $allOrders->sum('subtotal') : $order->subtotal;
                $grossCgst = isset($allOrders) ? $allOrders->sum('cgst') : $order->cgst;
                $grossSgst = isset($allOrders) ? $allOrders->sum('sgst') : $order->sgst;
                $cgstPercent = (float)($order->organization->cgst_percent ?? 0);
                $sgstPercent = (float)($order->organization->sgst_percent ?? 0);
            @endphp
            @foreach($itemsToPrint as $item)
            <tr>
                <td class="text-left bold">{{ $item->name_snapshot }}</td>
                <td class="text-center bold">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->price_snapshot, 2) }}</td>
                <td class="text-right bold">{{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Summary -->
    <table style="margin-top: 3px;">
        <tr>
            <td class="text-left">Subtotal:</td>
            <td class="text-right bold">₹{{ number_format($grossSubtotal, 2) }}</td>
        </tr>
        @if($grossCgst > 0)
        <tr>
            <td class="text-left">CGST ({{ $cgstPercent }}%):</td>
            <td class="text-right">₹{{ number_format($grossCgst, 2) }}</td>
        </tr>
        @endif
        @if($grossSgst > 0)
        <tr>
            <td class="text-left">SGST ({{ $sgstPercent }}%):</td>
            <td class="text-right">₹{{ number_format($grossSgst, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td class="text-left header-title" style="font-size: {{ $is58mm ? '11px' : '13px' }};">GRAND TOTAL:</td>
            <td class="text-right header-title" style="font-size: {{ $is58mm ? '12px' : '14px' }};">₹{{ number_format($order->total, 2) }}</td>
        </tr>
        <tr>
            <td class="text-left bold">Payment Status:</td>
            <td class="text-right bold">
                @if($order->payment_status === 'Paid')
                    <span class="status-badge paid-badge">PAID ✅</span>
                @else
                    <span class="status-badge pending-badge">PENDING ⏳</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    @if($order->payment_status === 'Paid')
        <div class="text-center" style="margin: {{ $is58mm ? '5px 0' : '8px 0' }}; padding: {{ $is58mm ? '4px' : '6px' }}; border: 1.5px solid #000000; border-radius: 4px; background: #ffffff;">
            <div style="font-size: {{ $is58mm ? '10px' : '12px' }}; font-weight: 900; color: #000000 !important; text-transform: uppercase;">✅ FULLY PAID</div>
            <div style="font-size: {{ $is58mm ? '8.5px' : '9.5px' }}; font-weight: bold; color: #000000 !important; margin-top: 2px;">AMOUNT DUE: ₹0.00 (NO PAYMENT REQUIRED)</div>
        </div>
    @else
        @php
            $orgUpi = $order->organization->upi_id ?? 'pay@upi';
            $orderUpiString = "upi://pay?pa=" . rawurlencode($orgUpi) . "&pn=" . rawurlencode($order->organization->name ?? 'POS') . "&am=" . number_format($order->total, 2, '.', '') . "&cu=INR&tn=" . rawurlencode('Order #' . $order->order_number);
        @endphp

        <div class="text-center" style="margin-top: 4px;">
            <div style="font-size: {{ $is58mm ? '8.5px' : '9.5px' }}; font-weight: 900; color: #000000 !important; text-transform: uppercase;">SCAN TO PAY EXACT BILL AMOUNT</div>
            <div style="font-size: {{ $is58mm ? '12px' : '14px' }}; font-weight: 900; margin-top: 2px; color: #000000 !important;">₹{{ number_format($order->total, 2) }}</div>
            <div style="display: flex; justify-content: center; margin: 4px 0;">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size($is58mm ? 75 : 90)->margin(0)->generate($orderUpiString) !!}
            </div>
            <div style="font-size: {{ $is58mm ? '7.5px' : '8.5px' }}; font-weight: bold; color: #000000 !important;">GPay | PhonePe | Paytm | BHIM</div>
        </div>
    @endif

    <div class="text-center" style="margin-top: {{ $is58mm ? '6px' : '10px' }};">
        <div class="bold" style="color: #000000 !important; font-size: {{ $is58mm ? '9.5px' : '11px' }};">Thank you for dining with us!</div>
        <div style="font-size: {{ $is58mm ? '8.5px' : '10px' }}; margin-top: 2px; color: #000000 !important;">Please Visit Again 🙏</div>
    </div>

    <script>
    function changePaperSize(size) {
        localStorage.setItem('pos_printer_paper_size', size);
        const url = new URL(window.location.href);
        url.searchParams.set('paper_size', size);
        window.location.href = url.toString();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // If preferred paper size differs from current query, reload with saved preference
        const savedSize = localStorage.getItem('pos_printer_paper_size');
        const urlParams = new URLSearchParams(window.location.search);
        if (savedSize && !urlParams.has('paper_size')) {
            urlParams.set('paper_size', savedSize);
            window.location.search = urlParams.toString();
            return;
        }

        // Auto-print after slight render delay
        setTimeout(function() {
            window.print();
        }, 350);
    });
    </script>

</body>
</html>
