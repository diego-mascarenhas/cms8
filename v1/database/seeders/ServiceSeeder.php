<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Service::create([
            'id' => 3060,
            'name' => 'Beginner',
            'description' => 'Plan de Hosting Beginner',
            'data' => '{"descripcion":"<em>Para pequeños proyectos</em><br><br><strong>30 GB</strong> de espacio<br>5 GB de transferencia mensual<br>1 Cuenta de <strong>emails</strong><br>Panel de control <strong>cPanel</strong><br>Backups semanales<br>Certificado <strong>SSL</strong><br><br><br><br><br><br><small><strong>500</strong> créditos email-Marketing mensuales</small><br><br>","valor_mensual":"4,99€","color":"red","plan":"revision_beginner"}',
            'price' => 4.99,
            'discount' => 0,
            'frequency' => 1,
            'order' => 1,
            'status' => 3,
            'services_type_id' => 1
        ]);

        Service::create([
            'id' => 3061,
            'name' => 'Explorer',
            'description' => 'Plan de Hosting Explorer',
            'data' => '{"descripcion":"<em>Mejor para blogs</em><br><br><strong>50 GB</strong> de espacio<br>10 GB de transferencia mensual<br>10 Cuentas de <strong>emails</strong><br>Panel de control <strong>cPanel</strong><br>Backups diarios<br>Certificado <strong>SSL</strong><br><strong>Dominio</strong> gratis por un año<br>Actualización de <strong>Plugins</strong><br><br><br><br><small><strong>1500</strong> créditos email-Marketing mensuales</small><br><br>","valor_mensual":"7,99€","valor_mensual_pago_anual":"4,99€","color":"blue","plan":"revision_explorer","imagen":"/assets/img/optimizado-wordpress-2.png"}',
            'price' => 4.99,
            'discount' => 0,
            'frequency' => 12,
            'order' => 2,
            'status' => 3,
            'services_type_id' => 1
        ]);

        Service::create([
            'id' => 3062,
            'name' => 'Enthusiast',
            'description' => 'Plan de Hosting Enthusiast',
            'data' => '{"descripcion":"<em>Solo para emprendedores</em><br><br><strong>100 GB </strong>de espacio<br>Transferencia mensual <strong>sin límites</strong><br>Cuentas de <strong>emails ilimitadas</strong><br>Panel de control <strong>cPanel</strong><br>Backups diarios<br>Certificado <strong>SSL</strong><br><strong>Dominio</strong> gratis por un año<br>Actualización de <strong>Plugins</strong><br>Monitoreo de <strong>vulnerabilidades</strong><br>Alta en Buscadores <strong>(SEO)</strong><br><br><small><strong>2500</strong> créditos email-Marketing mensuales</small><br><br>","valor_mensual":"11,99€","valor_mensual_pago_anual":"7,99€","color":"green","plan":"revision_enthusiast"}',
            'price' => 7.99,
            'discount' => 0,
            'frequency' => 12,
            'order' => 3,
            'status' => 3,
            'services_type_id' => 1
        ]);

        Service::create([
            'id' => 3074,
            'name' => 'Free',
            'description' => 'Plan de Email Marketing Free',
            'data' => '{"suscriptores":2000,"creditos":10000,"limite_diario":2000}',
            'price' => 0.00,
            'discount' => 0,
            'frequency' => 1,
            'order' => 1,
            'status' => 3,
            'services_type_id' => 4
        ]);

        Service::create([
            'id' => 3075,
            'name' => 'Essential 500',
            'description' => 'Plan de Email Marketing Essential 500',
            'data' => '{"descripcion":"500 suscriptores<br><strong>3000 créditos</strong> de envío<br>Límite diario 500<br><br>","suscriptores":500,"creditos":3000,"limite_diario":500,"valor_mensual":"6€","color":"red"}',
            'price' => 6.00,
            'discount' => 0,
            'frequency' => 1,
            'order' => 2,
            'status' => 3,
            'services_type_id' => 4
        ]);
    }
}
