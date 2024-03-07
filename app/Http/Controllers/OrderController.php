<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use App\Services\ApiAuthService;
use Session;

class OrderController extends Controller
{
    public function store()
    {
        $apiAuthService = new ApiAuthService();
        $apiService = new ApiService($apiAuthService);
        $usuario = env('BRULER_USERNAME');
        $password = env('BRULER_PASSWORD');

        $orderData = '{
            "Fecha":"2023-10-13 08:55",
            "ClienteId":7,
            "RemoteId":300,
            "TipoPedido":{"TipoPedidoId":1},
            "FormaPago":{"FormaPagoId":1},
            "Direccion":{
                "Direccion":"Calle s/nro",
                "CodigoPostal":"1258",
                "Ciudad":"CABA",
                "Localidad":"CABA",
                "Provincia":"CABA",
                "Pais":"Argentida",
                "Observaciones":"Prueba"
            },
            "Cliente":{
                "Apellido":"Perez",
                "Nombre":"Juan",
                "Telefono":"1123456789",
                "Email":""
            },
            "Items":[
                {
                    "Articulo":{
                        "ArticuloId":"417",
                        "Articulo":"DOCENA EMPANADAS",
                        "Precio":4200,
                        "ListAdicionales":[
                            {
                                "AdicionalId":"59",
                                "Adicional":"carne cortada a cuchillo",
                                "Precio":0,
                                "Cantidad":4
                            },
                            {
                                "AdicionalId":"61",
                                "Adicional":"jamon y queso",
                                "Precio":0,
                                "Cantidad":4
                            },
                            {
                                "AdicionalId":"63",
                                "Adicional":"verdura",
                                "Precio":0,
                                "Cantidad":4
                            }
                        ]
                    },
                    "Cantidad":1,
                    "PrecioUnitario":4200,
                    "Observaciones":""
                }
            ],
            "Total":4200,
            "IsDevelopment":true,
            "LocalId":1
        }';

        $response = $apiService->addOrder(json_decode($orderData));

        return response()->json($response);
    }

}
