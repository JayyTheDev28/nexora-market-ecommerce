<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waybill — {{ $trackingNumber }} ({{ $order->order_number }})</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
            background: #f1f5f9;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar {
            max-width: 680px;
            margin: 0 auto 16px auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .layout-toggle {
            display: inline-flex;
            background: #e2e8f0;
            padding: 4px;
            border-radius: 999px;
            gap: 4px;
        }
        .toggle-btn {
            border: none;
            background: transparent;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 999px;
            cursor: pointer;
        }
        .toggle-btn.active {
            background: #0f172a;
            color: #fff;
        }
        .btn-print {
            background: #0058be;
            color: #fff;
            padding: 10px 22px;
            border: none;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .waybill-card {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #000000;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .waybill-card.label-4x6 {
            width: 4in;
            max-width: 100%;
            padding: 0.18in;
            font-size: 11px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .logo-title {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: #000;
        }
        .label-4x6 .logo-title {
            font-size: 15px;
        }
        .subtitle {
            font-size: 11px;
            color: #333;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            border: 2px solid #000;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            background: #fff;
        }
        .route-strip {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 8px;
            border: 1.5px solid #000;
            padding: 8px 12px;
            margin-bottom: 12px;
            background: #fff;
        }
        .route-col-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #444;
        }
        .route-col-val {
            font-size: 13px;
            font-weight: 900;
            color: #000;
        }
        .route-col-sub {
            font-size: 10px;
            color: #333;
        }
        .route-arrow {
            font-size: 18px;
            font-weight: 900;
            padding: 0 6px;
        }
        .barcode-section {
            text-align: center;
            padding: 12px 18px;
            background: #ffffff;
            border: 2px solid #000;
            margin-bottom: 12px;
        }
        .barcode-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            background: #ffffff;
            padding: 10px 22px;
            margin: 6px auto;
        }
        .barcode-wrap svg {
            max-width: 100%;
            height: auto;
            display: block;
            shape-rendering: crispEdges;
        }
        .tracking-text {
            font-family: 'Courier New', Courier, monospace;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #000;
            margin-top: 4px;
        }
        .label-4x6 .tracking-text {
            font-size: 15px;
            letter-spacing: 1px;
        }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #666;
            color: #111;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1.5px solid #000;
            margin-bottom: 12px;
        }
        .box {
            padding: 10px 12px;
        }
        .box:first-child {
            border-right: 1.5px solid #000;
        }
        .box-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 4px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        .info-name {
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 3px;
        }
        .info-text {
            font-size: 11px;
            line-height: 1.35;
            color: #111;
        }
        .cod-banner {
            border: 2px solid #000;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            background: #ffffff;
        }
        .cod-label {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .cod-amount {
            font-size: 20px;
            font-weight: 900;
        }
        .handling-box {
            border: 1.5px solid #000;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 11px;
            background: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 12px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 10px;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 10px;
            color: #333;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .waybill-card {
                box-shadow: none;
                border: 2px solid #000;
                max-width: 100%;
                margin: 0;
            }
            .waybill-card.label-4x6 {
                width: 4in;
                min-height: 5.8in;
                page-break-after: avoid;
            }
        }
    </style>
    <style id="page-size-style"></style>
