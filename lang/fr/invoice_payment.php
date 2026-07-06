<?php

return [
    'register_title' => 'Registrar pago',
    'amount' => 'Importe',
    'date' => 'Fecha',
    'account' => 'Cuenta',
    'type' => 'Forma de pago',
    'remarks' => 'Observaciones',
    'submit' => 'Registrar pago',
    'no_accounts' => 'No hay cuentas activas en :currency para registrar este pago.',
    'success' => 'El pago se registró correctamente.',
    'errors' => [
        'not_allowed' => 'No puedes registrar pagos para esta factura.',
        'amount_invalid' => 'El importe debe ser mayor que cero.',
        'amount_exceeds_balance' => 'El importe no puede superar el saldo pendiente.',
        'account_invalid' => 'La cuenta seleccionada no es válida.',
        'account_currency_mismatch' => 'La cuenta debe estar en la misma moneda que la factura.',
        'type_not_allowed_for_account' => 'La forma de pago seleccionada no está permitida para la cuenta elegida.',
    ],
];
