<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Forbidden</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f3f4f6;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 6rem;
            margin: 0;
            color: #dc2626;
        }
        h2 {
            font-size: 1.5rem;
            color: #374151;
            margin-bottom: 1rem;
        }
        p {
            color: #6b7280;
            margin-bottom: 2rem;
        }
        a {
            color: #2563eb;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Access Forbidden</h2>
        <p>{{ $message ?? 'You do not have permission to access this resource.' }}</p>
        <a href="{{ url('/dashboard') }}">Return to Dashboard</a>
    </div>
</body>
</html>
