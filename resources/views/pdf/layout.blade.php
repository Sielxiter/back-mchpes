<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title', 'Document — Université Cadi Ayyad')</title>
    <style>
        /* === Reset & Base === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #191919;
            line-height: 1.5;
        }

        /* === Page layout === */
        @page {
            margin: 120px 50px 80px 50px;
        }

        /* === Header (repeated on each page) === */
        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 90px;
            border-bottom: 3px solid #965624;
            padding-bottom: 8px;
        }
        .header-table {
            width: 100%;
            border: none;
        }
        .header-table td {
            vertical-align: middle;
            border: none;
        }
        .header-logo {
            width: 70px;
        }
        .header-logo img {
            width: 60px;
            height: auto;
        }
        .header-center {
            text-align: center;
        }
        .header-center .univ-name {
            font-size: 14pt;
            font-weight: bold;
            color: #965624;
        }
        .header-center .doc-title {
            font-size: 10pt;
            color: #555;
            margin-top: 2px;
        }
        .header-right {
            text-align: right;
            font-size: 8pt;
            color: #777;
            width: 140px;
        }

        /* === Footer (repeated on each page) === */
        footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 50px;
            border-top: 2px solid #965624;
            padding-top: 6px;
        }
        .footer-table {
            width: 100%;
            border: none;
            font-size: 8pt;
            color: #777;
        }
        .footer-table td {
            border: none;
            vertical-align: middle;
        }
        .footer-left { text-align: left; }
        .footer-center { text-align: center; }
        .footer-right { text-align: right; }
        .page-number:after { content: counter(page); }
        .page-total:after { content: counter(pages); }

        /* === Content styles === */
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #965624;
            margin-top: 18px;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid #965624;
        }

        .kv-grid {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .kv-grid td {
            padding: 5px 8px;
            border: none;
            vertical-align: top;
        }
        .kv-label {
            font-weight: bold;
            color: #555;
            width: 40%;
        }
        .kv-value {
            color: #191919;
        }
        .kv-grid tr:nth-child(even) {
            background-color: #f8f6f3;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9pt;
        }
        .data-table thead th {
            background-color: #965624;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
        }
        .data-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e5e5;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f8f6f3;
        }
        .data-table tfoot td {
            padding: 6px 8px;
            font-weight: bold;
            border-top: 2px solid #965624;
            background-color: #fdf8f3;
        }

        .summary-box {
            background-color: #f8f6f3;
            border: 1px solid #e5ddd4;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .summary-box strong {
            color: #965624;
        }

        .paragraph {
            margin-bottom: 10px;
            text-align: justify;
        }

        .signature-block {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            border: none;
        }
        .signature-table td {
            border: none;
            width: 50%;
            vertical-align: top;
            padding: 8px;
        }
        .signature-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 50px;
            display: block;
        }
        .signature-line {
            border-top: 1px solid #ccc;
            width: 80%;
            margin-top: 50px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
    </style>
    @yield('extra-styles')
</head>
<body>

    {{-- HEADER --}}
    <header>
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    {{-- UCA Logo placeholder (use public path or base64-encoded) --}}
                    <div style="width:55px;height:55px;border:2px solid #965624;border-radius:50%;text-align:center;line-height:55px;font-weight:bold;color:#965624;font-size:16pt;">UCA</div>
                </td>
                <td class="header-center">
                    <div class="univ-name">Université Cadi Ayyad</div>
                    <div class="doc-title">@yield('doc-title', 'Dossier de candidature')</div>
                </td>
                <td class="header-right">
                    <div>Date : {{ $generated_date ?? now()->format('d/m/Y') }}</div>
                    <div>Réf : UCA/{{ $reference ?? 'CAND-' . ($candidature_id ?? '000') }}</div>
                </td>
            </tr>
        </table>
    </header>

    {{-- FOOTER --}}
    <footer>
        <table class="footer-table">
            <tr>
                <td class="footer-left">Université Cadi Ayyad — Dossier de candidature</td>
                <td class="footer-center">Confidentiel</td>
                <td class="footer-right">Page <span class="page-number"></span> / <span class="page-total"></span></td>
            </tr>
        </table>
    </footer>

    {{-- BODY --}}
    <main>
        @yield('content')
    </main>

</body>
</html>
