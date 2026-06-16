<?php

return [
    'link' => [
        'action_title' => 'Vincular a factura',
        'title' => 'Vincular pago a una factura',
        'subtitle' => 'Elegí la factura a la que corresponde este pago.',
        'back' => 'Volver a pagos',
        'payment_date' => 'Fecha del pago',
        'amount' => 'Importe',
        'enterprise' => 'Cliente',
        'invoice_label' => 'Factura',
        'invoice_placeholder' => 'Selecciona una factura',
        'no_invoices' => 'No hay facturas con saldo pendiente que coincidan. Creá o sincroniza la factura primero, o revisa el cliente del pago.',
        'hint' => 'Solo se listan facturas con saldo pendiente de tu equipo. Al vincular, se asignará el cliente de la factura al pago si aún no tiene uno.',
        'submit' => 'Vincular factura',
        'success' => 'El pago quedó vinculado a la factura.',
        'errors' => [
            'already_linked' => 'Este pago ya está vinculado a una factura.',
            'enterprise_mismatch' => 'La factura es de otro cliente distinto al del pago. Elegí una factura del mismo cliente o ajusta el pago primero.',
            'no_balance' => 'Esta factura no tiene saldo pendiente.',
            'operation_mismatch' => 'Esta factura no corresponde al tipo de pago (cobro o pago).',
        ],
    ],
];
