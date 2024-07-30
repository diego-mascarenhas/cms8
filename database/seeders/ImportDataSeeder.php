<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Hash;
use Log;

class ImportDataSeeder extends Seeder
{
    public function run()
    {
        // Users
        echo "Starting migration of Users...\n";
        $users = DB::connection('mysql_tmp')->table('contactos')
            ->whereNotNull('email')
            ->where('grupo', env('CMS_GROUP'))
            ->whereNotNull('id_empresa')
            ->where('area_privada', '!=', 6)
            ->where('id', '>', 2)
            //->limit(5)
            ->get();

        foreach ($users as $data)
        {
            $existingUser = DB::table('users')->where('email', $data->email)->first();

            $phone = $data->celular ?? $data->telefono ?? null;
            $cleaned_phone = $phone ? preg_replace('/\D/', '', $phone) : null;
            if (!empty($cleaned_phone) && strpos($cleaned_phone, '54') !== 0)
                $cleaned_phone = '54' . $cleaned_phone;
            if (empty($cleaned_phone))
                $cleaned_phone = null;

            $userData = [
                'id' => $data->id,
                'name' => $data->nombre . ' ' . $data->apellido,
                'phone' => $cleaned_phone,
                'email' => $data->email,
                'password' => Hash::make($cleaned_phone),
                'email_verified_at' => $data->ultima_visita,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingUser)
            {
                DB::table('users')->insert($userData);
            }
        }

        // Categories
        echo "Starting migration of Categories...\n";
        $categories = DB::connection('mysql_tmp')->table('categorias_generales')
            ->where('grupo', env('CMS_GROUP'))
            ->orderBy('padre', 'asc')
            ->get();

        foreach ($categories as $data)
        {
            $existingCategory = DB::table('categories')->where('id', $data->id)->first();

            $cleaned_description = strip_tags($data->descripcion);

            $dataArray = [
                'currency_id' => $data->id_moneda,
                'price' => $data->valor,
                'discount' => $data->descuento,
                'frequency' => $data->frecuencia,
            ];

            $categoryData = [
                'id' => $data->id,
                'name' => $data->categoria,
                'description' => $cleaned_description,
                'data' => json_encode($dataArray),
                'parent_id' => $data->padre,
                'order' => $data->orden,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingCategory)
            {
                DB::table('categories')->insert($categoryData);
            }
            else
            {
                DB::table('categories')->where('id', $existingCategory->id)->update($categoryData);
            }
        }

        // Payment Types
        echo "Starting migration of Payment Types...\n";
        $paymentTypes = DB::connection('mysql_tmp')->table('formas_pago')
            ->where('grupo', env('CMS_GROUP'))
            ->get();

        foreach ($paymentTypes as $data)
        {
            $existingPaymentType = DB::table('payment_types')->where('id', $data->id)->first();

            $paymentTypesData = [
                'id' => $data->id,
                'name' => $data->forma_pago,
                'discount' => $data->descuento,
                'status' => $data->estado,
            ];

            if (!$existingPaymentType)
            {
                DB::table('payment_types')->insert($paymentTypesData);
            }
            else
            {
                DB::table('payment_types')->where('id', $existingPaymentType->id)->update($paymentTypesData);
            }
        }

        // Invoice Types
        echo "Starting migration of Invoice Types...\n";
        $invoiceTypes = DB::connection('mysql_tmp')->table('facturas_tipo')
            ->where('grupo', env('CMS_GROUP'))
            ->get();

        foreach ($invoiceTypes as $data)
        {
            $existingInvoiceType = DB::table('invoice_types')->where('id', $data->id)->first();

            $invoiceTypesData = [
                'id' => $data->id,
                'name' => $data->factura_tipo,
            ];

            if (!$existingInvoiceType)
            {
                DB::table('invoice_types')->insert($invoiceTypesData);
            }
            else
            {
                DB::table('invoice_types')->where('id', $existingInvoiceType->id)->update($invoiceTypesData);
            }
        }

        // Enterprises
        echo "Starting migration of Enterprises...\n";
        $enterprises = DB::connection('mysql_tmp')->table('empresas')
            ->where('grupo', env('CMS_GROUP'))
            ->get();

        foreach ($enterprises as $data)
        {
            $existingEnterprise = DB::table('enterprises')->where('id', $data->id)->first();


            if ($data->id_categoria == 2)
            {
                $type_id = 1;
            }
            elseif ($data->id_categoria == 100)
            {
                $type_id = 3;
            }
            else
            {
                $type_id = 2;
            }

            $userId = $data->id_contacto ?? null;

            if ($userId !== null && !User::where('id', $userId)->exists())
            {
                $userId = null;
            }

            $enterpriseData = [
                'id' => $data->id,
                'name' => $data->empresa,
                'type_id' => $type_id,
                'user_id' => $userId,
                'referred_by' => $data->referido ?? null,
                'address' => $data->domicilio ?? null,
                'postal_code' => $data->codigo_postal ?? null,
                'locality' => $data->localidad ?? null,
                'province' => $data->provincia ?? null,
                'country' => $data->pais ?? null,
                'phone' => $data->telefono ?? null,
                'whatsapp' => $data->whatsapp ?? null,
                'email' => $data->email ?? null,
                'website' => $data->web ?? null,
                'payment_type_id' => $data->id_forma_pago ?? null,
                'invoice_type_id' => $data->id_factura_tipo ?? null,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingEnterprise)
            {
                DB::table('enterprises')->insert($enterpriseData);
            }
            else
            {
                DB::table('enterprises')->where('id', $existingEnterprise->id)->update($enterpriseData);
            }
        }

        // Enterprise Billing Address
        echo "Starting migration of Enterprise Billing Address...\n";
        $EnterpriseBillingAddress = DB::connection('mysql_tmp')->table('empresas_fiscales')
            ->where('grupo', env('CMS_GROUP'))
            ->get();

        foreach ($EnterpriseBillingAddress as $data)
        {
            $existingEnterpriseBillingAddress = DB::table('enterprise_billing_addresses')->where('id', $data->id)->first();

            $enterpriseBillingAddressData = [
                'id' => $data->id,
                'name' => $data->razon_social,
                'enterprise_id' => $data->id_empresa,
                'fiscal_condition_type_id' => $data->id_condicion_iva ?? null,
                'identification_number' => $data->cuit ?? null,
                'address' => $data->domicilio ?? null,
                'postal_code' => $data->codigo_postal ?? null,
                'locality' => $data->localidad ?? null,
                'province' => $data->provincia ?? null,
                'country' => $data->pais ?? null,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingEnterpriseBillingAddress)
            {
                DB::table('enterprise_billing_addresses')->insert($enterpriseBillingAddressData);
            }
            else
            {
                DB::table('enterprise_billing_addresses')->where('id', $existingEnterpriseBillingAddress->id)->update($enterpriseBillingAddressData);
            }
        }

        // Services
        echo "Starting migration of Services...\n";
        $services = DB::connection('mysql_tmp')
            ->table('servicios')
            ->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
            ->where('servicios.grupo', 502)
            ->where('servicios.estado', '>', 0)
            ->select('servicios.*', 'servicios_hosting.user')
            ->get();

        foreach ($services as $data)
        {
            $existingService = DB::table('services')->where('id', $data->id)->first();

            $cleaned_description = strip_tags($data->descripcion);

            $serviceData = [
                'id' => $data->id,
                'category_id' => $data->id_categoria,
                'enterprise_id' => $data->id_empresa,
                'operation' => ($data->operacion == 'C') ? 'Buy' : 'Sell',
                'desctiption' => $cleaned_description,
                'data' => json_encode(['user' => $data->user]),
                'currency_id' => $data->id_moneda,
                'price' => $data->valor,
                'discount' => $data->descuento,
                'frequency' => $data->frecuencia,
                'last_billed' => $data->ultima,
                'next_billing' => $data->proxima,
                'expires_at' => $data->caduca,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingService)
            {
                DB::table('services')->insert($serviceData);
            }
            else
            {
                DB::table('services')->where('id', $existingService->id)->update($serviceData);
            }
        }

        // Projects
        echo "Starting migration of Projects...\n";
        $projetcs = DB::connection('mysql_tmp')->table('proyectos')
            ->leftJoin('contactos', 'proyectos.responsable', '=', 'contactos.username')
            ->select(
                'proyectos.numero_proyecto',
                'proyectos.id_empresa',
                'proyectos.id_categoria',
                'contactos.id as leader_id',
                'proyectos.titulo',
                'proyectos.descripcion',
                'proyectos.valor',
                'proyectos.descuento',
                'proyectos.costo',
                'proyectos.desde',
                'proyectos.hasta',
                'proyectos.estado',
                'proyectos.fecha_alta',
                'proyectos.fecha_modificacion'
            )
            ->where('proyectos.grupo', 502)
            ->where('proyectos.estado', '>', 0)
            ->get();

        foreach ($projetcs as $data)
        {
            $existingProjetc = DB::table('projects')->where('id', $data->numero_proyecto)->first();

            $projetcData = [
                'id' => $data->numero_proyecto,
                'enterprise_id' => $data->id_empresa,
                'category_id' => $data->id_categoria,
                'leader_id' => $data->leader_id,
                'name' => $data->titulo,
                'description' => $data->descripcion,
                'price' => $data->valor,
                'discount' => $data->descuento,
                'cost' => $data->costo,
                'start_date' => $data->desde,
                'end_date' => $data->hasta,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingProjetc)
            {
                DB::table('projects')->insert($projetcData);
            }
            else
            {
                DB::table('projects')->where('id', $existingProjetc->id)->update($projetcData);
            }
        }

        // Invoices
        echo "Starting migration of Invoices...\n";
        $invoices = DB::connection('mysql_tmp')
            ->table('facturas')
            ->leftJoin('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
            ->leftJoin('empresas', 'empresas_fiscales.id_empresa', '=', 'empresas.id')
            ->where('facturas.grupo', 502)
            ->where('facturas.estado', '>', 0)
            ->select('facturas.*', 'empresas.id as enterprise_id')
            ->get();

        foreach ($invoices as $data)
        {
            $existingInvoice = DB::table('invoices')->where('id', $data->id)->first();

            $operation = $data->operacion === 'C' ? 'Buy' : 'Sell';

            $invoiceData = [
                'id' => $data->id,
                'enterprise_id' => $data->enterprise_id,
                'billing_id' => $data->id_empresa_fiscal,
                'type_id' => $data->id_factura_tipo,
                'operation' => $operation,
                'number' => $data->numero_talonario . $data->numero_factura,
                'date' => $data->fecha,
                'due_date' => $data->vencimiento,
                'gross_amount' => $data->bruto,
                'discount' => $data->descuento,
                'total_amount' => $data->total_neto,
                'balance' => $data->saldo,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingInvoice)
            {
                DB::table('invoices')->insert($invoiceData);
            }
            else
            {
                DB::table('invoices')->where('id', $existingInvoice->id)->update($invoiceData);
            }
        }

        // Invoice Items
        echo "Starting migration of Invoices Items...\n";
        $invoiceItems = DB::connection('mysql_tmp')->table('facturas_items')
            ->join('facturas', 'facturas_items.id_factura', '=', 'facturas.id')
            ->where('facturas_items.grupo', env('CMS_GROUP'))
            ->where('facturas.estado', '>', 0)
            ->select('facturas_items.*')
            ->get();

        foreach ($invoiceItems as $data)
        {
            $existingInvoiceItem = DB::table('invoice_items')->where('id', $data->id)->first();

            $invoiceItemData = [
                'invoice_id' => $data->id_factura,
                'category_id' => $data->id_categoria,
                'description' => $data->descripcion,
                'quantity' => 1,
                'unit_price' => $data->valor,
                'discount' => $data->descuento,
                'tax_percentage' => 21,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingInvoiceItem)
            {
                DB::table('invoice_items')->insert($invoiceItemData);
            }
            else
            {
                DB::table('invoice_items')->where('id', $existingInvoiceItem->id)->update($invoiceItemData);
            }
        }

        // Payments
        echo "Starting migration of Payments...\n";
        $payments = DB::connection('mysql_tmp')->table('movimientos')
            ->where('grupo', env('CMS_GROUP'))
            ->where('estado', '>', 0)
            ->get();

        foreach ($payments as $data)
        {
            $existingPayment = DB::table('payments')->where('id', $data->id)->first();

            $transaction_type = $data->transaccion === 'I' ? 'I' : 'E';

            $paymentData = [
                'enterprise_id' => $data->id_empresa,
                'transaction_type' => $transaction_type,
                'date' => $data->fecha,
                'invoice_id' => $data->id_factura,
                'account_id' => $data->id_cuenta,
                'type_id' => $data->id_forma_pago,
                'amount' => $data->valor,
                'remarks' => $data->observaciones,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingPayment)
            {
                DB::table('payments')->insert($paymentData);
            }
            else
            {
                DB::table('payments')->where('id', $existingPayment->id)->update($paymentData);
            }
        }

        // Communication Types
        echo "Starting migration of Communication Types...\n";
        $communicationTypes = DB::connection('mysql_tmp')->table('comunicaciones_tipo')
            ->get();

        foreach ($communicationTypes as $data)
        {
            $existingCommunicationTypes = DB::table('communication_types')->where('id', $data->id)->first();

            $communicationTypesData = [
                'id' => $data->id,
                'name' => $data->tipo,
                'status' => $data->estado - 1,
            ];

            if (!$existingCommunicationTypes)
            {
                DB::table('communication_types')->insert($communicationTypesData);
            }
            else
            {
                DB::table('communication_types')->where('id', $existingCommunicationTypes->id)->update($communicationTypesData);
            }
        }

        // Communication Templates
        echo "Starting migration of Communication Templates...\n";
        $communicationTemplates = DB::connection('mysql_tmp')->table('comunicaciones_templates')
            ->where('grupo', env('CMS_GROUP'))
            ->get();

        foreach ($communicationTemplates as $data)
        {
            $existingCommunicationTemplates = DB::table('communication_templates')->where('id', $data->id)->first();

            $communicationTemplatesData = [
                'id' => $data->id,
                'type_id' => $data->id_tipo,
                'name' => $data->asunto,
                'message' => $data->mensaje,
                'url' => $data->url,
            ];

            if (!$existingCommunicationTemplates)
            {
                DB::table('communication_templates')->insert($communicationTemplatesData);
            }
            else
            {
                DB::table('communication_templates')->where('id', $existingCommunicationTemplates->id)->update($communicationTemplatesData);
            }
        }

        // Communications
        echo "Starting migration of Communications...\n";
        $chunkSize = 1000;

        DB::connection('mysql_tmp')->table('comunicaciones')
            ->leftJoin('contactos', 'comunicaciones.id_contacto', '=', 'contactos.id')
            ->where('comunicaciones.grupo', env('CMS_GROUP'))
            ->where('comunicaciones.estado', '>', 0)
            ->select('comunicaciones.*', 'contactos.email')
            ->orderBy('comunicaciones.id')
            ->chunk($chunkSize, function ($communications)
            {
                $userEmails = $communications->pluck('email')->filter()->unique()->toArray();
                $users = User::whereIn('email', $userEmails)->get()->keyBy('email');

                $insertData = [];
                $updateData = [];

                foreach ($communications as $data)
                {
                    $userId = $users->get($data->email)?->id;

                    $communicationData = [
                        'id' => $data->id,
                        'user_id' => $userId,
                        'type_id' => $data->id_tipo,
                        'reference' => $data->id_referencia,
                        'data' => $data->data,
                        'sent' => $data->enviado ? Carbon::createFromTimestamp($data->enviado)->toDateTimeString() : null,
                        'received' => $data->recibido ? Carbon::createFromTimestamp($data->recibido)->toDateTimeString() : null,
                        'link' => $data->vinculo,
                        'status' => $data->estado,
                    ];

                    $existingCommunication = DB::table('communications')->where('id', $data->id)->first();
                    if (!$existingCommunication)
                    {
                        $insertData[] = $communicationData;
                    }
                    else
                    {
                        $updateData[] = $communicationData;
                    }
                }

                if (!empty($insertData))
                {
                    DB::table('communications')->insert($insertData);
                }

                if (!empty($updateData))
                {
                    foreach ($updateData as $update)
                    {
                        DB::table('communications')->where('id', $update['id'])->update($update);
                    }
                }
            });

        Log::info('Data import completed successfully.');
    }
}