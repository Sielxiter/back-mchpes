@extends('pdf.layout')

@section('title', 'Récapitulatif des enseignements')
@section('doc-title', 'Récapitulatif des enseignements')

@section('extra-styles')
<style>
    .data-table thead th {
        background-color: #f1f5f9; /* light blue/grey */
        color: #334155;
        border-bottom: 2px solid #e2e8f0;
    }
    .year-summary-row td {
        background-color: #f8fafc;
        font-weight: bold;
        color: #0f172a;
        border-top: 1px solid #e2e8f0;
    }
    .total-label {
        color: #965624;
        font-weight: bold;
    }
</style>
@endsection

@section('content')

    {{-- Summary box --}}
    <div class="summary-box">
        <strong>Total global :</strong>
        {{ count($enseignements) }} enseignement(s) —
        {{ $totals['volume_horaire'] ?? 0 }} heures —
        {{ number_format($totals['equivalent_tp'] ?? 0, 2) }} Eq.TP
    </div>

    {{-- Detail table --}}
    <div class="section-title">Enseignements déclarés</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Année</th>
                <th>Intitulé du module</th>
                <th>Type</th>
                <th>Elt.M</th>
                <th>Niveau</th>
                <th class="text-right">Vol.h</th>
                <th class="text-right">Eq.TP</th>
            </tr>
        </thead>
        <tbody>
            @php $globalIndex = 1; @endphp
            @forelse ($by_year as $year => $yearData)
                @foreach ($yearData['items'] as $index => $e)
                    <tr>
                        <td>{{ $index === 0 ? $year : '' }}</td>
                        <td>{{ $e->intitule ?? '—' }}</td>
                        <td>{{ $e->type_enseignement ?? '—' }}</td>
                        <td>{{ $e->type_module === 'Element de module' ? 'Elt.M' : $e->type_module }}</td>
                        <td>{{ $e->niveau ?? '—' }}</td>
                        <td class="text-right">{{ $e->volume_horaire ?? 0 }}h</td>
                        <td class="text-right">{{ number_format($e->equivalent_tp ?? 0, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="year-summary-row">
                    <td colspan="6" class="text-right">Total Eq.TP pour {{ $year }} :</td>
                    <td class="text-right">{{ number_format($yearData['equivalent_tp'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Aucun enseignement enregistré</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($enseignements) > 0)
        <tfoot>
            <tr>
                <td colspan="5" class="text-right"><strong>TOTAUX GLOBAUX</strong></td>
                <td class="text-right">{{ $totals['volume_horaire'] ?? 0 }}h</td>
                <td class="text-right">{{ number_format($totals['equivalent_tp'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Signature Section --}}
    <div class="signature-block">
        <table class="signature-table">
            <tr>
                <td>
                    {{-- Left side: empty or for administration --}}
                </td>
                <td>
                    <span class="signature-label">Signature du candidat</span>
                    <div style="margin-top: -40px; margin-bottom: 20px;">
                        <strong>Nom :</strong> {{ $profile->nom ?? '—' }}<br>
                        <strong>Prénom :</strong> {{ $profile->prenom ?? '—' }}
                    </div>
                    @if($signature)
                        <div style="margin-top: 10px; margin-bottom: 10px;">
                            <img src="{{ $signature }}" style="max-width: 200px; max-height: 80px;">
                        </div>
                    @else
                        <div class="signature-line"></div>
                    @endif
                    <div style="font-size: 8pt; color: #777; margin-top: 5px;">
                        Signé électroniquement le {{ now()->format('d/m/Y à H:i') }} (GMT)<br>
                        Hachage (SHA-256) : {{ hash('sha256', ($profile->nom ?? '') . ($profile->prenom ?? '') . ($reference ?? '')) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Security Footer --}}
    <div style="position: absolute; bottom: 0; right: 0; text-align: right;">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode(url('/verify/' . ($reference ?? ''))) }}" style="width: 80px; height: 80px;">
        <div style="font-size: 7pt; color: #999; margin-top: 2px;">Vérification officielle</div>
    </div>

@endsection
