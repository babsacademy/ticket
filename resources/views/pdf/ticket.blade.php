<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Billet — {{ $event->title }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20px;
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#ffffff; color:#000000; font-family: 'DejaVu Sans', sans-serif;">
@foreach ($tickets as $ticket)
    <div style="width:100%; box-sizing:border-box;{{ $loop->last ? '' : ' page-break-after: always;' }}">

        {{-- Header --}}
        <table style="width:100%; border-collapse:collapse; background-color:#f97316;">
            <tr>
                <td style="padding:16px 20px;">
                    <table style="border-collapse:collapse;">
                        <tr>
                            <td style="width:34px; vertical-align:middle;">
                                <div style="width:30px; height:30px; background-color:#ffffff; border-radius:6px; text-align:center; line-height:30px; font-size:13px; font-weight:bold; color:#f97316;">ST</div>
                            </td>
                            <td style="vertical-align:middle; padding-left:10px;">
                                <div style="color:#ffffff; font-size:17px; font-weight:bold;">ScanTicket</div>
                                <div style="color:#ffffff; font-size:10px;">{{ $platformName }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Body --}}
        <div style="width:100%; box-sizing:border-box; padding:28px 20px; text-align:center;">
            <div style="width:460px; max-width:100%; margin:0 auto;">
                @if ($ticket['qr_src'])
                    <img src="{{ $ticket['qr_src'] }}" alt="QR code du billet" style="display:block; width:180px; height:180px; margin:0 auto 22px;">
                @endif

                <div style="font-size:22px; font-weight:bold; color:#000000; margin-bottom:6px;">
                    {{ $event->title }}
                </div>
                <div style="font-size:12px; color:#52525b; margin-bottom:22px;">
                    {{ $event->date->locale('fr')->translatedFormat('d F Y \à H:i') }} — {{ $event->venue }}{{ $event->city ? ', '.$event->city : '' }}
                </div>

                {{-- Dashed "ticket tear" separator --}}
                <table style="width:100%; border-collapse:collapse; margin-bottom:22px;">
                    <tr>
                        <td style="width:14px; height:14px;">
                            <div style="width:14px; height:14px; border-radius:50%; background-color:#ffffff; border:1px solid #e4e4e7;"></div>
                        </td>
                        <td style="border-top:2px dashed #d4d4d8;"></td>
                        <td style="width:14px; height:14px;">
                            <div style="width:14px; height:14px; border-radius:50%; background-color:#ffffff; border:1px solid #e4e4e7;"></div>
                        </td>
                    </tr>
                </table>

                {{-- Info table: fixed 40%/60% columns, wraps long values instead of overflowing --}}
                <table style="width:100%; table-layout:fixed; border-collapse:collapse; text-align:left;">
                    <tr>
                        <td style="width:40%; padding:9px 8px 9px 0; font-size:12px; color:#71717a; vertical-align:top; border-bottom:1px solid #e4e4e7; word-wrap:break-word; overflow-wrap:break-word;">
                            Titulaire
                        </td>
                        <td style="width:60%; padding:9px 0 9px 8px; font-size:13px; font-weight:bold; color:#000000; text-align:right; vertical-align:top; border-bottom:1px solid #e4e4e7; word-wrap:break-word; overflow-wrap:break-word; word-break:break-word;">
                            {{ $order->buyer_name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="width:40%; padding:9px 8px 9px 0; font-size:12px; color:#71717a; vertical-align:top; border-bottom:1px solid #e4e4e7; word-wrap:break-word; overflow-wrap:break-word;">
                            Type de billet
                        </td>
                        <td style="width:60%; padding:9px 0 9px 8px; font-size:13px; font-weight:bold; color:#000000; text-align:right; vertical-align:top; border-bottom:1px solid #e4e4e7; word-wrap:break-word; overflow-wrap:break-word; word-break:break-word;">
                            {{ $ticket['type_name'] }}
                        </td>
                    </tr>
                </table>

                <div style="margin-top:22px; font-size:12px; letter-spacing:1px; color:#71717a;">
                    Billet {{ $ticket['number'] }}
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <table style="width:100%; border-collapse:collapse; background-color:#f4f4f5;">
            <tr>
                <td style="padding:12px 20px; text-align:center; font-size:10px; color:#71717a;">
                    Billetterie sécurisée — {{ $platformName }}
                </td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
