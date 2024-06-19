<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $data = [
      'components' => '[]',
      'styles' => '[]',
      'css' => '* { box-sizing: border-box; } body {margin: 0;}',
      'html' => '<body></body>'
    ];

    Page::create([
      'id' => 1,
      'name' => 'GrapesJs',
			'gjs_data' => $data,
      'status' => 1,
    ]);
  }
}