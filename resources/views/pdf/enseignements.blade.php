@extends('pdf.layout')

@section('title', 'Récapitulatif des enseignements')
@section('doc-title', 'Récapitulatif des enseignements')

@section('content')

    {{-- Summary box --}}
    <div class="summary-box">
        <strong>Total :</strong>
        {{ count($enseignements) }} enseignement(s) —
        {{ $totals['volume_horaire'] ?? 0 }} heures —
        {{ number_format($totals['equivalent_tp'] ?? 0, 2) }} eq. TP
    </div>

    {{-- Detail table --}}
    <div class="section-title">Détail des enseignements</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Année</th>
                <th>Intitulé</th>
                <th>Type</th>
                <th>Module</th>
                <th>Niveau</th>
                <th>Vol. (h)</th>
                <th>Eq. TP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($enseignements as $index => $e)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $e->annee_universitaire ?? '—' }}</td>
                    <td>{{ $e->intitule ?? '—' }}</td>
                    <td>{{ $e->type_enseignement ?? '—' }}</td>
                    <td>{{ $e->type_module ?? '—' }}</td>
                    <td>{{ $e->niveau ?? '—' }}</td>
                    <td class="text-right">{{ $e->volume_horaire ?? 0 }}</td>
                    <td class="text-right">{{ number_format($e->equivalent_tp ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Aucun enseignement enregistré</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($enseignements) > 0)
        <tfoot>
            <tr>
                <td colspan="6" class="text-right"><strong>Totaux</strong></td>
                <td class="text-right">{{ $totals['volume_horaire'] ?? 0 }}</td>
                <td class="text-right">{{ number_format($totals['equivalent_tp'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

@endsection
