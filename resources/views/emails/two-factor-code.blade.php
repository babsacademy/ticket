<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre code de connexion</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; color:#000000; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="width:480px; max-width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden;">

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
                        <td style="padding:32px 28px; text-align:center;">
                            <p style="margin:0 0 6px; font-size:16px; font-weight:bold; color:#000000;">
                                Votre code de connexion
                            </p>
                            <p style="margin:0 0 24px; font-size:13px; color:#52525b;">
                                Saisissez ce code pour terminer votre connexion à l'espace administrateur.
                            </p>

                            <div style="display:inline-block; padding:16px 32px; background-color:#f4f4f5; border-radius:8px; font-size:36px; font-weight:bold; letter-spacing:10px; color:#000000;">
                                {{ $code }}
                            </div>

                            <p style="margin:24px 0 0; font-size:12px; color:#71717a;">
                                Ce code est valable 10 minutes. Si vous n'êtes pas à l'origine de cette tentative de connexion, ignorez cet e-mail.
                            </p>
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
