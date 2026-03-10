@extends('pdf.layout')

@section('title', 'Récapitulatif des PFE encadrés')
@section('doc-title', 'Récapitulatif des PFE encadrés')

@section('content')

    @php
        $counts = [
            'pfe' => 0,
            'pfa' => 0,
            'stages' => 0
        ];
        foreach($pfes as $p) {
            $type = strtoupper($p->intitule);
            if (str_contains($type, 'PFE')) $counts['pfe']++;
            elseif (str_contains($type, 'PFA')) $counts['pfa']++;
            else $counts['stages']++;
        }
    @endphp

    {{-- Summary box --}}
    <div class="summary-box">
        <strong>Nombre total d'encadrements :</strong> {{ count($pfes) }}<br>
        <span style="font-size: 9pt; margin-left: 10px;">
            PFE : {{ $counts['pfe'] }} | 
            PFA : {{ $counts['pfa'] }} | 
            Stages : {{ $counts['stages'] }}
        </span>
    </div>

    {{-- Detail table --}}
    <div class="section-title">Détail des encadrements des PFE et stages</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Année universitaire</th>
                <th>Type</th>
                <th>Niveau</th>
                <th>Intitulé</th>
                <th>Établissement</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pfes->groupBy('annee_universitaire') as $year => $yearItems)
                <tr style="background-color: #f3f4f6;">
                    <td colspan="5" style="font-weight: bold; color: #965624;">Année Universitaire : {{ $year }}</td>
                </tr>
                @foreach ($yearItems as $p)
                    @php
                        $parts = explode(' - ', $p->intitule);
                        $type = $parts[0] ?? '—';
                        $intitule = $parts[1] ?? ($p->intitule ?? '—');
                        $etablissement = $parts[2] ?? '—';
                    @endphp
                    <tr>
                        <td>{{ $year }}</td>
                        <td>{{ $type }}</td>
                        <td>{{ $p->niveau ?? '—' }}</td>
                        <td>{{ $intitule }}</td>
                        <td>{{ $etablissement }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="5" class="text-center">Aucun encadrement enregistré</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-block">
        <table class="signature-table">
            <tr>
                <td>
                    <span class="signature-label">Signature du candidat</span>
                    <div class="mt-2">Nom : {{ $profile->nom ?? '—' }}</div>
                    <div>Prénom : {{ $profile->prenom ?? '—' }}</div>
                    <div class="signature-line"></div>
                </td>
                <td class="text-right">
                    @if(isset($signature))
                        {{-- Assuming signature might be handled by layout or extra-styles --}}
                    @endif
                </td>
            </tr>
        </table>
    </div>

@endsection
