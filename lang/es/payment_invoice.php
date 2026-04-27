<?php

return [
    'link' => [
        'action_title' => 'Vincular a factura',
        'title' => 'Vincular pago a una factura',
        'subtitle' => 'Elige la factura a la que corresponde este pago.',
        'back' => 'Volver a pagos',
        'payment_date' => 'Fecha del pago',
        'amount' => 'Importe',
        'enterprise' => 'Cliente',
        'invoice_label' => 'Factura',
        'invoice_placeholder' => 'Selecciona una factura',
        'no_invoices' => 'No hay facturas que coincidan. Crea o sincroniza la factura primero, o revisa el cliente del pago.',
        'hint' => 'Solo se listan facturas de tu equipo. Si el pago tiene cliente, solo aparecen facturas de ese cliente.',
        'submit' => 'Vincular factura',
        'success' => 'El pago quedó vinculado a la factura.',
        'errors' => [
            'already_linked' => 'Este pago ya está vinculado a una factura.',
            'enterprise_mismatch' => 'La factura es de otro cliente distinto al del pago. Elige una factura del mismo cliente o ajusta el pago primero.',
        ],
    ],
];
