@php
    use App\Billing\Money;
    $taxLabels = ['VAT' => 'ተ.እ.ታ', 'TOT' => 'ተ.ኦ.ታ'];
    $methods = [
        'cash' => ['Cash', 'ጥሬ ገንዘብ'],
        'pos_card' => ['Card (POS)', 'ካርድ'],
        'bank_transfer' => ['Bank transfer', 'የባንክ ዝውውር'],
        'telebirr' => ['Telebirr', 'ቴሌብር'],
        'chapa' => ['Chapa', 'ቻፓ'],
    ];
    $issued = $invoice->issued_at->copy()->setTimezone($timezone);
    $membership = $invoice->memberships->first();
@endphp
<html>
<head>
<style>
    body { font-family: dejavusans; font-size: {{ $thermal ? '7.5pt' : '9pt' }}; color: #161816; }
    .am { font-family: abyssinicasil; }
    .muted { color: #5b5f5a; }
    .brand { font-size: {{ $thermal ? '11pt' : '15pt' }}; font-weight: bold; }
    .band { border-top: 3pt solid #C2412D; margin: 6pt 0 8pt; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 3pt 0; vertical-align: top; }
    th { text-align: left; font-size: {{ $thermal ? '6.5pt' : '7.5pt' }}; color: #5b5f5a; border-bottom: 0.6pt solid #b9bcb6; }
    .num { text-align: right; white-space: nowrap; }
    .items td { border-bottom: 0.4pt solid #dfe1dc; }
    .totals td { padding: 2pt 0; }
    .grand td { font-size: {{ $thermal ? '10pt' : '12pt' }}; font-weight: bold; border-top: 1pt solid #161816; padding-top: 5pt; }
    .stamp { color: #9C3320; border: 1.5pt solid #9C3320; padding: 3pt 8pt; font-weight: bold; font-size: 12pt; }
    .meta td { padding: 1.5pt 0; }
    .foot { margin-top: 12pt; text-align: center; }
</style>
</head>
<body>
<table>
    <tr>
        <td>
            <div class="brand">{{ $invoice->organization->name }}</div>
            <div class="muted">{{ $invoice->branch->name }}@if($invoice->branch->address) · {{ $invoice->branch->address }}@endif</div>
            @if($invoice->branch->phone)<div class="muted">{{ $invoice->branch->phone }}</div>@endif
            @if($invoice->organization->tin_number)<div class="muted">TIN / <span class="am">የግብር ከፋይ መለያ</span>: {{ $invoice->organization->tin_number }}</div>@endif
        </td>
        @unless($thermal)
        <td class="num">
            <div style="font-size: 13pt; font-weight: bold;">Receipt</div>
            <div class="am" style="font-size: 11pt;">ደረሰኝ</div>
        </td>
        @endunless
    </tr>
</table>
<div class="band"></div>

@if($invoice->status === 'void')
    <p><span class="stamp">VOID · <span class="am">ተሰርዟል</span></span></p>
    <p class="muted">{{ $invoice->void_reason }}</p>
@endif

<table class="meta">
    <tr><td class="muted">No. / <span class="am">ቁጥር</span></td><td class="num"><b>{{ $invoice->number }}</b></td></tr>
    <tr><td class="muted">Date / <span class="am">ቀን</span></td><td class="num">{{ $issued->format('d M Y, H:i') }}</td></tr>
    @if($invoice->member)
    <tr><td class="muted">Member / <span class="am">አባል</span></td><td class="num">{{ $invoice->member->full_name }} · #{{ $invoice->member->code }}</td></tr>
    @endif
    @if($membership)
    <tr>
        <td class="muted">Valid / <span class="am">የሚያገለግለው</span></td>
        <td class="num">
            {{ $membership->starts_on->format('d M Y') }}@if($membership->ends_on) – {{ $membership->ends_on->format('d M Y') }}@endif
            @if($membership->visits_total) · {{ $membership->visits_total }} visits / <span class="am">ጉብኝት</span>@endif
        </td>
    </tr>
    @endif
</table>

<table class="items" style="margin-top: 8pt;">
    <tr>
        <th>Description / <span class="am">መግለጫ</span></th>
        <th class="num">Amount / <span class="am">መጠን</span></th>
    </tr>
    @foreach($invoice->items as $item)
    <tr>
        <td>
            {{ $item->translate('description', 'en') }}
            @if(($item->description['am'] ?? null) && $item->description['am'] !== $item->description['en'])<br><span class="am muted">{{ $item->description['am'] }}</span>@endif
            @if($item->qty > 1)<span class="muted"> × {{ $item->qty }}</span>@endif
        </td>
        <td class="num">{{ Money::format($item->unit_price_cents * $item->qty) }}</td>
    </tr>
    @if($item->discount_cents > 0)
    <tr>
        <td class="muted">Discount / <span class="am">ቅናሽ</span>@if($item->discountRule) · {{ $item->discountRule->translate('name', 'en') }}@if($item->discountRule->name['am'] ?? null) / <span class="am">{{ $item->discountRule->name['am'] }}</span>@endif @endif</td>
        <td class="num muted">−{{ Money::format($item->discount_cents) }}</td>
    </tr>
    @endif
    @endforeach
</table>

<table class="totals" style="margin-top: 6pt;">
    @if($invoice->tax_name)
    <tr>
        <td class="muted">{{ $invoice->tax_inclusive ? 'Price before' : 'Subtotal before' }} {{ $invoice->tax_name }} / <span class="am">ከ{{ $taxLabels[$invoice->tax_name] ?? '' }} በፊት</span></td>
        <td class="num">{{ Money::format($invoice->netCents()) }}</td>
    </tr>
    <tr>
        <td class="muted">{{ $invoice->tax_name }} {{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') }}% / <span class="am">{{ $taxLabels[$invoice->tax_name] ?? '' }}</span></td>
        <td class="num">{{ Money::format($invoice->tax_cents) }}</td>
    </tr>
    @endif
    <tr class="grand">
        <td>Total / <span class="am">ጠቅላላ</span></td>
        <td class="num">{{ $invoice->currency }} {{ Money::format($invoice->total_cents) }}</td>
    </tr>
    @foreach($invoice->payments->where('status', 'completed') as $payment)
    <tr>
        <td class="muted">{{ $methods[$payment->method][0] }} / <span class="am">{{ $methods[$payment->method][1] }}</span>@if($payment->reference) · {{ $payment->reference }}@endif</td>
        <td class="num">{{ Money::format($payment->amount_cents) }}</td>
    </tr>
    @endforeach
    @if($invoice->balanceCents() > 0)
    <tr>
        <td><b>Balance due / <span class="am">ቀሪ ሂሳብ</span></b></td>
        <td class="num"><b>{{ Money::format($invoice->balanceCents()) }}</b></td>
    </tr>
    @endif
</table>

<div class="foot muted">
    @if($invoice->creator)Served by / <span class="am">ያስተናገደው</span>: {{ $invoice->creator->name }}<br>@endif
    Thank you · <span class="am">እናመሰግናለን</span>
</div>
</body>
</html>
