<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 40px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 18px;
        }

        p {
            margin: 0 0 16px;
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
            vertical-align: top;
        }

        th {
            background-color: #eeeeee;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <h1>Office list</h1>

    <p>Active and inactive offices</p>

    <table>
        <thead>
            <tr>
                <th>Office code</th>
                <th>Office name</th>
                <th>Office type</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($offices as $office)
                <tr>
                    <td>{{ $office->code }}</td>
                    <td>{{ $office->name }}</td>
                    <td>{{ ucfirst($office->type) }}</td>
                    <td>{{ $office->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No offices found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