</head>
<body>

    @php
        $snapshot = is_array($order->address_snapshot) ? $order->address_snapshot : [];
        $sellerName = $order->seller?->sellerProfile?->business_name ?? $order->seller?->full_name ?? 'Nexora Merchant';
        $sellerAddress = $order->seller?->address?->full_address ?? 'Nexora Fulfillment Origin';
        $sellerPhone = $order->seller?->sellerProfile?->contact_phone ?? $order->seller?->contact_number ?? 'N/A';

        $recipientName = $order->address?->recipient_name
            ?? ($snapshot['recipient_name'] ?? null)
            ?? $order->buyer?->full_name
            ?? 'Customer';
        $recipientAddress = $order->address?->full_address
            ?? collect([
                $snapshot['street'] ?? null,
                $snapshot['barangay_name'] ?? null,
                $snapshot['municipality_name'] ?? null,
                $snapshot['province_name'] ?? null,
            ])->filter()->implode(', ');
        $recipientPhone = $order->address?->recipient_phone
            ?? ($snapshot['recipient_phone'] ?? null)
            ?? $order->buyer?->contact_number
            ?? 'N/A';

        $originLocation = $order->originHub
            ? ($order->originHub->code . ' · ' . $order->originHub->name)
            : ($order->seller?->address?->municipality_name ?? 'Merchant Pickup Point');
        $originSub = $order->originHub?->city_name ?? ($order->seller?->address?->province_name ?? 'Origin Dispatch');

        $destinationLocation = $order->destinationHub
            ? ($order->destinationHub->code . ' · ' . $order->destinationHub->name)
            : ($order->address?->municipality_name ?? ($snapshot['municipality_name'] ?? 'Buyer Drop-off'));
        $destinationSub = collect([
            $order->destinationHub?->city_name ?? ($order->address?->province_name ?? ($snapshot['province_name'] ?? null)),
            $order->deliveryZone?->name ? ('Zone: ' . $order->deliveryZone->name) : null,
        ])->filter()->implode(' · ');

        $hasFragile = $order->items->contains(function ($item) {
            $name = strtolower((string) $item->product_name);
            return str_contains($name, 'glass')
                || str_contains($name, 'fragile')
                || str_contains($name, 'ceramic')
                || str_contains($name, 'monitor')
                || str_contains($name, 'screen');
        });
        $handlingNotes = collect([
            $hasFragile ? 'FRAGILE — Handle with care, keep upright.' : null,
            $order->payment_method === 'cod' ? 'COD PARCEL — Collect exact amount before release.' : null,
            ! empty($order->notes) ? ('Note: ' . $order->notes) : null,
        ])->filter()->values();
    @endphp

    <div class="toolbar no-print">
        <div class="layout-toggle" role="group" aria-label="Waybill print layout">
            <button type="button" id="btn-standard" class="toggle-btn active" onclick="setWaybillLayout('standard')">Standard Waybill</button>
            <button type="button" id="btn-4x6" class="toggle-btn" onclick="setWaybillLayout('4x6')">4 × 6 in Label</button>
        </div>
        <button type="button" class="btn-print" onclick="window.print()">Print Shipping Waybill</button>
    </div>

    <div id="waybill-card" class="waybill-card">
        <div class="header">
            <div>
                <div class="logo-title">NEXORA LOGISTICS</div>
                <div class="subtitle">Standard Domestic Express Waybill</div>
            </div>
            <div class="badge">{{ strtoupper(str_replace('_', ' ', $order->status)) }}</div>
        </div>

        <div class="route-strip">
            <div>
                <div class="route-col-label">Origin Hub / Dispatch</div>
                <div class="route-col-val">{{ $originLocation }}</div>
                <div class="route-col-sub">{{ $originSub }}</div>
            </div>
            <div class="route-arrow">→</div>
            <div style="text-align: right;">
                <div class="route-col-label">Destination Hub / Area</div>
                <div class="route-col-val">{{ $destinationLocation }}</div>
                <div class="route-col-sub">{{ $destinationSub ?: 'Standard Delivery' }}</div>
            </div>
        </div>

        <div class="barcode-section" data-tracking-number="{{ $trackingNumber }}">
            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #333;">
                Code 128 Canonical Tracking Barcode
            </div>
            <div class="barcode-wrap" aria-label="Code 128 Barcode for {{ $trackingNumber }}">
                {!! $barcodeSvg !!}
            </div>
            <div class="tracking-text">{{ $trackingNumber }}</div>
            <div class="meta-row">
                <span><strong>Order #:</strong> {{ $order->order_number }}</span>
                <span><strong>Billed Weight:</strong> {{ number_format((float) ($order->shipping_weight ?? 0), 2) }} kg</span>
                <span><strong>Items:</strong> {{ $order->items->sum('quantity') }} pcs</span>
            </div>
        </div>

        <div class="grid-2">
            <div class="box">
                <div class="box-title">Sender (Merchant)</div>
                <div class="info-name">{{ $sellerName }}</div>
                <div class="info-text">
                    {{ $sellerAddress }}<br>
                    Phone: {{ $sellerPhone }}
                </div>
            </div>
            <div class="box">
                <div class="box-title">Recipient (Customer)</div>
                <div class="info-name">{{ $recipientName }}</div>
                <div class="info-text">
                    {{ $recipientAddress ?: 'Address on file' }}<br>
                    Phone: {{ $recipientPhone }}
                </div>
            </div>
        </div>

        <div class="cod-banner">
            <div>
                <div class="cod-label">
                    {{ $order->payment_method === 'cod' ? 'Cash on Delivery (COD)' : 'Prepaid Parcel' }}
                </div>
                <div style="font-size: 11px; color: #333;">
                    {{ $order->payment_method === 'cod' ? 'Courier must collect exact cash upon delivery' : 'Do NOT collect cash — paid online' }}
                </div>
            </div>
            <div class="cod-amount">
                {{ $order->payment_method === 'cod' ? '₱' . number_format((float) $order->total, 2) : '₱0.00' }}
            </div>
        </div>

        @if($handlingNotes->isNotEmpty())
            <div class="handling-box">
                <div style="font-weight: 800; text-transform: uppercase; font-size: 10px; margin-bottom: 3px;">Handling Instructions</div>
                @foreach($handlingNotes as $line)
                    <div>• {{ $line }}</div>
                @endforeach
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th style="width: 110px;">Variant</th>
                    <th style="width: 48px; text-align: center;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td><strong>{{ $item->product_name }}</strong></td>
                        <td>
                            {{ is_array($item->variant)
                                ? collect($item->variant)
                                    ->reject(fn($v, $k) => in_array(strtolower((string) $k), ['image', 'image_url', 'img', 'row_id', 'has_file']) || str_starts_with((string) $v, '/storage/') || str_starts_with((string) $v, 'http://') || str_starts_with((string) $v, 'https://'))
                                    ->map(fn($v, $k) => is_string($k) ? "$k: $v" : $v)
                                    ->implode(', ') ?: '—'
                                : ($item->variant ?: '—') }}
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <span>Printed {{ now()->format('Y-m-d H:i') }} · Nexora Market Logistics</span>
            <span>Receiver Signature: _____________________</span>
        </div>
    </div>

    <script>
        function setWaybillLayout(mode) {
            const card = document.getElementById('waybill-card');
            const btnStd = document.getElementById('btn-standard');
            const btn4x6 = document.getElementById('btn-4x6');
            const pageStyle = document.getElementById('page-size-style');

            if (mode === '4x6') {
                card.classList.add('label-4x6');
                btn4x6.classList.add('active');
                btnStd.classList.remove('active');
                pageStyle.textContent = '@page { size: 4in 6in; margin: 0.1in; }';
            } else {
                card.classList.remove('label-4x6');
                btnStd.classList.add('active');
                btn4x6.classList.remove('active');
                pageStyle.textContent = '@page { size: auto; margin: 12mm; }';
            }
        }
    </script>
</body>
</html>
