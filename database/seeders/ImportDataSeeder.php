<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Hash;
use Log;

class ImportDataSeeder extends Seeder
{
    public function run()
    {
        // Users
        $users = DB::connection('mysql_tmp')->table('contactos')
            ->whereNotNull('email')
            ->where('grupo', 502)
            ->whereNotNull('id_empresa')
            ->where('area_privada', '!=', 6)
            ->where('id', '>', 2)
            //->limit(5)
            ->get();
            //dd($users);

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

        // Enterprises
        $enterprises = DB::connection('mysql_tmp')->table('empresas')
            ->where('grupo', 502)
            ->get();

        foreach ($enterprises as $data)
        {
            $existingEnterprise = DB::table('clients')->where('id', $data->id)->first();

            $enterpriseData = [
                'id' => $data->id,
                'name' => $data->empresa,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingEnterprise)
            {
                DB::table('clients')->insert($enterpriseData);
            }
            else
            {
                DB::table('clients')->where('id', $existingEnterprise->id)->update($enterpriseData);
            }
        }

        // Services Type
        $servicesType = DB::connection('mysql_tmp')->table('categorias_generales')
            ->where('grupo', 502)
            ->get();

        foreach ($servicesType as $data)
        {
            $existingServiceType = DB::table('service_type')->where('id', $data->id)->first();

            $cleaned_description = strip_tags($data->descripcion);

            $serviceData = [
                'id' => $data->id,
                'name' => $data->categoria,
                'desctiption' => $cleaned_description,
                'currency_id' => $data->id_moneda,
                'price' => $data->valor,
                'discount' => $data->descuento,
                'frequency' => $data->frecuencia,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];

            if (!$existingServiceType)
            {
                DB::table('service_type')->insert($serviceData);
            }
            else
            {
                DB::table('service_type')->where('id', $existingServiceType->id)->update($serviceData);
            }
        }

        // Services
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
                'type_id' => $data->id_categoria,
                'client_id' => $data->id_empresa,
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
        
        // Project Types
        $projectsType = DB::connection('mysql_tmp')->table('categorias_generales')
            ->where('grupo', 502)
            ->whereIn('id', [41, 42, 43, 44, 98, 99])
            ->get();

        foreach ($projectsType as $data)
        {
            $existingProjectType = DB::table('project_types')->where('id', $data->id)->first();

            $cleaned_description = strip_tags($data->descripcion);

            $serviceData = [
                'id' => $data->id,
                'name' => $data->categoria
            ];

            if (!$existingProjectType)
            {
                DB::table('project_types')->insert($serviceData);
            }
            else
            {
                DB::table('project_types')->where('id', $existingProjectType->id)->update($serviceData);
            }
        }

        // Projects
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
                'client_id' => $data->id_empresa,
                'type_id' => $data->id_categoria,
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

        // Invoice Types
        $invoiceTypes = DB::connection('mysql_tmp')->table('facturas_tipo')
            ->where('grupo', 502)
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

        // Invoices
        $invoices = DB::connection('mysql_tmp')
            ->table('facturas')
            ->leftJoin('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
            ->leftJoin('empresas', 'empresas_fiscales.id_empresa', '=', 'empresas.id')
            ->where('facturas.grupo', 502)
            ->where('facturas.estado', '>', 0)
            ->select('facturas.*', 'empresas.id as client_id')
            ->get();

        foreach ($invoices as $data)
        {
            $existingInvoice = DB::table('invoices')->where('id', $data->id)->first();

            $operation = $data->operacion === 'C' ? 'Buy' : 'Sell';

            $invoiceData = [
                'id' => $data->id,
                'client_id' => $data->client_id,
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

        // Payment Types
        $paymentTypes = DB::connection('mysql_tmp')->table('formas_pago')
            ->where('grupo', 502)
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

        // Payments
        $payments = DB::connection('mysql_tmp')->table('movimientos')
            ->where('grupo', 502)
            ->where('estado', '>', 0)
            ->get();

        foreach ($payments as $data)
        {
            $existingPayment = DB::table('payments')->where('id', $data->id)->first();

            $transaction_type = $data->transaccion === 'I' ? 'I' : 'E';

            $paymentData = [
                'client_id' => $data->id_empresa,
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
    }
}