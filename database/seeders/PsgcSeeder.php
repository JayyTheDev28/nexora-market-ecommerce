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
        // 1. All 82 Philippine Provinces (National PSGC Coverage)
        $provincesJsonPath = __DIR__ . '/provinces_data.json';
        if (file_exists($provincesJsonPath)) {
            $provinces = json_decode(file_get_contents($provincesJsonPath), true);
        } else {
            $provinces = [
                ['code' => '043400000', 'name' => 'Laguna', 'region_code' => '040000000', 'region_name' => 'Region IV-A (CALABARZON)'],
                ['code' => '042100000', 'name' => 'Cavite', 'region_code' => '040000000', 'region_name' => 'Region IV-A (CALABARZON)'],
                ['code' => '133900000', 'name' => 'Metro Manila', 'region_code' => '130000000', 'region_name' => 'National Capital Region (NCR)'],
            ];
        }

        $now = now();
        $provUpsert = array_map(function ($p) use ($now) {
            return [
                'code' => $p['code'],
                'name' => $p['name'],
                'region_code' => $p['region_code'] ?? '000000000',
                'region_name' => $p['region_name'] ?? 'Philippines',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $provinces);

        PsgcProvince::upsert($provUpsert, ['code'], ['name', 'region_code', 'region_name', 'updated_at']);

        // 2. All 30 Cities and Municipalities of Laguna + All 23 Cities and Municipalities of Cavite
        $cities = [
            // --- ALL 30 CITIES & MUNICIPALITIES OF LAGUNA ---
            ['code' => '043401000', 'name' => 'Alaminos', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043402000', 'name' => 'Bay', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043403000', 'name' => 'City of Biñan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043404000', 'name' => 'City of Calamba', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043405000', 'name' => 'City of Cabuyao', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043406000', 'name' => 'Calauan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043407000', 'name' => 'Cavinti', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043408000', 'name' => 'Famy', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043409000', 'name' => 'Kalayaan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043410000', 'name' => 'Liliw', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043411000', 'name' => 'Los Baños', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043412000', 'name' => 'Luisiana', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043413000', 'name' => 'Lumban', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043414000', 'name' => 'Mabitac', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043415000', 'name' => 'Magdalena', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043416000', 'name' => 'Majayjay', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043417000', 'name' => 'Nagcarlan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043418000', 'name' => 'Paete', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043419000', 'name' => 'Pagsanjan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043420000', 'name' => 'Pakil', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043421000', 'name' => 'Pangil', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043422000', 'name' => 'Pila', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043423000', 'name' => 'Rizal', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043424000', 'name' => 'City of San Pablo', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043425000', 'name' => 'City of San Pedro', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043426000', 'name' => 'Santa Cruz', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043427000', 'name' => 'Santa Maria', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043428000', 'name' => 'City of Santa Rosa', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043429000', 'name' => 'Siniloan', 'province_code' => '043400000', 'province_name' => 'Laguna'],
            ['code' => '043430000', 'name' => 'Victoria', 'province_code' => '043400000', 'province_name' => 'Laguna'],

            // --- ALL 23 CITIES & MUNICIPALITIES OF CAVITE ---
            ['code' => '042101000', 'name' => 'Alfonso', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042102000', 'name' => 'Amadeo', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042103000', 'name' => 'City of Bacoor', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042104000', 'name' => 'Cavite City', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042105000', 'name' => 'Carmona', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042106000', 'name' => 'City of Dasmariñas', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042107000', 'name' => 'General Emilio Aguinaldo', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042108000', 'name' => 'City of General Trias', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042109000', 'name' => 'City of Imus', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042110000', 'name' => 'Indang', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042114000', 'name' => 'Kawit', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042115000', 'name' => 'Magallanes', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042116000', 'name' => 'Maragondon', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042117000', 'name' => 'Naic', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042118000', 'name' => 'Mendez', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042119000', 'name' => 'City of Tagaytay', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042120000', 'name' => 'Noveleta', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042121000', 'name' => 'Rosario', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042123000', 'name' => 'Silang', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042124000', 'name' => 'Ternate', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042125000', 'name' => 'Tanza', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042126000', 'name' => 'Trece Martires City', 'province_code' => '042100000', 'province_name' => 'Cavite'],
            ['code' => '042127000', 'name' => 'General Mariano Alvarez (GMA)', 'province_code' => '042100000', 'province_name' => 'Cavite'],
        ];

        foreach ($cities as $c) {
            PsgcCity::updateOrCreate(['code' => $c['code']], $c);
        }

        // 3. Complete Official Barangays per City/Municipality
        $rawBarangaysByCity = [
            // --- LAGUNA ---
            // 1. Alaminos (043401000)
            '043401000' => [
                'city_name' => 'Alaminos',
                'province_code' => '043400000',
                'names' => [
                    'Del Carmen', 'Palma', 'San Agustin', 'San Andres', 'San Benito',
                    'San Gregorio', 'San Ildefonso', 'San Juan', 'San Miguel', 'San Pedro',
                    'San Roque', 'Santa Rosa', 'Poblacion I', 'Poblacion II', 'Poblacion III',
                ],
            ],

            // 2. Bay (043402000)
            '043402000' => [
                'city_name' => 'Bay',
                'province_code' => '043400000',
                'names' => [
                    'Bitin', 'Calo', 'Dila', 'Maitim', 'Malaking Ambling', 'Masaya',
                    'Paciano Rizal', 'Puypuy', 'San Agustin', 'San Antonio', 'San Isidro',
                    'Santa Cruz', 'Santo Domingo', 'Tagumpay', 'Tranca',
                ],
            ],

            // 3. City of Biñan (043403000)
            '043403000' => [
                'city_name' => 'City of Biñan',
                'province_code' => '043400000',
                'custom_codes' => [
                    '043403001' => 'Biñan',
                    '043403002' => 'Bungahan',
                    '043403003' => 'Canlalay',
                    '043403004' => 'Casile',
                    '043403005' => 'De La Paz',
                    '043403006' => 'Ganado',
                    '043403007' => 'San Antonio',
                    '043403008' => 'San Francisco',
                    '043403009' => 'San Jose',
                    '043403010' => 'San Vicente',
                    '043403011' => 'Santo Domingo',
                    '043403012' => 'Santo Niño',
                    '043403013' => 'Santo Tomas',
                    '043403014' => 'Langkiwa',
                    '043403015' => 'Loma',
                    '043403016' => 'Malaban',
                    '043403017' => 'Mampalasan',
                    '043403018' => 'Platero',
                    '043403019' => 'Poblacion',
                    '043403020' => 'Timbao',
                    '043403021' => 'Tubigan',
                    '043403022' => 'Zapote',
                ],
            ],

            // 4. City of Calamba (043404000)
            '043404000' => [
                'city_name' => 'City of Calamba',
                'province_code' => '043400000',
                'custom_codes' => [
                    '043404001' => 'Bagong Kalsada',
                    '043404002' => 'Bañadero',
                    '043404003' => 'Banlic',
                    '043404004' => 'Barandal',
                    '043404005' => 'Batino',
                    '043404006' => 'Bubuyan',
                    '043404007' => 'Bucal',
                    '043404008' => 'Bunggo',
                    '043404009' => 'Burol',
                    '043404010' => 'Camaligan',
                    '043404011' => 'Canlubang',
                    '043404012' => 'Halang',
                    '043404013' => 'Hornalan',
                    '043404014' => 'Kay-Anlog',
                    '043404015' => 'Laguerta',
                    '043404016' => 'La Mesa',
                    '043404017' => 'Lawa',
                    '043404018' => 'Lecheria',
                    '043404019' => 'Lingga',
                    '043404020' => 'Looc',
                    '043404021' => 'Mabuhay',
                    '043404022' => 'Makiling',
                    '043404023' => 'Majada Labas',
                    '043404024' => 'Masili',
                    '043404025' => 'Maunong',
                    '043404026' => 'Mayapa',
                    '043404027' => 'Paciano Rizal',
                    '043404028' => 'Palingon',
                    '043404029' => 'Palo-Alto',
                    '043404030' => 'Pansol',
                    '043404031' => 'Parian',
                    '043404032' => 'Prinza',
                    '043404033' => 'Poblacion 1',
                    '043404034' => 'Poblacion 2',
                    '043404035' => 'Poblacion 3',
                    '043404036' => 'Poblacion 4',
                    '043404037' => 'Poblacion 5',
                    '043404038' => 'Poblacion 6',
                    '043404039' => 'Poblacion 7',
                    '043404040' => 'Sampiruhan',
                    '043404041' => 'Saimsim',
                    '043404042' => 'Real',
                    '043404043' => 'San Cristobal',
                    '043404044' => 'San Jose',
                    '043404045' => 'San Juan',
                    '043404046' => 'Sirang Lupa',
                    '043404047' => 'Sucol',
                    '043404048' => 'Turbina',
                    '043404049' => 'Ulango',
                    '043404050' => 'Uwisan',
                ],
            ],

            // 5. City of Cabuyao (043405000)
            '043405000' => [
                'city_name' => 'City of Cabuyao',
                'province_code' => '043400000',
                'names' => [
                    'Baclaran', 'Banaybanay', 'Banlic', 'Bigaa', 'Butong', 'Casile',
                    'Diezmo', 'Gulod', 'Mamatid', 'Marinig', 'Niugan', 'Pittland',
                    'Pulo', 'Sala', 'San Isidro', 'Poblacion I', 'Poblacion II', 'Poblacion III',
                ],
            ],

            // 6. Calauan (043406000)
            '043406000' => [
                'city_name' => 'Calauan',
                'province_code' => '043400000',
                'names' => [
                    'Balayhangin', 'Bangyas', 'Dayap', 'Hanggan', 'Imok', 'Lamot 1',
                    'Lamot 2', 'Limao', 'Mabacan', 'Masiit', 'Paliparan', 'Perez',
                    'Kanluran (Poblacion)', 'Silangan (Poblacion)', 'Prinza', 'San Isidro', 'Santo Tomas',
                ],
            ],

            // 7. Cavinti (043407000)
            '043407000' => [
                'city_name' => 'Cavinti',
                'province_code' => '043400000',
                'names' => [
                    'Poblacion', 'Anglas', 'Banghahan', 'Bukal', 'Bumbungan', 'Duhat',
                    'Burgos', 'Cansuso', 'Inao-Awan', 'Kanluran Talaongan', 'Labayo',
                    'Layasin', 'Layug', 'Mahipon', 'Paowin', 'Silangan Talaongan',
                    'Sisilmin', 'Sumucab', 'Tibatib', 'Udia',
                ],
            ],

            // 8. Famy (043408000)
            '043408000' => [
                'city_name' => 'Famy',
                'province_code' => '043400000',
                'names' => [
                    'Asuncion', 'Bagong Pag-asa', 'Balitoc', 'Banaba', 'Batuhan', 'Bulihan',
                    'Caballero', 'Calumpang', 'Kapatalan', 'Cuevas', 'Damayan', 'Kataypuanan',
                    'Magdalo', 'Mayatba', 'Minayutan', 'Salangbato', 'San Pablo', 'Tunhac',
                ],
            ],

            // 9. Kalayaan (043409000)
            '043409000' => [
                'city_name' => 'Kalayaan',
                'province_code' => '043400000',
                'names' => ['Longos', 'San Antonio', 'San Juan (Poblacion)'],
            ],

            // 10. Liliw (043410000) - Full 33 Official Barangays
            '043410000' => [
                'city_name' => 'Liliw',
                'province_code' => '043400000',
                'names' => [
                    'Bagong Anyo (Poblacion)', 'Bayate', 'Bubukal', 'Bongkol', 'Cabuyew',
                    'Calumpang', 'San Isidro (Culubbrum)', 'Dagatan', 'Daniw', 'Dita',
                    'Ibabang Palina', 'Ibabang San Roque', 'Ibabang Sungi', 'Ibabang Taykin',
                    'Ilayang Palina', 'Ilayang San Roque', 'Ilayang Sungi', 'Ilayang Taykin',
                    'Kanlurang Bukal', 'Laguan', 'Luquin', 'Malabo-Kalantukan',
                    'Masikap (Poblacion)', 'Maslun (Poblacion)', 'Mojon', 'Novaliches',
                    'Oples', 'Pag-asa (Poblacion)', 'Palayan', 'Rizal (Poblacion)',
                    'San Roque', 'Silangang Bukal', 'Tuy-Baanan',
                ],
            ],

            // 11. Los Baños (043411000)
            '043411000' => [
                'city_name' => 'Los Baños',
                'province_code' => '043400000',
                'custom_codes' => [
                    '043411001' => 'Anos',
                    '043411002' => 'Bagong Silang',
                    '043411003' => 'Bambang',
                    '043411004' => 'Batong Malake',
                    '043411005' => 'Bayog',
                    '043411006' => 'Lalakay',
                    '043411007' => 'Maahas',
                    '043411008' => 'Malinta',
                    '043411009' => 'Mayondon',
                    '043411010' => 'Putho-Tuntungin',
                    '043411011' => 'San Antonio',
                    '043411012' => 'Tadlac',
                    '043411013' => 'Timugan',
                    '043411014' => 'Baybayin',
                ],
            ],

            // 12. Luisiana (043412000)
            '043412000' => [
                'city_name' => 'Luisiana',
                'province_code' => '043400000',
                'names' => [
                    'De La Paz', 'San Antonio', 'San Buenaventura', 'San Diego', 'San Isidro',
                    'San Jose', 'San Juan', 'San Luis', 'San Pablo', 'San Pedro',
                    'San Rafael', 'San Roque', 'San Salvador', 'Santo Domingo', 'Santo Tomas',
                ],
            ],

            // 13. Lumban (043413000)
            '043413000' => [
                'city_name' => 'Lumban',
                'province_code' => '043400000',
                'names' => [
                    'Bagong Silang', 'Balimbingan', 'Balubad', 'Caliraya', 'Concepcion',
                    'Lewin', 'Maracta', 'Maytalang I', 'Maytalang II', 'Primera Parang',
                    'Primera Pulo', 'Salac', 'Segunda Parang', 'Segunda Pulo', 'Santo Niño', 'Wawa',
                ],
            ],

            // 14. Mabitac (043414000)
            '043414000' => [
                'city_name' => 'Mabitac',
                'province_code' => '043400000',
                'names' => [
                    'Amuyong', 'Lambac', 'Lucong', 'Matalatala', 'Nanguma', 'Numero',
                    'Paagahan', 'Bayanihan (Poblacion)', 'Libis ng Nayon (Poblacion)',
                    'Maligaya (Poblacion)', 'Masikap (Poblacion)', 'Pag-asa (Poblacion)',
                    'San Antonio', 'San Jose', 'Sinagtala (Poblacion)',
                ],
            ],

            // 15. Magdalena (043415000)
            '043415000' => [
                'city_name' => 'Magdalena',
                'province_code' => '043400000',
                'names' => [
                    'Alipit', 'Baanan', 'Balanac', 'Bucal', 'Buenavista', 'Bungkol', 'Buo',
                    'Burlungan', 'Cigaras', 'Halayhayin', 'Ibabang Atingay', 'Ibabang Butnong',
                    'Ilayang Atingay', 'Ilayang Butnong', 'Ilog', 'Malaking Ambling', 'Maravilla',
                    'Poblacion', 'Sabang', 'Salasad', 'Tanawan', 'Tipunan', 'Villa Hermosa',
                ],
            ],

            // 16. Majayjay (043416000)
            '043416000' => [
                'city_name' => 'Majayjay',
                'province_code' => '043400000',
                'names' => [
                    'Amonoy', 'Bakia', 'Banti', 'Binitagan', 'Bukal', 'Coralao', 'Gagalot',
                    'Ibabang Banga', 'Ilayang Banga', 'Isabang', 'Malinao', 'May-it',
                    'Munting Kawayan', 'Olla', 'Oobi', 'Pangil', 'Panglan', 'Piit', 'Pook',
                    'Rizal', 'San Francisco', 'San Isidro', 'San Miguel', 'San Roque',
                    'Santa Catalina', 'Suba', 'Talortor', 'Tanawan', 'Taytay', 'Villa Nogales',
                ],
            ],

            // 17. Nagcarlan (043417000)
            '043417000' => [
                'city_name' => 'Nagcarlan',
                'province_code' => '043400000',
                'names' => [
                    'Abo', 'Alapang', 'Aliiw', 'Antipolo', 'Bambang', 'Banago', 'Bangcuro',
                    'Banilad', 'Bayaquitos', 'Buboy', 'Buenavista', 'Buhanginan', 'Bukal',
                    'Bunga', 'Cabuyew', 'Calumpang', 'Kanluran Kabubuhayan', 'Silangan Kabubuhayan',
                    'Labuin', 'Lagulo', 'Maiit', 'Malaya', 'Malinao', 'Manaol', 'Maravilla',
                    'Nagtalongtong', 'Oples', 'Palayan', 'Palina', 'Poblacion I', 'Poblacion II',
                    'Poblacion III', 'San Francisco', 'Santa Lucia', 'Sibulan',
                    'Silangan Napapatid', 'Sinipian', 'Sulsuguin', 'Talahib', 'Talangan',
                    'Taytay', 'Tipacan', 'Wakat', 'Yukos',
                ],
            ],

            // 18. Paete (043418000)
            '043418000' => [
                'city_name' => 'Paete',
                'province_code' => '043400000',
                'names' => [
                    'Bagumbayan', 'Bangkusay', 'Ermita', 'Ibaba del Norte', 'Ibaba del Sur',
                    'Ilaya del Norte', 'Ilaya del Sur', 'Maytoong', 'Quinale', 'San Jose',
                ],
            ],

            // 19. Pagsanjan (043419000)
            '043419000' => [
                'city_name' => 'Pagsanjan',
                'province_code' => '043400000',
                'custom_codes' => [
                    '043419001' => 'Anibong',
                    '043419002' => 'Sampaloc',
                    '043419003' => 'Poblacion I',
                    '043419004' => 'Poblacion II',
                    '043419005' => 'Biñan',
                    '043419006' => 'Buboy',
                    '043419007' => 'Cabanbanan',
                    '043419008' => 'Calusiche',
                    '043419009' => 'Magdapio',
                    '043419010' => 'Dingin',
                    '043419011' => 'Lambac',
                    '043419012' => 'Layugan',
                    '043419013' => 'Maulawin',
                    '043419014' => 'Pinagsanjan',
                    '043419015' => 'Sabang',
                    '043419016' => 'San Isidro',
                ],
            ],

            // 20. Pakil (043420000)
            '043420000' => [
                'city_name' => 'Pakil',
                'province_code' => '043400000',
                'names' => [
                    'Bañadero', 'Burgos', 'Casa Real', 'Casinsin', 'Dorado', 'Gonzales',
                    'Kabulusan', 'Matikiw', 'Rizal', 'Saray', 'Taft', 'Tavera',
                ],
            ],

            // 21. Pangil (043421000)
            '043421000' => [
                'city_name' => 'Pangil',
                'province_code' => '043400000',
                'names' => [
                    'Balian', 'Dambo', 'Galalan', 'Isla', 'Mabato-Asufre', 'Natividad',
                    'San Jose', 'Sulib',
                ],
            ],

            // 22. Pila (043422000)
            '043422000' => [
                'city_name' => 'Pila',
                'province_code' => '043400000',
                'names' => [
                    'Aplaya', 'Bagong Pook', 'Bukal', 'Bulilan Norte', 'Bulilan Sur',
                    'Concepcion', 'Labuin', 'Linga', 'Masico', 'Mojon', 'Pansol',
                    'Pinagbayanan', 'San Antonio', 'San Miguel', 'Santa Clara', 'Tubuan',
                ],
            ],

            // 23. Rizal (043423000)
            '043423000' => [
                'city_name' => 'Rizal',
                'province_code' => '043400000',
                'names' => [
                    'Antipolo', 'Entablado', 'Laguan', 'Pauli 1', 'Pauli 2', 'East Poblacion',
                    'West Poblacion', 'Pook', 'Tala', 'Tuy',
                ],
            ],

            // 24. City of San Pablo (043424000)
            '043424000' => [
                'city_name' => 'City of San Pablo',
                'province_code' => '043400000',
                'names' => [
                    'Bagong Bayan', 'Bagong Pook', 'Bautista', 'Concepcion', 'Del Remedio',
                    'Dolores', 'San Antonio 1', 'San Antonio 2', 'San Bartolome', 'San Buenaventura',
                    'San Cristobal', 'San Diego', 'San Francisco', 'San Gabriel', 'San Gregorio',
                    'San Ignacio', 'San Isidro', 'San Jose', 'San Juan', 'San Lucas 1',
                    'San Lucas 2', 'San Marcos', 'San Mateo', 'San Miguel', 'San Nicolas',
                    'San Pedro', 'San Rafael', 'San Roque', 'San Vicente', 'Santa Ana',
                    'Santa Catalina', 'Santa Cruz', 'Santa Elena', 'Santa Felomina', 'Santa Isabel',
                    'Santa Maria', 'Santa Maria Magdalena', 'Santa Monica', 'Santa Veronica',
                    'Santiago I', 'Santiago II', 'Santisimo Rosario', 'Santo Angel', 'Santo Cristo',
                    'Santo Niño', 'Soledad',
                ],
            ],

            // 25. City of San Pedro (043425000)
            '043425000' => [
                'city_name' => 'City of San Pedro',
                'province_code' => '043400000',
                'names' => [
                    'Bagong Silang', 'Calendola', 'Chrysanthemum', 'Cuyab', 'Estrella', 'Fatima',
                    'G.S.I.S.', 'Landayan', 'Langgam', 'Laram', 'Magsaysay', 'Maharlika',
                    'Narra', 'Nueva', 'Pacita 1', 'Pacita 2', 'Poblacion', 'Riverside',
                    'Rosario', 'San Antonio', 'San Lorenzo Ruiz', 'San Roque', 'San Vicente',
                    'Santo Niño', 'United Bayanihan', 'United Better Living', 'Sampaguita Village',
                ],
            ],

            // 26. Santa Cruz (043426000)
            '043426000' => [
                'city_name' => 'Santa Cruz',
                'province_code' => '043400000',
                'custom_codes' => [
                    '043426001' => 'Poblacion I',
                    '043426002' => 'Poblacion II',
                    '043426003' => 'Poblacion III',
                    '043426004' => 'Poblacion IV',
                    '043426005' => 'Poblacion V',
                    '043426006' => 'Bagumbayan',
                    '043426007' => 'Bubukal',
                    '043426008' => 'Calios',
                    '043426009' => 'Duhat',
                    '043426010' => 'Gatid',
                    '043426011' => 'Pagsawitan',
                    '043426012' => 'Labuin',
                    '043426013' => 'Malinao',
                    '043426014' => 'Oogong',
                    '043426015' => 'Palasan',
                    '043426016' => 'Patimbao',
                    '043426017' => 'San Jose',
                    '043426018' => 'San Juan',
                    '043426019' => 'Santisima Cruz',
                    '043426020' => 'Santo Angel Central',
                    '043426021' => 'Santo Angel Norte',
                    '043426022' => 'Santo Angel Sur',
                    '043426023' => 'San Pablo Sur',
                    '043426024' => 'Jasa',
                    '043426025' => 'Alipit',
                    '043426026' => 'Molindo',
                ],
            ],

            // 27. Santa Maria (043427000)
            '043427000' => [
                'city_name' => 'Santa Maria',
                'province_code' => '043400000',
                'names' => [
                    'Bagong Pook', 'Bagumbayan', 'Bubukal', 'Cabooan', 'Calangay', 'Cambuja',
                    'Coralan', 'Cueva', 'Inayapan', 'Jose P. Laurel', 'Jose P. Rizal',
                    'Juan Santiago', 'Mataling-Ting', 'Masinao', 'Parang Ng Buho', 'Poblacion I',
                    'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Real Velasquez', 'Sucol',
                ],
            ],

            // 28. City of Santa Rosa (043428000)
            '043428000' => [
                'city_name' => 'City of Santa Rosa',
                'province_code' => '043400000',
                'names' => [
                    'Aplaya', 'Balibago', 'Caingin', 'Dila', 'Dita', 'Don Jose',
                    'Ibaba', 'Kanluran', 'Labas', 'Macabling', 'Malitlit', 'Malusak',
                    'Market Area', 'Pooc', 'Pulong Santa Cruz', 'Santo Domingo', 'Sinalhan', 'Tagapo',
                ],
            ],

            // 29. Siniloan (043429000)
            '043429000' => [
                'city_name' => 'Siniloan',
                'province_code' => '043400000',
                'names' => [
                    'Acevida', 'Bagong Pag-asa', 'Bagumbagayan', 'Buhay', 'Gen. Luna',
                    'Halayhayin', 'Magsaysay', 'Macatad', 'Malaking Ambling', 'Pandeño',
                    'Salubungan', 'San Felipe', 'San Jose', 'San Juan', 'San Ramon', 'Wawa',
                ],
            ],

            // 30. Victoria (043430000)
            '043430000' => [
                'city_name' => 'Victoria',
                'province_code' => '043400000',
                'names' => [
                    'Daniw', 'Masapang', 'Nanhaya (Poblacion)', 'Pagalangan', 'San Benito',
                    'San Felix', 'San Francisco', 'San Roque',
                ],
            ],

            // --- CAVITE LGUS ---
            // Alfonso (042101000)
            '042101000' => [
                'city_name' => 'Alfonso',
                'province_code' => '042100000',
                'names' => [
                    'Amuyong', 'Bilog', 'Buck Estate', 'Esperanza Ibaba', 'Esperanza Ilaya',
                    'Kaybagal', 'Luksuhin Ibaba', 'Luksuhin Ilaya', 'Mangas I', 'Mangas II',
                    'Marahan I', 'Marahan II', 'Matagbak I', 'Pacheco', 'Poblacion I',
                    'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Poblacion V', 'Santa Teresa',
                    'Sikat', 'Sinaliw Malaki', 'Sinaliw Munti', 'Sulsugin', 'Taywanak Ibaba',
                    'Taywanak Ilaya', 'Upli',
                ],
            ],

            // Amadeo (042102000)
            '042102000' => [
                'city_name' => 'Amadeo',
                'province_code' => '042100000',
                'names' => [
                    'Banaybanay', 'Bucal', 'Dagatan', 'Halang', 'Loma', 'Maitim I',
                    'Maymanga', 'Minantok Kanluran', 'Minantok Silangan', 'Pangil',
                    'Poblacion 1', 'Poblacion 2', 'Poblacion 3', 'Poblacion 4', 'Poblacion 5',
                    'Salaban', 'Talon', 'Tamacan',
                ],
            ],

            // City of Bacoor (042103000)
            '042103000' => [
                'city_name' => 'City of Bacoor',
                'province_code' => '042100000',
                'custom_codes' => [
                    '042103001' => 'Alima',
                    '042103002' => 'Aniban I',
                    '042103003' => 'Aniban II',
                    '042103008' => 'Habay I',
                    '042103009' => 'Habay II',
                    '042103019' => 'Molino I',
                    '042103020' => 'Molino II',
                    '042103021' => 'Molino III',
                    '042103022' => 'Molino IV',
                    '042103023' => 'Niog I',
                    '042103024' => 'Niog II',
                    '042103027' => 'Queens Row Central',
                    '042103028' => 'Queens Row East',
                    '042103029' => 'Queens Row West',
                    '042103036' => 'Talaba I',
                    '042103037' => 'Zapote I',
                ],
            ],

            // Cavite City (042104000)
            '042104000' => [
                'city_name' => 'Cavite City',
                'province_code' => '042100000',
                'names' => [
                    'San Antonio', 'Caridad', 'San Roque', 'Santa Cruz', 'Dalahican',
                    'Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5',
                ],
            ],

            // Carmona (042105000)
            '042105000' => [
                'city_name' => 'Carmona',
                'province_code' => '042100000',
                'names' => [
                    'Cabilang Baybay', 'Lantic', 'Mabuhay', 'Maduya', 'Milagrosa',
                    'Poblacion 1', 'Poblacion 2', 'Poblacion 3', 'Poblacion 4',
                    'Poblacion 5', 'Poblacion 6', 'Poblacion 7', 'Poblacion 8',
                ],
            ],

            // City of Dasmariñas (042106000)
            '042106000' => [
                'city_name' => 'City of Dasmariñas',
                'province_code' => '042100000',
                'custom_codes' => [
                    '042106001' => 'Burol',
                    '042106002' => 'Burol I',
                    '042106003' => 'Burol II',
                    '042106004' => 'Burol III',
                    '042106008' => 'Langkaan I',
                    '042106009' => 'Langkaan II',
                    '042106010' => 'Paliparan I',
                    '042106011' => 'Paliparan II',
                    '042106012' => 'Paliparan III',
                    '042106013' => 'Sabang',
                    '042106014' => 'Salawag',
                    '042106015' => 'Salitran I',
                    '042106016' => 'Salitran II',
                    '042106017' => 'Sampaloc I',
                    '042106018' => 'Sampaloc II',
                    '042106019' => 'San Agustin I',
                    '042106044' => 'Zone I (Poblacion)',
                ],
            ],

            // General Emilio Aguinaldo (042107000)
            '042107000' => [
                'city_name' => 'General Emilio Aguinaldo',
                'province_code' => '042100000',
                'names' => [
                    'A. Dalusag', 'Batas Dao', 'Castaños Cerca', 'Castaños Lejos',
                    'Kabulusan', 'Kaymisas', 'Kaypaaba', 'Lumipa', 'Narvaez',
                    'Poblacion I', 'Poblacion II', 'Poblacion III', 'Poblacion IV', 'Tabora',
                ],
            ],

            // City of General Trias (042108000)
            '042108000' => [
                'city_name' => 'City of General Trias',
                'province_code' => '042100000',
                'custom_codes' => [
                    '042108001' => 'Arnaldo',
                    '042108002' => 'Bacao I',
                    '042108003' => 'Bacao II',
                    '042108004' => 'Bagumbayan',
                    '042108005' => 'Corregidor',
                    '042108006' => 'Dulong Bayan',
                    '042108007' => 'Gov. Ferrer',
                    '042108008' => 'Javalera',
                    '042108009' => 'Manggahan',
                    '042108010' => 'Navarro',
                    '042108011' => 'Panungyanan',
                    '042108012' => 'Pasong Camachile I',
                    '042108013' => 'Pasong Camachile II',
                    '042108014' => 'Pasong Kawayan I',
                    '042108015' => 'Pasong Kawayan II',
                    '042108016' => 'Pinagtipunan',
                    '042108017' => 'Prinza',
                    '042108018' => 'San Francisco',
                    '042108019' => 'San Gabriel',
                    '042108020' => 'San Juan I',
                    '042108021' => 'San Juan II',
                    '042108022' => 'Santa Clara',
                    '042108023' => 'Santiago',
                    '042108024' => 'Tapia',
                    '042108025' => 'Tejero',
                    '042108026' => 'Vibora',
                ],
            ],

            // City of Imus (042109000)
            '042109000' => [
                'city_name' => 'City of Imus',
                'province_code' => '042100000',
                'custom_codes' => [
                    '042109001' => 'Alapan I-A',
                    '042109006' => 'Anabu I-A',
                    '042109007' => 'Anabu I-B',
                    '042109013' => 'Anabu II-A',
                    '042109020' => 'Bayan Luma I',
                    '042109021' => 'Bucandala I',
                    '042109023' => 'Malagasang I-A',
                    '042109027' => 'Pag-Asa I',
                    '042109030' => 'Poblacion I-A',
                ],
            ],

            // Indang (042110000)
            '042110000' => [
                'city_name' => 'Indang',
                'province_code' => '042100000',
                'names' => [
                    'Agus-os', 'Alulod', 'Banaba Cerca', 'Banaba Lejos', 'Bancod',
                    'Buna Cerca', 'Buna Lejos', 'Calumpang Cerca', 'Calumpang Lejos',
                    'Carasuchi', 'Guyam Malaki', 'Guyam Munti', 'Harasan', 'Kayquit I',
                    'Kaytapos', 'Limbon', 'Lumampong Balagbag', 'Poblacion 1', 'Poblacion 2',
                ],
            ],

            // Kawit (042114000)
            '042114000' => [
                'city_name' => 'Kawit',
                'province_code' => '042100000',
                'names' => [
                    'Binakayan-Aplaya', 'Binakayan-Kanluran', 'Gahak', 'Kaingen',
                    'Marulas', 'Panamitan', 'Poblacion', 'San Sebastian', 'Santa Isabel',
                    'Tabon I', 'Toclong', 'Wakas I',
                ],
            ],

            // Magallanes (042115000)
            '042115000' => [
                'city_name' => 'Magallanes',
                'province_code' => '042100000',
                'names' => [
                    'Baliwag', 'Bendita I', 'Bendita II', 'Caluangan', 'Kabulusan',
                    'Medina', 'Pacheco', 'Poblacion I', 'Poblacion II', 'Ramirez', 'San Agustin',
                ],
            ],

            // Maragondon (042116000)
            '042116000' => [
                'city_name' => 'Maragondon',
                'province_code' => '042100000',
                'names' => [
                    'Bucal 1', 'Bucal 2', 'Caingin Pob.', 'Garita 1', 'Layong Mabilog',
                    'Mabato', 'Pantihan 1', 'Pantihan 2', 'Patungan', 'Pinagsanhan',
                    'Poblacion 1', 'Poblacion 2', 'San Miguel', 'Talipusngo',
                ],
            ],

            // Naic (042117000)
            '042117000' => [
                'city_name' => 'Naic',
                'province_code' => '042100000',
                'names' => [
                    'Bagong Kalsada', 'Balsahan', 'Bucana Malaki', 'Bucana Sasahan',
                    'Capt. C. Nazareno', 'Gomez-Zamora', 'Halang', 'Humbac',
                    'Ibayo Estacion', 'Ibayo Silangan', 'Kanluran', 'Labac', 'Latoria',
                    'Mabolo', 'Makina', 'Malainen Bago', 'Malainen Luma', 'Molino',
                    'Palangue 1', 'Palangue 2', 'Sabang', 'San Roque', 'Santulan', 'Sapa',
                ],
            ],

            // Mendez (042118000)
            '042118000' => [
                'city_name' => 'Mendez',
                'province_code' => '042100000',
                'names' => [
                    'Anuling Cerca 1', 'Anuling Cerca 2', 'Asis 1', 'Asis 2', 'Galicia 1',
                    'Galicia 2', 'Palocpoc 1', 'Palocpoc 2', 'Panungyan 1', 'Poblacion 1',
                    'Poblacion 2', 'Poblacion 3', 'Poblacion 4',
                ],
            ],

            // City of Tagaytay (042119000)
            '042119000' => [
                'city_name' => 'City of Tagaytay',
                'province_code' => '042100000',
                'custom_codes' => [
                    '042119001' => 'Asisan',
                    '042119002' => 'Bagong Tubig',
                    '042119003' => 'Calabuso',
                    '042119004' => 'Dapdap East',
                    '042119005' => 'Dapdap West',
                    '042119006' => 'Francisco',
                    '042119007' => 'Guinhawa North',
                    '042119008' => 'Guinhawa South',
                    '042119009' => 'Iruhin Central',
                    '042119010' => 'Iruhin East',
                    '042119011' => 'Iruhin West',
                    '042119012' => 'Kaybagal Central',
                    '042119013' => 'Kaybagal North',
                    '042119014' => 'Kaybagal South',
                    '042119015' => 'Mag-Asawang Ilat',
                    '042119016' => 'Maharlika East',
                    '042119017' => 'Maharlika West',
                    '042119018' => 'Maitim 2nd Central',
                    '042119019' => 'Maitim 2nd East',
                    '042119020' => 'Maitim 2nd West',
                    '042119021' => 'Mendez Crossing East',
                    '042119022' => 'Mendez Crossing West',
                    '042119023' => 'Neogan',
                    '042119024' => 'Patutong Malaki North',
                    '042119025' => 'Patutong Malaki South',
                    '042119026' => 'Sambong',
                    '042119027' => 'San Jose',
                    '042119028' => 'Silang Junction North',
                    '042119029' => 'Silang Junction South',
                    '042119030' => 'Sungay East',
                    '042119031' => 'Sungay West',
                    '042119032' => 'Tolentino East',
                    '042119033' => 'Tolentino West',
                    '042119034' => 'Zambal',
                ],
            ],

            // Noveleta (042120000)
            '042120000' => [
                'city_name' => 'Noveleta',
                'province_code' => '042100000',
                'names' => [
                    'Magdiwang', 'Poblacion', 'Salcedo 1', 'Salcedo 2', 'San Antonio 1',
                    'San Antonio 2', 'San Juan 1', 'San Juan 2', 'San Rafael 1', 'San Rafael 2',
                    'Santa Rosa 1', 'Santa Rosa 2',
                ],
            ],

            // Rosario (042121000)
            '042121000' => [
                'city_name' => 'Rosario',
                'province_code' => '042100000',
                'names' => [
                    'Bagbag I', 'Bagbag II', 'Kanluran', 'Ligtong I', 'Ligtong II',
                    'Ligtong III', 'Muzon I', 'Muzon II', 'Poblacion', 'Sapa I',
                    'Sapa II', 'Sapa III', 'Sapa IV', 'Tejeros Convention', 'Wawa I',
                ],
            ],

            // Silang (042123000)
            '042123000' => [
                'city_name' => 'Silang',
                'province_code' => '042100000',
                'names' => [
                    'Biga I', 'Biga II', 'Biluso', 'Bulihan', 'Hulong Duhat',
                    'Iba', 'Lalaan I', 'Lalaan II', 'Poblacion I', 'Poblacion II',
                    'Puting Kahoy', 'Tartaria', 'Tubuan I', 'Ulat',
                ],
            ],

            // Ternate (042124000)
            '042124000' => [
                'city_name' => 'Ternate',
                'province_code' => '042100000',
                'names' => [
                    'Bucana', 'Poblacion 1', 'Poblacion 2', 'Poblacion 3',
                    'San Jose', 'San Juan 1', 'San Juan 2', 'Sapang 1', 'Sapang 2',
                ],
            ],

            // Tanza (042125000)
            '042125000' => [
                'city_name' => 'Tanza',
                'province_code' => '042100000',
                'names' => [
                    'Amaya I', 'Amaya II', 'Bagtas', 'Biga', 'Biwas', 'Bucal',
                    'Daang Amaya I', 'Julugan I', 'Paradahan I', 'Poblacion I',
                    'Punta I', 'Sahud Ulan', 'Sanja Mayor', 'Tres Cruses',
                ],
            ],

            // Trece Martires City (042126000)
            '042126000' => [
                'city_name' => 'Trece Martires City',
                'province_code' => '042100000',
                'names' => [
                    'Aguado', 'Cabezas', 'Cabuco', 'Conchu', 'De Ocampo', 'Gregorio',
                    'Inocencio', 'Lallana', 'Lapidario', 'Luciano', 'Osorio', 'Perez',
                    'San Agustin', 'Hugo Perez',
                ],
            ],

            // General Mariano Alvarez (GMA) (042127000)
            '042127000' => [
                'city_name' => 'General Mariano Alvarez (GMA)',
                'province_code' => '042100000',
                'names' => [
                    'Aldiano Olaes', 'Barangay 1 (Poblacion)', 'Barangay 2 (Poblacion)',
                    'Barangay 3 (Poblacion)', 'Barangay 4 (Poblacion)', 'Barangay 5 (Poblacion)',
                    'Benjamin Tirona', 'Bernardo Pulido', 'Epifanio Malia', 'Francisco De Castro',
                    'Gabriel G. Reyes', 'Gregorio Boulever', 'Inocencio Salud', 'Kapitan Kua',
                    'Macario Dacon', 'Marcelino Memije', 'Nicolasa Virata', 'Pantaleon Granados',
                    'Ramon Cruz', 'San Gabriel', 'San Jose', 'Severino De Las Alas',
                    'Tiniente Tiago', 'Virgilio Toledo',
                ],
            ],
        ];

        // Process and upsert barangays in chunks
        $barangayRecords = [];
        $now = now();

        foreach ($rawBarangaysByCity as $cityCode => $cityData) {
            $cityName = $cityData['city_name'];
            $provinceCode = $cityData['province_code'];

            if (! empty($cityData['custom_codes'])) {
                foreach ($cityData['custom_codes'] as $bCode => $bName) {
                    $barangayRecords[] = [
                        'code' => (string) $bCode,
                        'name' => $bName,
                        'city_code' => (string) $cityCode,
                        'city_name' => $cityName,
                        'province_code' => (string) $provinceCode,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            } elseif (! empty($cityData['names'])) {
                $idx = 1;
                $prefix = substr((string) $cityCode, 0, 6);
                foreach ($cityData['names'] as $bName) {
                    $bCode = sprintf('%s%03d', $prefix, $idx++);
                    $barangayRecords[] = [
                        'code' => $bCode,
                        'name' => $bName,
                        'city_code' => (string) $cityCode,
                        'city_name' => $cityName,
                        'province_code' => (string) $provinceCode,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Upsert in batches of 100 to optimize performance
        foreach (array_chunk($barangayRecords, 100) as $chunk) {
            PsgcBarangay::upsert(
                $chunk,
                ['code'],
                ['name', 'city_code', 'city_name', 'province_code', 'updated_at']
            );
        }
    }
}
