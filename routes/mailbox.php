<?php

use BeyondCode\Mailbox\Facades\Mailbox;
use App\Http\Controllers\ChatController;

Mailbox::from('*', [ChatController::class, 'handleIncomingEmail']); 

// Mailbox::from('*@empresa.com', [ChatController::class, 'handleIncomingEmail']);

// Mailbox::from('info@empresa.com', [ChatController::class, 'handleIncomingEmail']);

// Mailbox::subject('Soporte: *', [SoporteController::class, 'handle']);

// Mailbox::from('soporte@empresa.com')
//     ->subject('Urgente: *')
//     ->to('info@miempresa.com')
//     ->call([UrgentController::class, 'handle']); 