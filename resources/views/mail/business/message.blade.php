{{--
    Branded email layout used by every business email (Roadmap 4f).
    Email-safe: tables + inline styles (Gmail / Outlook / Apple Mail), max width 600px, readable in dark mode.
    Variables: $brand (name, logoUrl, color, initials, address, phone), $title, $lines[], $summary[[label,value]],
    $action ([label, url] | null), $outro (string|null), $preheader (string|null).
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5fa;font-family:Inter,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1d2033;">
@if(!empty($preheader))
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5fa;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                {{-- Brand header --}}
                <tr>
                    <td style="background:{{ $brand['color'] }};border-radius:14px 14px 0 0;padding:22px 28px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    @if($brand['logoUrl'])
                                        <img src="{{ $brand['logoUrl'] }}" alt="{{ $brand['name'] }}" height="44" style="display:block;height:44px;max-width:180px;border-radius:8px;background:#ffffff;padding:4px;">
                                    @else
                                        <div style="width:44px;height:44px;line-height:44px;border-radius:12px;background:rgba(255,255,255,0.22);color:#ffffff;font-weight:800;font-size:16px;text-align:center;letter-spacing:0.5px;">{{ $brand['initials'] }}</div>
                                    @endif
                                </td>
                                <td style="vertical-align:middle;padding-left:14px;color:#ffffff;font-size:18px;font-weight:800;">{{ $brand['name'] }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                {{-- Card --}}
                <tr>
                    <td style="background:#ffffff;padding:28px;border-radius:0 0 14px 14px;box-shadow:0 8px 24px rgba(31,22,92,0.08);">
                        <h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;color:#1d2033;">{{ $title }}</h1>
                        @foreach($lines as $line)
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#3b3f57;">{{ $line }}</p>
                        @endforeach

                        @if(!empty($summary))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;border:1px solid #e6e8f0;border-radius:10px;">
                                @foreach($summary as [$label, $value])
                                    <tr>
                                        <td style="padding:10px 14px;font-size:14px;color:#6b7086;border-bottom:1px solid #eef0f6;">{{ $label }}</td>
                                        <td align="right" style="padding:10px 14px;font-size:14px;font-weight:700;color:#1d2033;border-bottom:1px solid #eef0f6;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if(!empty($action))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 8px;">
                                <tr>
                                    <td style="border-radius:10px;background:{{ $brand['color'] }};">
                                        <a href="{{ $action[1] }}" style="display:inline-block;padding:12px 24px;color:#ffffff;font-weight:700;font-size:15px;text-decoration:none;">{{ $action[0] }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if(!empty($outro))
                            <p style="margin:18px 0 0;font-size:14px;line-height:1.6;color:#6b7086;">{{ $outro }}</p>
                        @endif
                    </td>
                </tr>
                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:18px 12px;font-size:12px;line-height:1.6;color:#8a8fa8;">
                        <strong style="color:#6b7086;">{{ $brand['name'] }}</strong>
                        @if($brand['address'])<br>{{ $brand['address'] }}@endif
                        @if($brand['phone'])<br>{{ $brand['phone'] }}@endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
