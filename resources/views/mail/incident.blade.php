<!doctype html>
<html lang="{{ $locale }}">
<body style="margin:0;padding:24px;background:#f5f5f4;font-family:-apple-system,'Segoe UI',Roboto,'Noto Sans Thai',sans-serif;color:#1c1917;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e7e5e4;border-radius:8px;">
    <tr>
        <td style="padding:20px 24px;border-left:4px solid {{ $tone }};">
            <div style="font-size:12px;color:#78716c;text-transform:uppercase;letter-spacing:.04em;">{{ $kind }}</div>
            <div style="font-size:16px;font-weight:600;margin-top:4px;">{{ $subject }}</div>
            @foreach ($lines as $line)
                <div style="font-size:14px;margin-top:8px;">{{ $line }}</div>
            @endforeach
            @if ($url)
                <a href="{{ $url }}" style="display:inline-block;margin-top:16px;padding:8px 14px;background:#1c1917;color:#ffffff;border-radius:6px;font-size:13px;text-decoration:none;">{{ $openLabel }}</a>
            @endif
        </td>
    </tr>
</table>
<div style="max-width:560px;margin:8px auto 0;font-size:11px;color:#a8a29e;">{{ config('app.name') }} · {{ app()->environment() }}</div>
</body>
</html>
