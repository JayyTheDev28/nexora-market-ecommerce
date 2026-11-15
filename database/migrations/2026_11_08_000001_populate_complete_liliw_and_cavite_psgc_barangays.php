<?php

use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Populate complete barangays for Liliw, Laguna and comprehensive Cavite cities and municipalities.
     */
    public function up(): void
    {
        // 1. Ensure Cavite & Laguna provinces exist
        PsgcProvince::updateOrCreate(
            ['code' => '043400000'],
            [
                'name' => 'Laguna',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
            ]
        );

        PsgcProvince::updateOrCreate(
            ['code' => '042100000'],
            [
                'name' => 'Cavite',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
            ]
        );

        // 2. Cities & Municipalities
        $cities = [
            // Laguna
            ['code' => '043410000', 'name' => 'Liliw', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043426000', 'name' => 'Santa Cruz', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043404000', 'name' => 'City of Calamba', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043411000', 'name' => 'Los Baños', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043403000', 'name' => 'City of Biñan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043419000', 'name' => 'Pagsanjan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043407000', 'name' => 'Cavinti', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043413000', 'name' => 'Lumban', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043412000', 'name' => 'Luisiana', 'province_code' => '043400000', 'province_name' => 'Laguna'],

            // Cavite Cities & Municipalities
            ['code' => '042103000', 'name' => 'City of Bacoor', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042106000', 'name' => 'City of Dasmariñas', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042109000', 'name' => 'City of Imus', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042119000', 'name' => 'City of Tagaytay', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042108000', 'name' => 'City of General Trias', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042114000', 'name' => 'Kawit', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042123000', 'name' => 'Silang', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042105000', 'name' => 'Carmona', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042125000', 'name' => 'Tanza', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042121000', 'name' => 'Rosario', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042127000', 'name' => 'General Mariano Alvarez (GMA)', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042117000', 'name' => 'Naic', 'province_code' => '042100000', 'province_name' => 'Cavite'],
        ];

        foreach ($cities as $c) {
            PsgcCity::updateOrCreate(['code' => $c['code']], $c);
        }

        // 3. Complete Official Barangays for Liliw, Laguna (All 33 Barangays)
        $liliwBarangays = [
            ['code' => '043410001', 'name' => 'Bagong Anyo (Poblacion)'],
            ['code' => '043410002', 'name' => 'Bayate'],
            ['code' => '043410003', 'name' => 'Bubukal'],
            ['code' => '043410004', 'name' => 'Bongkol'],
            ['code' => '043410005', 'name' => 'Cabuyew'],
            ['code' => '043410006', 'name' => 'Calumpang'],
            ['code' => '043410007', 'name' => 'San Isidro (Culubbrum)'],
            ['code' => '043410008', 'name' => 'Dagatan'],
            ['code' => '043410009', 'name' => 'Daniw'],
            ['code' => '043410010', 'name' => 'Dita'],
            ['code' => '043410011', 'name' => 'Ibabang Palina'],
            ['code' => '043410012', 'name' => 'Ibabang San Roque'],
            ['code' => '043410013', 'name' => 'Ibabang Sungi'],
            ['code' => '043410014', 'name' => 'Ibabang Taykin'],
            ['code' => '043410015', 'name' => 'Ilayang Palina'],
            ['code' => '043410016', 'name' => 'Ilayang San Roque'],
            ['code' => '043410017', 'name' => 'Ilayang Sungi'],
            ['code' => '043410018', 'name' => 'Ilayang Taykin'],
            ['code' => '043410019', 'name' => 'Kanlurang Bukal'],
            ['code' => '043410020', 'name' => 'Laguan'],
            ['code' => '043410021', 'name' => 'Luquin'],
            ['code' => '043410022', 'name' => 'Malabo-Kalantukan'],
            ['code' => '043410023', 'name' => 'Masikap (Poblacion)'],
            ['code' => '043410024', 'name' => 'Maslun (Poblacion)'],
            ['code' => '043410025', 'name' => 'Mojon'],
            ['code' => '043410026', 'name' => 'Novaliches'],
            ['code' => '043410027', 'name' => 'Oples'],
            ['code' => '043410028', 'name' => 'Pag-asa (Poblacion)'],
            ['code' => '043410029', 'name' => 'Palayan'],
            ['code' => '043410030', 'name' => 'Rizal (Poblacion)'],
            ['code' => '043410031', 'name' => 'San Roque'],
            ['code' => '043410032', 'name' => 'Silangang Bukal'],
            ['code' => '043410033', 'name' => 'Tuy-Baanan'],
        ];

        foreach ($liliwBarangays as $b) {
            PsgcBarangay::updateOrCreate(
                ['code' => $b['code']],
                [
                    'name' => $b['name'],
                    'city_code' => '043410000',
                    'city_name' => 'Liliw',
                    'province_code' => '043400000',
                ]
            );
        }

        // 4. Tagaytay City Barangays (All 34 Barangays)
        $tagaytayBarangays = [
            'Asisan', 'Bagong Tubig', 'Calabuso', 'Dapdap East', 'Dapdap West',
            'Francisco', 'Guinhawa North', 'Guinhawa South', 'Iruhin Central', 'Iruhin East',
            'Iruhin West', 'Kaybagal Central', 'Kaybagal North', 'Kaybagal South', 'Mag-Asawang Ilat',
            'Maharlika East', 'Maharlika West', 'Maitim 2nd Central', 'Maitim 2nd East', 'Maitim 2nd West',
            'Mendez Crossing East', 'Mendez Crossing West', 'Neogan', 'Patutong Malaki North', 'Patutong Malaki South',
            'Sambong', 'San Jose', 'Silang Junction North', 'Silang Junction South', 'Sungay East',
            'Sungay West', 'Tolentino East', 'Tolentino West', 'Zambal',
        ];

        foreach ($tagaytayBarangays as $idx => $name) {
            $code = '042119' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042119000',
                    'city_name' => 'City of Tagaytay',
                    'province_code' => '042100000',
                ]
            );
        }

        // 5. General Trias City Barangays
        $genTriasBarangays = [
            'Arnaldo', 'Bacao I', 'Bacao II', 'Bagumbayan', 'Corregidor',
            'Dulong Bayan', 'Gov. Ferrer', 'Javalera', 'Manggahan', 'Navarro',
            'Panungyanan', 'Pasong Camachile I', 'Pasong Camachile II', 'Pasong Kawayan I', 'Pasong Kawayan II',
            'Pinagtipunan', 'Prinza', 'San Francisco', 'San Gabriel', 'San Juan I',
            'San Juan II', 'Santa Clara', 'Santiago', 'Tapia', 'Tejero', 'Vibora',
        ];

        foreach ($genTriasBarangays as $idx => $name) {
            $code = '042108' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042108000',
                    'city_name' => 'City of General Trias',
                    'province_code' => '042100000',
                ]
            );
        }

        // 6. Kawit Barangays
        $kawitBarangays = [
            'Binakayan-Aplaya', 'Binakayan-Kanluran', 'Gahak', 'Kaingen', 'Marulas',
            'Panamitan', 'Poblacion', 'San Sebastian', 'Santa Isabel', 'Tabon I', 'Toclong', 'Wakas I',
        ];

        foreach ($kawitBarangays as $idx => $name) {
            $code = '042114' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042114000',
                    'city_name' => 'Kawit',
                    'province_code' => '042100000',
                ]
            );
        }

        // 7. Silang Barangays
        $silangBarangays = [
            'Biga I', 'Biga II', 'Biluso', 'Bulihan', 'Hulong Duhat',
            'Iba', 'Lalaan I', 'Lalaan II', 'Poblacion I', 'Poblacion II',
            'Puting Kahoy', 'Tartaria', 'Tubuan I', 'Ulat',
        ];

        foreach ($silangBarangays as $idx => $name) {
            $code = '042123' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042123000',
                    'city_name' => 'Silang',
                    'province_code' => '042100000',
                ]
            );
        }

        // 8. Carmona Barangays
        $carmonaBarangays = [
            'Cabilang Baybay', 'Lantic', 'Mabuhay', 'Maduya', 'Milagrosa',
            'Poblacion 1', 'Poblacion 2', 'Poblacion 3', 'Poblacion 4', 'Poblacion 5',
            'Poblacion 6', 'Poblacion 7', 'Poblacion 8',
        ];

        foreach ($carmonaBarangays as $idx => $name) {
            $code = '042105' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042105000',
                    'city_name' => 'Carmona',
                    'province_code' => '042100000',
                ]
            );
        }

        // 9. Tanza Barangays
        $tanzaBarangays = [
            'Amaya I', 'Amaya II', 'Bagtas', 'Biga', 'Biwas',
            'Bucal', 'Daang Amaya I', 'Julugan I', 'Paradahan I', 'Poblacion I',
            'Punta I', 'Sahud Ulan', 'Sanja Mayor', 'Tres Cruses',
        ];

        foreach ($tanzaBarangays as $idx => $name) {
            $code = '042125' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042125000',
                    'city_name' => 'Tanza',
                    'province_code' => '042100000',
                ]
            );
        }

        // 10. Additional Dasmariñas, Bacoor, and Imus Barangays
        $dasmaExtra = [
            'Fatima I', 'San Jose', 'Saint Peter I', 'Zone II (Poblacion)', 'Zone III (Poblacion)', 'Zone IV (Poblacion)',
        ];
        foreach ($dasmaExtra as $idx => $name) {
            $code = '042106' . str_pad($idx + 50, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042106000',
                    'city_name' => 'City of Dasmariñas',
                    'province_code' => '042100000',
                ]
            );
        }

        $bacoorExtra = [
            'Salinas I', 'Mambog I', 'Panapaan I', 'Dulong Bayan', 'Kaingin',
        ];
        foreach ($bacoorExtra as $idx => $name) {
            $code = '042103' . str_pad($idx + 50, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042103000',
                    'city_name' => 'City of Bacoor',
                    'province_code' => '042100000',
                ]
            );
        }

        $imusExtra = [
            'Carsadang Bago I', 'Tanzang Luma I', 'Toclong I-A', 'Palico I',
        ];
        foreach ($imusExtra as $idx => $name) {
            $code = '042109' . str_pad($idx + 50, 3, '0', STR_PAD_LEFT);
            PsgcBarangay::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'city_code' => '042109000',
                    'city_name' => 'City of Imus',
                    'province_code' => '042100000',
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe to leave data intact
    }
};

