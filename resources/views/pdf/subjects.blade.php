<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>Subject list</title>

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
    <h1>Subject list</h1>

    <table>
        <thead>
            <tr>
                <th>Office code</th>
                <th>Office name</th>
                <th>Subject code</th>
                <th>Subject name</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject->office->code }}</td>
                    <td>{{ $subject->office->name }}</td>
                    <td>{{ $subject->code }}</td>
                    <td>{{ $subject->name }}</td>
                    <td>{{ $subject->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No subjects found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>