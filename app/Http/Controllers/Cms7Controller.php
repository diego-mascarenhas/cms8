<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Cms7Controller extends Controller
{
    /**
     * Muestra un contacto de la base de datos antigua
     */
    public function showContact($id)
    {
        $contact = DB::connection('mysql_tmp')
            ->table('contactos')
            ->where('id', $id)
            ->first();

        if (!$contact) {
            return redirect()
                ->back()
                ->with('error', 'Contacto no encontrado en el sistema antiguo');
        }

        // Obtenemos la empresa si existe
        $empresa = null;
        if ($contact->id_empresa) {
            $empresa = DB::connection('mysql_tmp')
                ->table('empresas')
                ->where('id', $contact->id_empresa)
                ->first();
        }

        return view('cms7.contact', compact('contact', 'empresa'));
    }

    /**
     * Muestra una empresa de la base de datos antigua
     */
    public function showEnterprise($id)
    {
        $empresa = DB::connection('mysql_tmp')
            ->table('empresas')
            ->where('id', $id)
            ->first();

        if (!$empresa) {
            return redirect()
                ->back()
                ->with('error', 'Empresa no encontrada en el sistema antiguo');
        }

        // Obtenemos los contactos asociados a esta empresa
        $contactos = DB::connection('mysql_tmp')
            ->table('contactos')
            ->where('id_empresa', $id)
            ->orderBy('nombre')
            ->get();

        return view('cms7.enterprise', compact('empresa', 'contactos'));
    }

    /**
     * Muestra los servicios de la base de datos antigua
     */
    public function showServices($empresaId = null)
    {
        $query = DB::connection('mysql_tmp')
            ->table('servicios')
            ->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio')
            ->where('servicios.grupo', env('CMS_GROUP', 501))
            ->where('servicios.estado', '>', 0);

        if ($empresaId) {
            $query->where('servicios.id_empresa', $empresaId);
        }

        $servicios = $query->select('servicios.*', 'servicios_hosting.*')
            ->orderBy('servicios.id_empresa')
            ->get();

        return view('cms7.services', compact('servicios', 'empresaId'));
    }

    /**
     * Muestra las facturas de la base de datos antigua
     */
    public function showInvoices($empresaId = null)
    {
        $query = DB::connection('mysql_tmp')
            ->table('facturas')
            ->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
            ->where('facturas.grupo', env('CMS_GROUP', 501))
            ->where('facturas.estado', '>', 0);

        if ($empresaId) {
            $query->where('empresas_fiscales.id_empresa', $empresaId);
        }

        $facturas = $query->select(
                'facturas.id',
                'empresas_fiscales.id_empresa as enterprise_id',
                'facturas.fecha',
                'facturas.vencimiento',
                'facturas.operacion',
                'facturas.numero_talonario',
                'facturas.numero_factura',
                'facturas.bruto',
                'facturas.descuento',
                'facturas.total_neto',
                'facturas.saldo',
                'facturas.estado'
            )
            ->orderBy('facturas.fecha', 'desc')
            ->get();

        return view('cms7.invoices', compact('facturas', 'empresaId'));
    }

    /**
     * Busca contactos en la base de datos antigua
     */
    public function searchContacts(Request $request)
    {
        $query = $request->input('q');
        
        if (empty($query)) {
            return view('cms7.search');
        }

        $contactos = DB::connection('mysql_tmp')
            ->table('contactos')
            ->where('grupo', env('CMS_GROUP', 501))
            ->where(function($q) use ($query) {
                $q->where('nombre', 'like', "%{$query}%")
                  ->orWhere('apellido', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('telefono', 'like', "%{$query}%")
                  ->orWhere('celular', 'like', "%{$query}%");
            })
            ->orderBy('nombre')
            ->limit(50)
            ->get();

        $empresas = DB::connection('mysql_tmp')
            ->table('empresas')
            ->where('grupo', env('CMS_GROUP', 501))
            ->where(function($q) use ($query) {
                $q->where('empresa', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('telefono', 'like', "%{$query}%");
            })
            ->orderBy('empresa')
            ->limit(50)
            ->get();

        return view('cms7.search', compact('contactos', 'empresas', 'query'));
    }

    /**
     * Muestra los detalles de una empresa de la base de datos antigua
     */
    public function enterpriseDetails($id)
    {
        // Buscar la empresa en la base de datos antigua
        $empresa = DB::connection('mysql_tmp')
            ->table('empresas')
            ->where('id', $id)
            ->first();
            
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }
        
        // Obtener los contactos relacionados
        $contactos = DB::connection('mysql_tmp')
            ->table('contactos')
            ->where('id_empresa', $id)
            ->get();
        
        // Obtener servicios relacionados
        $servicios = DB::connection('mysql_tmp')
            ->table('servicios')
            ->join('servicios_hosting', 'servicios.id', '=', 'servicios_hosting.id_servicio', 'left')
            ->where('servicios.id_empresa', $id)
            ->where('servicios.estado', '>', 0)
            ->select('servicios.*', 'servicios_hosting.*')
            ->get();
            
        // Obtener datos fiscales si existen
        $datosFiscales = DB::connection('mysql_tmp')
            ->table('empresas_fiscales')
            ->where('id_empresa', $id)
            ->get();
        
        // Obtener facturas si existen
        $facturas = DB::connection('mysql_tmp')
            ->table('facturas')
            ->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
            ->where('empresas_fiscales.id_empresa', $id)
            ->select('facturas.*', 'empresas_fiscales.razon_social as razon_social')
            ->orderBy('facturas.fecha', 'desc')
            ->limit(20)
            ->get();
        
        return view('cms7.empresa-detalle', compact('empresa', 'contactos', 'servicios', 'datosFiscales', 'facturas'));
    }
} 