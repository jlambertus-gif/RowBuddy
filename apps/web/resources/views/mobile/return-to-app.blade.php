<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ $returnUrl }}">
    <title>{{ $title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #0b0e14; color: #fff; }
        main { text-align: center; padding: 24px; }
        a { display: inline-block; margin-top: 16px; padding: 12px 24px; background: #2563eb; color: #fff; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ __('mobile.return_to_app.body') }}</p>
        <a href="{{ $returnUrl }}">{{ __('mobile.return_to_app.action_label') }}</a>
    </main>
</body>
</html>
