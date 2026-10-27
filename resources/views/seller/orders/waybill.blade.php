<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waybill — {{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
            color: #111;
            background: #f7f7f7;
        }
        .waybill-card {
            max-width: 650px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #000;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-title {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        .badge {
            display: inline-block;
            border: 1.5px solid #000;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .barcode-section {
            text-align: center;
            padding: 12px;
            background: #fafafa;
            border: 1px dashed #444;
            margin-bottom: 16px;
        }
        .barcode-font {
            font-family: 'Courier New', Courier, monospace;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 4px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #000;
            margin-bottom: 16px;
        }
        .box {
            padding: 12px;
        }
        .box:first-child {
            border-right: 1px solid #000;
        }
        .box-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 6px;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }
        .info-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .info-text {
            font-size: 12px;
            line-height: 1.4;
            color: #222;
        }
        .cod-banner {
            border: 2px solid #000;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            background: {{ $order->payment_method === 'cod' ? '#fff9e6' : '#e6f7ed' }};
        }
        .cod-label {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .cod-amount {
            font-size: 20px;
            font-weight: 900;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 16px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #0058be;
            color: #fff;
            padding: 10px 24px;
            border: none;
            border-radius: 999px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .waybill-card {
                box-shadow: none;
                border: 2px solid #000;
                max-width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Shipping Waybill</button>
    </div>

    <div class="waybill-card">
        <div class="header">
            <div>
                <span class="logo-title">NEXORA LOGISTICS</span>
                <div style="font-size: 11px; color: #444; margin-top: 2px;">Standard Domestic Express Delivery</div>
            </div>
            <div class="badge">
                {{ strtoupper($order->status) }}
            </div>
        </div>

        <div class="barcode-section">
            <div style="font-size: 10px; text-transform: uppercase; color: #666; margin-bottom: 4px;">Tracking Number / Barcode</div>
            <div class="barcode-font">||| {{ $order->order_number }} |||</div>
            <div style="font-size: 11px; margin-top: 4px; color: #444;">Billed Weight: {{ number_format($order->shipping_weight, 2) }} kg</div>
        </div>

        <div class="grid-2">
            <div class="box">
                <div class="box-title">Sender (Merchant)</div>
                <div class="info-name">{{ $order->seller->sellerProfile->business_name ?? $order->seller->full_name }}</div>
                <div class="info-text">
                    {{ $order->seller->address->full_address ?? 'Nexora Fulfillment Origin Hub' }}<br>
                    Phone: {{ $order->seller->sellerProfile->contact_phone ?? $order->seller->contact_number }}
                </div>
            </div>
            <div class="box">
                <div class="box-title">Recipient (Customer)</div>
                <div class="info-name">{{ $order->address->recipient_name ?? $order->buyer->full_name }}</div>
                <div class="info-text">
                    {{ $order->address->full_address }}<br>
                    Phone: {{ $order->address->recipient_phone ?? $order->buyer->contact_number }}
                </div>
            </div>
        </div>

        <div class="cod-banner">
            <div>
                <div class="cod-label">
                    {{ $order->payment_method === 'cod' ? 'Cash on Delivery (COD)' : 'Prepaid Parcel' }}
                </div>
                <div style="font-size: 11px; color: #555;">
                    {{ $order->payment_method === 'cod' ? 'Courier must collect cash upon delivery' : 'Do NOT collect cash — paid online' }}
                </div>
            </div>
            <div class="cod-amount">
                {{ $order->payment_method === 'cod' ? '₱' . number_format($order->total, 2) : '₱0.00' }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th style="width: 80px;">Variant</th>
                    <th style="width: 50px; text-align: center;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td><strong>{{ $item->product_name }}</strong></td>
                        <td>{{ is_array($item->variant) ? implode(', ', $item->variant) : ($item->variant ?: '—') }}</td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <span>Printed on {{ now()->format('Y-m-d H:i') }} · Nexora Market System</span>
            <span>Customer Signature: _______________________</span>
        </div>
    </div>

</body>
</html>
