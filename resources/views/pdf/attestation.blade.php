@extends('pdf.layout')

@section('title', $title ?? "Attestation des activités")
@section('doc-title', $title ?? "Attestation des activités")

@section('content')

    {{-- Declaration paragraph --}}
    <p class="paragraph mt-2">
        Je soussigné(e), <strong>{{ $profile->prenom ?? '' }} {{ $profile->nom ?? '' }}</strong>,
        {{ $profile->grade_actuel ?? '' }} à
        <strong>{{ $profile->etablissement ?? "l'Université Cadi Ayyad" }}</strong>,
        atteste avoir réalisé les activités suivantes dans le cadre de
        {{ $activity_type === 'enseignement' ? "l'enseignement" : "la recherche" }} :
    </p>

    {{-- Activities table --}}
    <div class="section-title mt-4">{{ $title ?? 'Activités' }}</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Catégorie</th>
                <th>Sous-catégorie</th>
                <th>Nombre</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($activites as $index => $a)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $a->category ?? '—' }}</td>
                    <td>{{ $a->subcategory ?? '—' }}</td>
                    <td class="text-center">{{ $a->count ?? 0 }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">Aucune activité enregistrée</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($activites) > 0)
        <tfoot>
            <tr>
                <td colspan="3" class="text-right"><strong>Total</strong></td>
                <td class="text-center"><strong>{{ collect($activites)->sum('count') }}</strong></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Attestation text --}}
    <p class="paragraph mt-4">
        La présente attestation est délivrée pour servir et valoir ce que de droit.
    </p>

    {{-- Signatures --}}
    <div class="signature-block">
        <table class="signature-table">
            <tr>
                <td>
                    <span class="signature-label">Fait à {{ $profile->ville ?? 'Marrakech' }}, le {{ now()->format('d/m/Y') }}</span>
                    <br><br>
                    <span class="signature-label">Signature du candidat</span>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <span class="signature-label">&nbsp;</span>
                    <br><br>
                    <span class="signature-label">Visa du chef d'établissement</span>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>

@endsection
