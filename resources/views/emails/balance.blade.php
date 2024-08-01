<!DOCTYPE html>
<html>
<head>
    <title>Account Balance</title>
</head>
<body>
    <h1>Account Balance</h1>
    <table>
        <thead>
            <tr>
                <th>Account Name</th>
                <th>Balance</th>
                <th>Currency</th>
            </tr>
        </thead>
        <tbody>
            @foreach($balances as $account)
            <tr>
                <td>{{ $account['name'] }}</td>
                <td>{{ $account['balance'] }}</td>
                <td>{{ $account['currency_code'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <footer>
        <p>Visit our application at <a href="https://staging.revisionalpha.com/app/payment/list">https://staging.revisionalpha.com</a></p>
        <p><strong>Demo User:</strong> admin@staging.revisionalpha.com</p>
        <p><strong>Password:</strong> Simplicity!</p>
    </footer>
</body>
</html>
