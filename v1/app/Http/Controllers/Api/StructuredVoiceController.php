<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StructuredVoiceController extends Controller
{
	public function processOrder(Request $request)
	{
		$texto = $request->input('text');
		list($url, $parsedData) = $this->parseText($texto);
		return response()->json(['url' => $url, 'data' => $parsedData]);
	}

	private function addCommaIfNotExists($texto)
	{
		$texto = preg_replace('/(Agregar a mesa \d+)(?!,)/', '$1,', $texto);
		$texto = preg_replace('/(Abrir mesa \d+)(?!,)/', '$1,', $texto);
		$texto = preg_replace('/(Cerrar mesa \d+)(?!,)/', '$1,', $texto);
		return $texto;
	}

	private function getProductId($prodText, $products)
	{
		$keys = array_keys($products);
		foreach ($keys as $key)
		{
			if (strpos($prodText, $key) !== false)
			{
				return $products[$key];
			}
		}
		return null;
	}

	private function parseText($texto)
	{
		$productos = [
			"milanesa con pure" => 1,
			"milanesa con papas fritas" => 2,
			"ravioles" => 3,
			"gaseosa" => 4,
			"agua" => 5,
			"vino" => 6,
			"café" => 7
		];

		$url = null;
		$parsedResult = [];

		$texto = $this->addCommaIfNotExists($texto);

		if (strpos($texto, "Abrir mesa") === 0)
		{
			$command = 1;
			if (preg_match('/Abrir mesa (\d+), para (\d+) comensales/', $texto, $match))
			{
				$mesa = $match[1];
				$quantity = $match[2];
				$parsedResult[] = [
					"command" => $command,
					"quantity" => (int) $quantity,
					"text" => str_replace(",", "", $texto)
				];
				$url = "https://brulerapi.ar/api/pedimosfacilmdw/comando/$mesa/add/$command";
			}
		}
		elseif (strpos($texto, "Agregar a mesa") === 0)
		{
			$command = 2;
			if (preg_match('/Agregar a mesa (\d+),/', $texto, $match))
			{
				$mesa = $match[1];
				preg_match_all('/(\d+)\s([\w\s]+?)(?:\s(sin sal|bien frías|del tiempo))?(?=\d|\s*$)/', $texto, $items, PREG_SET_ORDER);
				foreach ($items as $item)
				{
					$quantity = $item[1];
					$prodText = trim($item[2]);
					$opt = isset($item[3]) ? trim($item[3]) : "";
					$product = $this->getProductId($prodText, $productos);
					if ($product !== null)
					{
						$parsedResult[] = [
							"command" => $command,
							"product" => $product,
							"quantity" => (int) $quantity,
							"optionals" => $opt,
							"text" => "$quantity $prodText"
						];
						$url = "https://brulerapi.ar/api/pedimosfacilmdw/comando/$mesa/add/$command";
					}
				}
			}
		}
		elseif (strpos($texto, "Cerrar mesa") === 0)
		{
			$command = 6;
			if (preg_match('/Cerrar mesa (\d+)/', $texto, $match))
			{
				$mesa = $match[1];
				if (preg_match('/Cerrar mesa \d+ (.+)/', $texto, $optionalsMatch))
				{
					$optionals = trim($optionalsMatch[1]);
				}
				else
				{
					$optionals = "";
				}
				$parsedResult[] = [
					"command" => $command,
					"optionals" => $optionals,
					"text" => str_replace(",", "", $texto)
				];
				$url = "https://brulerapi.ar/api/pedimosfacilmdw/comando/$mesa/add/$command";
			}
		}

		return [$url, $parsedResult];
	}
}