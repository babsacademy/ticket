<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vos billets — {{ $event->title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; color:#000000; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px; max-width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#f97316; padding:20px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
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

                    {{-- Body --}}
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 8px; font-size:18px; font-weight:bold; color:#000000;">
                                Merci{{ $order->buyer_name ? ', '.$order->buyer_name : '' }} !
                            </p>
                            <p style="margin:0 0 24px; font-size:13px; color:#52525b;">
                                Votre commande pour <strong>{{ $event->title }}</strong> est confirmée.
                                Vos billets sont prêts à être téléchargés.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; color:#71717a;">Événement</td>
                                    <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; font-weight:bold; text-align:right; color:#000000;">{{ $event->title }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; color:#71717a;">Date</td>
                                    <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; font-weight:bold; text-align:right; color:#000000;">{{ $event->date->locale('fr')->translatedFormat('d F Y \à H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; color:#71717a;">Lieu</td>
                                    <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; font-weight:bold; text-align:right; color:#000000;">{{ $event->venue }}{{ $event->city ? ', '.$event->city : '' }}</td>
                                </tr>
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; color:#71717a;">{{ $item->quantity }} × {{ $item->ticketType->name }}</td>
                                        <td style="padding:12px 0; border-bottom:1px solid #e4e4e7; font-size:13px; font-weight:bold; text-align:right; color:#000000;">{{ number_format($item->quantity * $item->unit_price, 0, ',', ' ') }} FCFA</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding:12px 0; font-size:14px; font-weight:bold; color:#000000;">Total</td>
                                    <td style="padding:12px 0; font-size:14px; font-weight:bold; text-align:right; color:#000000;">{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $ticketPdfUrl }}" style="display:inline-block; background-color:#f97316; color:#ffffff; font-size:14px; font-weight:bold; text-decoration:none; padding:12px 28px; border-radius:6px;">
                                            Télécharger mes billets
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#f4f4f5; padding:14px 28px; text-align:center; font-size:11px; color:#71717a;">
                            Billetterie sécurisée — {{ $platformName }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
