@extends('pdf.layout')

@section('title', 'Récapitulatif des PFE encadrés')
@section('doc-title', 'Récapitulatif des PFE encadrés')

@section('content')

    {{-- Summary box --}}
    <div class="summary-box">
        <strong>Total :</strong>
        {{ count($pfes) }} PFE encadré(s) —
        {{ $totals['volume_horaire'] ?? 0 }} heures
    </div>

    {{-- Detail table --}}
    <div class="section-title">Détail des PFE</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Année universitaire</th>
                <th>Intitulé</th>
                <th>Niveau</th>
                <th>Volume horaire (h)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pfes as $index => $p)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $p->annee_universitaire ?? '—' }}</td>
                    <td>{{ $p->intitule ?? '—' }}</td>
                    <td>{{ $p->niveau ?? '—' }}</td>
                    <td class="text-right">{{ $p->volume_horaire ?? 0 }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Aucun PFE enregistré</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($pfes) > 0)
        <tfoot>
            <tr>
                <td colspan="4" class="text-right"><strong>Total heures</strong></td>
                <td class="text-right">{{ $totals['volume_horaire'] ?? 0 }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

@endsection
