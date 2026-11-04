<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Fund list</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        p {
            margin-top: 0;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #888;
            padding: 7px;
            text-align: left;
        }

        th {
            background-color: #eeeeee;
        }
    </style>
</head>
<body>
    <h1>Fund list</h1>

    <p>Sabaragamuwa Province Programme Budget Management System</p>

    <table>
        <thead>
            <tr>
                <th>Fund code</th>
                <th>Fund name</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($funds as $fund)
                <tr>
                    <td>{{ $fund->code }}</td>
                    <td>{{ $fund->name }}</td>
                    <td>{{ $fund->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No funds found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
