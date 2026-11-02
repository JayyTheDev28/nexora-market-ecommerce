<?php

namespace Database\Seeders;

use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use Illuminate\Database\Seeder;

class PsgcSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Provinces
        $provinces = [
            [
                'code' => '043400000',
                'name' => 'Laguna',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
            ],
            [
                'code' => '042100000',
                'name' => 'Cavite',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
            ],
            [
                'code' => '133900000',
                'name' => 'Metro Manila',
                'region_code' => '130000000',
                'region_name' => 'National Capital Region (NCR)',
            ],
        ];

        foreach ($provinces as $p) {
            PsgcProvince::updateOrCreate(['code' => $p['code']], $p);
        }

        // 2. Cities / Municipalities
        $cities = [
            // Laguna
            ['code' => '043426000', 'name' => 'Santa Cruz', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043404000', 'name' => 'City of Calamba', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043411000', 'name' => 'Los Baños', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043403000', 'name' => 'City of Biñan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043419000', 'name' => 'Pagsanjan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043407000', 'name' => 'Cavinti', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043413000', 'name' => 'Lumban', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043410000', 'name' => 'Liliw', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043412000', 'name' => 'Luisiana', 'province_code' => '043400000', 'province_name' => 'Laguna'],

            // Cavite
            ['code' => '042103000', 'name' => 'City of Bacoor', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042106000', 'name' => 'City of Dasmariñas', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042109000', 'name' => 'City of Imus', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042119000', 'name' => 'City of Tagaytay', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042108000', 'name' => 'City of General Trias', 'province_code' => '042100000', 'province_name' => 'Cavite'],
        ];

        foreach ($cities as $c) {
            PsgcCity::updateOrCreate(['code' => $c['code']], $c);
        }

        // 3. Barangays
        $barangays = [
            // Santa Cruz, Laguna (043426000)
            ['code' => '043426001', 'name' => 'Poblacion I', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426002', 'name' => 'Poblacion II', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426003', 'name' => 'Poblacion III', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426004', 'name' => 'Poblacion IV', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426005', 'name' => 'Poblacion V', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426006', 'name' => 'Bagumbayan', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426007', 'name' => 'Bubukal', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426008', 'name' => 'Calios', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426009', 'name' => 'Duhat', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426010', 'name' => 'Gatid', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426011', 'name' => 'Pagsawitan', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426012', 'name' => 'Labuin', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426013', 'name' => 'Malinao', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426014', 'name' => 'Oogong', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426015', 'name' => 'Palasan', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426016', 'name' => 'Patimbao', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426017', 'name' => 'San Jose', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426018', 'name' => 'San Juan', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426019', 'name' => 'Santisima Cruz', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],
            ['code' => '043426020', 'name' => 'Santo Angel Central', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'province_code' => '043400000'],

            // Calamba City, Laguna (043404000)
            ['code' => '043404001', 'name' => 'Bagong Kalsada', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404002', 'name' => 'Bañadero', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404003', 'name' => 'Banlic', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404004', 'name' => 'Barandal', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404005', 'name' => 'Batino', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404006', 'name' => 'Bubuyan', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404007', 'name' => 'Bucal', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404011', 'name' => 'Canlubang', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404012', 'name' => 'Halang', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404027', 'name' => 'Mayapa', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404028', 'name' => 'Paciano Rizal', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404031', 'name' => 'Pansol', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404032', 'name' => 'Parian', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404033', 'name' => 'Poblacion 1', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404042', 'name' => 'Real', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],
            ['code' => '043404050', 'name' => 'Turbina', 'city_code' => '043404000', 'city_name' => 'City of Calamba', 'province_code' => '043400000'],

            // Los Baños, Laguna (043411000)
            ['code' => '043411001', 'name' => 'Anos', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411002', 'name' => 'Bagong Silang', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411003', 'name' => 'Bambang', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411004', 'name' => 'Batong Malake', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411005', 'name' => 'Bayog', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411006', 'name' => 'Lalakay', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411007', 'name' => 'Maahas', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411008', 'name' => 'Malinta', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411009', 'name' => 'Mayondon', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411010', 'name' => 'Putho-Tuntungin', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411011', 'name' => 'San Antonio', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411012', 'name' => 'Tadlac', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],
            ['code' => '043411013', 'name' => 'Timugan', 'city_code' => '043411000', 'city_name' => 'Los Baños', 'province_code' => '043400000'],

            // Biñan City, Laguna (043403000)
            ['code' => '043403001', 'name' => 'Biñan', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403003', 'name' => 'Canlalay', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403005', 'name' => 'De La Paz', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403007', 'name' => 'San Antonio', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403008', 'name' => 'San Francisco', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403009', 'name' => 'San Jose', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403010', 'name' => 'San Vicente', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403011', 'name' => 'Santo Domingo', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403012', 'name' => 'Santo Niño', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],
            ['code' => '043403013', 'name' => 'Santo Tomas', 'city_code' => '043403000', 'city_name' => 'City of Biñan', 'province_code' => '043400000'],

            // Pagsanjan, Laguna (043419000)
            ['code' => '043419001', 'name' => 'Anibong', 'city_code' => '043419000', 'city_name' => 'Pagsanjan', 'province_code' => '043400000'],
            ['code' => '043419002', 'name' => 'Sampaloc', 'city_code' => '043419000', 'city_name' => 'Pagsanjan', 'province_code' => '043400000'],
            ['code' => '043419003', 'name' => 'Poblacion I', 'city_code' => '043419000', 'city_name' => 'Pagsanjan', 'province_code' => '043400000'],
            ['code' => '043419004', 'name' => 'Poblacion II', 'city_code' => '043419000', 'city_name' => 'Pagsanjan', 'province_code' => '043400000'],
            ['code' => '043419009', 'name' => 'Magdapio', 'city_code' => '043419000', 'city_name' => 'Pagsanjan', 'province_code' => '043400000'],

            // Cavinti, Laguna (043407000)
            ['code' => '043407001', 'name' => 'Poblacion', 'city_code' => '043407000', 'city_name' => 'Cavinti', 'province_code' => '043400000'],
            ['code' => '043407006', 'name' => 'Duhat', 'city_code' => '043407000', 'city_name' => 'Cavinti', 'province_code' => '043400000'],

            // Lumban, Laguna (043413000)
            ['code' => '043413001', 'name' => 'Bagong Silang', 'city_code' => '043413000', 'city_name' => 'Lumban', 'province_code' => '043400000'],
            ['code' => '043413006', 'name' => 'Lewin', 'city_code' => '043413000', 'city_name' => 'Lumban', 'province_code' => '043400000'],

            // Liliw, Laguna (043410000)
            ['code' => '043410001', 'name' => 'Poblacion', 'city_code' => '043410000', 'city_name' => 'Liliw', 'province_code' => '043400000'],
            ['code' => '043410037', 'name' => 'Tuy-Baanan', 'city_code' => '043410000', 'city_name' => 'Liliw', 'province_code' => '043400000'],

            // Luisiana, Laguna (043412000)
            ['code' => '043412001', 'name' => 'De La Paz', 'city_code' => '043412000', 'city_name' => 'Luisiana', 'province_code' => '043400000'],

            // Bacoor City, Cavite (042103000)
            ['code' => '042103001', 'name' => 'Alima', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103002', 'name' => 'Aniban I', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103003', 'name' => 'Aniban II', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103008', 'name' => 'Habay I', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103009', 'name' => 'Habay II', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103019', 'name' => 'Molino I', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103020', 'name' => 'Molino II', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103021', 'name' => 'Molino III', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103022', 'name' => 'Molino IV', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103023', 'name' => 'Niog I', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103024', 'name' => 'Niog II', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103027', 'name' => 'Queens Row Central', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103028', 'name' => 'Queens Row East', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103029', 'name' => 'Queens Row West', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103036', 'name' => 'Talaba I', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],
            ['code' => '042103037', 'name' => 'Zapote I', 'city_code' => '042103000', 'city_name' => 'City of Bacoor', 'province_code' => '042100000'],

            // City of Dasmariñas, Cavite (042106000)
            ['code' => '042106001', 'name' => 'Burol', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106002', 'name' => 'Burol I', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106003', 'name' => 'Burol II', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106004', 'name' => 'Burol III', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106008', 'name' => 'Langkaan I', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106009', 'name' => 'Langkaan II', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106010', 'name' => 'Paliparan I', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106011', 'name' => 'Paliparan II', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106012', 'name' => 'Paliparan III', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106013', 'name' => 'Sabang', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106014', 'name' => 'Salawag', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106015', 'name' => 'Salitran I', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106016', 'name' => 'Salitran II', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106017', 'name' => 'Sampaloc I', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106018', 'name' => 'Sampaloc II', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106019', 'name' => 'San Agustin I', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],
            ['code' => '042106044', 'name' => 'Zone I (Poblacion)', 'city_code' => '042106000', 'city_name' => 'City of Dasmariñas', 'province_code' => '042100000'],

            // City of Imus, Cavite (042109000)
            ['code' => '042109001', 'name' => 'Alapan I-A', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109006', 'name' => 'Anabu I-A', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109007', 'name' => 'Anabu I-B', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109013', 'name' => 'Anabu II-A', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109020', 'name' => 'Bayan Luma I', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109021', 'name' => 'Bucandala I', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109023', 'name' => 'Malagasang I-A', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109027', 'name' => 'Pag-Asa I', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
            ['code' => '042109030', 'name' => 'Poblacion I-A', 'city_code' => '042109000', 'city_name' => 'City of Imus', 'province_code' => '042100000'],
        ];

        foreach ($barangays as $b) {
            PsgcBarangay::updateOrCreate(['code' => $b['code']], $b);
        }
    }
}

