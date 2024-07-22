<?php

namespace Database\Seeders;

use App\Models\NetworkDevice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NetworkDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NetworkDevice::create([
            'id' => 1,
            'name' => 'Allied Telesis',
            'device_type' => 'Switch'
        ]);
        
        NetworkDevice::create([
            'id' => 2,
            'name' => 'Mikrotik',
            'device_type' => 'Router/Firewall'
        ]);

        NetworkDevice::create([
            'id' => 3,
            'name' => 'Mikrotik (FO)',
            'device_type' => 'Switch'
        ]);
    }
}
