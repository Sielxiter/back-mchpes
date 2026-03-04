@extends('pdf.layout')

@section('title', 'Formulaire de demande de candidature')
@section('doc-title', 'Formulaire de demande de candidature')

@section('content')

    {{-- Section: Identité --}}
    <div class="section-title">Identité</div>
    <table class="kv-grid">
        <tr>
            <td class="kv-label">Nom</td>
            <td class="kv-value">{{ $profile->nom ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Prénom</td>
            <td class="kv-value">{{ $profile->prenom ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Date de naissance</td>
            <td class="kv-value">{{ $profile->date_naissance ? \Carbon\Carbon::parse($profile->date_naissance)->format('d/m/Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Email</td>
            <td class="kv-value">{{ $profile->email ?? ($user->email ?? '—') }}</td>
        </tr>
        <tr>
            <td class="kv-label">Spécialité</td>
            <td class="kv-value">{{ $profile->specialite ?? '—' }}</td>
        </tr>
    </table>

    {{-- Section: Affectation --}}
    <div class="section-title">Affectation</div>
    <table class="kv-grid">
        <tr>
            <td class="kv-label">Établissement</td>
            <td class="kv-value">{{ $profile->etablissement ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Ville</td>
            <td class="kv-value">{{ $profile->ville ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Département</td>
            <td class="kv-value">{{ $profile->departement ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Grade actuel</td>
            <td class="kv-value">{{ $profile->grade_actuel ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Date recrutement (ES)</td>
            <td class="kv-value">{{ $profile->date_recrutement_es ? \Carbon\Carbon::parse($profile->date_recrutement_es)->format('d/m/Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Date recrutement (FP)</td>
            <td class="kv-value">{{ $profile->date_recrutement_fp ? \Carbon\Carbon::parse($profile->date_recrutement_fp)->format('d/m/Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Ancienneté</td>
            <td class="kv-value">
                @if($profile->anciennete)
                    {{ $profile->anciennete['years'] }} an(s) et {{ $profile->anciennete['months'] }} mois
                @else
                    —
                @endif
            </td>
        </tr>
    </table>

    {{-- Section: Coordonnées --}}
    <div class="section-title">Coordonnées</div>
    <table class="kv-grid">
        <tr>
            <td class="kv-label">N° Sommier</td>
            <td class="kv-value">{{ $profile->numero_som ?? '—' }}</td>
        </tr>
        <tr>
            <td class="kv-label">Téléphone</td>
            <td class="kv-value">{{ $profile->telephone ?? '—' }}</td>
        </tr>
    </table>

    {{-- Section: Déclaration --}}
    <div class="section-title">Déclaration</div>
    <p class="paragraph">
        Je soussigné(e) <strong>{{ $profile->prenom ?? '' }} {{ $profile->nom ?? '' }}</strong>,
        certifie sur l'honneur l'exactitude de toutes les informations fournies dans le présent
        dossier de candidature. Je reconnais que toute fausse déclaration peut entraîner
        l'annulation de ma candidature.
    </p>

    {{-- Section: Signatures --}}
    <div class="signature-block">
        <table class="signature-table">
            <tr>
                <td>
                    <span class="signature-label">Signature du candidat</span>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <span class="signature-label">Visa du chef d'établissement</span>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    </div>

@endsection
