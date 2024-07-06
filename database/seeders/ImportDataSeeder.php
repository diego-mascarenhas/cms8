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
                DB::table('clients')->where('id', $data->id)->update($enterpriseData);

                //Log::info('El empresa ' . $data->empresa . ' ha sido actualizado.');
            }
        }

        // Services Type
        $servicesType = DB::connection('mysql_tmp')->table('categorias_generales')
            ->where('grupo', 502)
            ->get();

        foreach ($servicesType as $data)
        {
            $existingService = DB::table('services_type')->where('id', $data->id)->first();

            $cleaned_description = strip_tags($data->descripcion);

            $serviceData = [
                'id' => $data->id,
                'name' => $data->categoria,
                'desctiption' => $cleaned_description,
                'price' => $data->valor,
                'discount' => $data->descuento,
                'frecuency' => $data->frecuencia,
                'status' => $data->estado,
                'created_at' => $data->fecha_alta,
                'updated_at' => $data->fecha_modificacion,
            ];
            
            if (!$existingService)
            {
                DB::table('services_type')->insert($serviceData);
            }
            else
            {
                DB::table('services_type')->where('id', $data->id)->update($serviceData);
            }
        }
        
        // Services
        $services = DB::connection('mysql_tmp')->table('servicios')
            ->where('grupo', 502)
            //->where('operacion', 'V')
            ->where('estado', '>', 0)
            //->where('estado', 4)
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
                'price' => $data->valor,
                'discount' => $data->descuento,
                'frecuency' => $data->frecuencia,
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
                DB::table('services')->where('id', $data->id)->update($serviceData);

                //Log::info('El empresa ' . $data->empresa . ' ha sido actualizado.');
            }
        }

        // Users
        $users = DB::connection('mysql_tmp')->table('contactos')
            ->whereNotNull('email')
            ->where('grupo', 502)
            ->whereNotNull('id_empresa')
            ->where('area_privada', '!=', 6)
            ->where('id', '>=', 5)
            //->limit(5)
            ->get();

        foreach ($users as $data)
        {
            $existingUser = DB::table('users')->where('email', $data->email)->first();

            $phone = $data->celular ?? $data->telefono ?? null;
            $cleaned_phone = $phone ? preg_replace('/\D/', '', $phone) : null;
            if (!empty($cleaned_phone) && strpos($cleaned_phone, '54') !== 0) $cleaned_phone = '54' . $cleaned_phone;
            if (empty($cleaned_phone)) $cleaned_phone = null;

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
            else
            {
                DB::table('users')->where('email', $data->email)->update($userData);

                //Log::info('El usuario con email ' . $data->email . ' ha sido actualizado.');
            }
        }
    }
}