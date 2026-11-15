<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Could Not Be Completed (422) — Nexora Market</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            max-width: 32rem;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
        }
        .badge {
            display: inline-block;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.75rem 0;
        }
        p {
            font-size: 0.9375rem;
            line-height: 1.5;
            color: #475569;
            margin: 0 0 1.5rem 0;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.125rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: #0f172a;
            color: #ffffff;
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">422 · Validation / Workflow Rule</span>
        <h1>Action Could Not Be Completed</h1>
        <p>{{ $exception?->getMessage() ?: 'The submitted request could not be processed due to a validation or workflow rule.' }}</p>
        <div class="actions">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-primary">Go Back</a>
            <button type="button" onclick="window.history.back()" class="btn btn-secondary">Previous Page</button>
        </div>
    </div>
</body>
</html>
