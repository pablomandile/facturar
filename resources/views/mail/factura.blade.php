{{-- El texto del email (TextoDelEmail), escapado y con sus saltos de línea. --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px;background:#ffffff;color:#111111;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;">
    <div style="max-width:600px;">
        {!! nl2br(e($texto)) !!}
    </div>
</body>
</html>
