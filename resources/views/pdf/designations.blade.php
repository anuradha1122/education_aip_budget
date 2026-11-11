<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>Designation list</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #777;
            padding: 7px;
            text-align: left;
        }

        th {
            background-color: #eeeeee;
        }
    </style>
</head>
<body>
    <h1>Designation list</h1>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($designations as $designation)
                <tr>
                    <td>{{ $designation->code }}</td>
                    <td>{{ $designation->name }}</td>
                    <td>{{ $designation->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No designations found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>