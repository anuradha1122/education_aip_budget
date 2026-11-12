<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>Officer list</title>

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
    <h1>Officer list</h1>

    <table>
        <thead>
            <tr>
                <th>Officer name</th>
                <th>Office code</th>
                <th>Office name</th>
                <th>Designation code</th>
                <th>Designation name</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($officers as $officer)
                <tr>
                    <td>{{ $officer->name }}</td>
                    <td>{{ $officer->office->code }}</td>
                    <td>{{ $officer->office->name }}</td>
                    <td>{{ $officer->designation->code }}</td>
                    <td>{{ $officer->designation->name }}</td>
                    <td>{{ $officer->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No officers found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>