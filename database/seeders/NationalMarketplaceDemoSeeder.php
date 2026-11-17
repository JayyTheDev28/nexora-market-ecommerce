<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\LogisticsProfile;
use App\Models\Product;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\StoreCategory;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use App\Services\CompensationPolicyService;
use App\Services\ShippingPricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fresh, no-orders national TEST dataset for Nexora Market.
 * Run ONLY on a disposable, isolated database after backing up any necessary data.
 * 100 buyers, 50 sellers, 1000 products, 22 logistics, 74 riders, 10 couriers.
 * Requires public PSGC API access for cities/municipalities not in existing PsgcSeeder.
 * Does not create orders, parcels, manifests, deliveries, cash movements, or payouts.
 */
class NationalMarketplaceDemoSeeder extends Seeder
{
    private const PASSWORD = 'Password123!@#';
    private const REGION_NAMES = [
        '130000000' => 'National Capital Region (NCR)',
        '140000000' => 'Cordillera Administrative Region (CAR)',
        '010000000' => 'Region I (Ilocos Region)',
        '020000000' => 'Region II (Cagayan Valley)',
        '030000000' => 'Region III (Central Luzon)',
        '040000000' => 'Region IV-A (CALABARZON)',
        '170000000' => 'MIMAROPA Region',
        '050000000' => 'Region V (Bicol Region)',
        '060000000' => 'Region VI (Western Visayas)',
        '070000000' => 'Region VII (Central Visayas)',
        '080000000' => 'Region VIII (Eastern Visayas)',
        '090000000' => 'Region IX (Zamboanga Peninsula)',
        '100000000' => 'Region X (Northern Mindanao)',
        '110000000' => 'Region XI (Davao Region)',
        '120000000' => 'Region XII (SOCCSKSARGEN)',
        '160000000' => 'Region XIII (Caraga)',
        '150000000' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
        '180000000' => 'Negros Island Region (NIR)',
    ];

    /** Five CALABARZON province operators plus one operator in each other region. */
    private const LOGISTICS_PROVINCES = [
        ['Laguna', '040000000'], ['Cavite', '040000000'],
        ['Batangas', '040000000'], ['Quezon', '040000000'], ['Rizal', '040000000'],
        ['Metro Manila', '130000000'], ['Benguet', '140000000'],
        ['Ilocos Norte', '010000000'], ['Cagayan', '020000000'], ['Bulacan', '030000000'],
        ['Palawan', '170000000'], ['Albay', '050000000'], ['Iloilo', '060000000'],
        ['Negros Occidental', '180000000'], ['Cebu', '070000000'], ['Leyte', '080000000'],
        ['Zamboanga Del Sur', '090000000'], ['Misamis Oriental', '100000000'],
        ['Davao Del Sur', '110000000'], ['South Cotabato', '120000000'],
        ['Agusan Del Norte', '160000000'], ['Lanao Del Sur', '150000000'],
    ];

    /** Ensure San Pablo has its own hub, and prefer key towns for the remaining hub locations. */
    private const PREFERRED_HUB_CITIES = [
        'Laguna' => ['San Pablo', 'Santa Cruz', 'Calamba', 'Santa Rosa', 'Biñan'],
        'Cavite' => ['Dasmariñas', 'Bacoor', 'Imus', 'General Trias', 'Tagaytay'],
        'Batangas' => ['Batangas City', 'Lipa', 'Tanauan', 'Santo Tomas', 'Nasugbu'],
        'Quezon' => ['Lucena', 'Tayabas', 'Sariaya', 'Candelaria', 'Lucban'],
        'Rizal' => ['Antipolo', 'Cainta', 'Taytay', 'Rodriguez', 'Binangonan'],
    ];

    private string $passwordHash;

    /** @var array<int,array{province:PsgcProvince,cities:\Illuminate\Support\Collection,company:User,hubs:array,zones:array}> */
    private array $companies = [];

    public function run(): void
    {
        if (app()->environment('production') && getenv('NEXORA_ALLOW_DEMO_SEED') !== '1') {
            throw new RuntimeException('Refusing demo dataset seed in production. Use a disposable database; explicit override requires NEXORA_ALLOW_DEMO_SEED=1.');
        }
        $this->passwordHash = Hash::make(self::PASSWORD);
        $this->call([PsgcSeeder::class, CategorySeeder::class, AdminUserSeeder::class]);
        $this->ensureSelectedProvincePsgc();
        $this->createLogisticsHubsAndZones();
        $this->createRidersAndCouriers();
        $this->createBuyers();
        $this->createSellersAndProducts();
        $this->call(VoucherSeeder::class);
        app(CompensationPolicyService::class)->ensureDefaultPolicies();
        app(ShippingPricingService::class)->ensureDefaultPolicy();
        $this->assertDataset();
    }

    private function ensureSelectedProvincePsgc(): void
    {
        foreach (self::LOGISTICS_PROVINCES as [$provinceName, $regionCode]) {
            $province = PsgcProvince::where('name', $provinceName)->first();
            if (! $province) {
                throw new RuntimeException("Missing official province PSGC code for {$provinceName}; check PsgcSeeder/provinces_data.json.");
            }
            // Legacy province JSON still maps Negros Occidental to Region VI; NIR is now region 18.
            if ($province->region_code !== $regionCode || $province->region_name !== self::REGION_NAMES[$regionCode]) {
                $province->update(['region_code' => $regionCode, 'region_name' => self::REGION_NAMES[$regionCode]]);
            }
            if (PsgcCity::where('province_code', $province->code)->count() === 0) {
                // No fabricated municipality codes: import authoritative PSGC-aligned city codes.
                $this->importPsgcCities($province);
            }
            if (PsgcCity::where('province_code', $province->code)->count() === 0) {
                throw new RuntimeException("No PSGC city records for {$provinceName}; seed cannot safely create hubs and zones.");
            }
        }
    }

    private function fetchPsgcJson(string $url): array
    {
        $caBundle = collect([
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
            'C:\\xampp\\phpMyAdmin\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ])->first(fn ($path) => is_string($path) && $path !== '' && is_file($path));

        $client = Http::timeout(25)->retry(2, 700)->acceptJson();
        if ($caBundle) {
            $client = $client->withOptions(['verify' => $caBundle]);
        }

        $response = $client->get($url);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('PSGC lookup returned no valid array');
        }

        return $response->json();
    }

    private function importPsgcCities(PsgcProvince $province): void
    {
        $url = $province->name === 'Metro Manila'
            ? 'https://psgc.gitlab.io/api/regions/130000000/cities-municipalities/'
            : "https://psgc.gitlab.io/api/provinces/{$province->code}/cities-municipalities/";
        try {
            $payload = $this->fetchPsgcJson($url);
            $rows = [];
            foreach ($payload as $row) {
                $code = (string) ($row['code'] ?? '');
                $name = trim((string) ($row['name'] ?? ''));
                if (! preg_match('/^\d{9}$/', $code) || $name === '') {
                    continue;
                }
                $rows[] = ['code' => $code, 'name' => $name, 'province_code' => $province->code,
                    'province_name' => $province->name, 'created_at' => now(), 'updated_at' => now()];
            }
            if (count($rows) < 1) {
                throw new RuntimeException('PSGC lookup supplied zero valid city records');
            }
            PsgcCity::upsert($rows, ['code'], ['name', 'province_code', 'province_name', 'updated_at']);
        } catch (\Throwable $e) {
            throw new RuntimeException("Cannot fetch official city/municipality codes for {$province->name}. Import a current PSGC dataset before seeding, or allow API access. URL: {$url}. Error: {$e->getMessage()}", 0, $e);
        }
    }

    /** Fetch official barangays only for cities hosting a physical hub. */
    private function ensureHubCityBarangay(PsgcCity $city): PsgcBarangay
    {
        $existing = PsgcBarangay::where('city_code', $city->code)->first();
        if ($existing) return $existing;
        $url = "https://psgc.gitlab.io/api/cities-municipalities/{$city->code}/barangays/";
        try {
            $payload = $this->fetchPsgcJson($url);
            $rows = [];
            foreach ($payload as $row) {
                $code = (string) ($row['code'] ?? '');
                $name = trim((string) ($row['name'] ?? ''));
                if (! preg_match('/^\d{9}$/', $code) || ! $name) continue;
                $rows[] = ['code' => $code, 'name' => $name,
                    'province_code' => $city->province_code,
                    'city_code' => $city->code, 'city_name' => $city->name,
                    'created_at' => now(), 'updated_at' => now()];
            }
            if ($rows === []) throw new RuntimeException('No valid barangays returned');
            PsgcBarangay::upsert($rows, ['code'], ['name', 'province_code', 'city_code', 'city_name', 'updated_at']);
        } catch (\Throwable $e) {
            throw new RuntimeException("No valid PSGC barangay for physical hub city {$city->name}: {$e->getMessage()}", 0, $e);
        }
        return PsgcBarangay::where('city_code', $city->code)->firstOrFail();
    }

    private function createLogisticsHubsAndZones(): void
    {
        foreach (self::LOGISTICS_PROVINCES as $index => [$name, $regionCode]) {
            $province = PsgcProvince::where('name', $name)->firstOrFail();
            $cities = PsgcCity::where('province_code', $province->code)->orderBy('name')->get();
            $company = $this->createAccount('logistics', $index+1, "logistics.{$province->code}@nexora.test",
                $name, 'Logistics', 200000000 + $index);
            $homeCity = $cities->first();
            LogisticsProfile::updateOrCreate(['user_id' => $company->id], [
                'company_name' => "Nexora {$name} Logistics (DEMO)",
                'sec_registration_no' => 'DEMO-SEC-'.str_pad((string)($index+1),5,'0',STR_PAD_LEFT),
                'tin' => sprintf('900-%03d-%03d-000', ($index+1), ($index+1)),
                'region' => self::REGION_NAMES[$regionCode], 'province' => $name,
                'city_municipality' => $homeCity->name, 'barangay' => 'See primary hub',
                'street_address' => 'Demo Operations Center', 'postal_code' => '0000',
                'contact_person_first_name' => $name, 'contact_person_last_name' => 'Operations',
                'position' => 'Operations Manager', 'email' => $company->email,
                'mobile_number' => $company->contact_number, 'service_areas' => [$province->code],
                'status' => 'approved',
            ]);
            SortingCenterProfile::updateOrCreate(['user_id' => $company->id], [
                'business_name' => "Nexora {$name} Logistics (DEMO)",
                'business_permit_path' => null,
            ]);
            $hubCount = $regionCode === '040000000' ? 5 : 2;
            $hubCities = $this->chooseHubCities($cities, $name, $hubCount);
            $hubs = [];
            foreach ($hubCities as $hubIndex => $city) {
                $barangay = $this->ensureHubCityBarangay($city);
                $hub = Hub::updateOrCreate(['code' => 'NX-HUB-'.$province->code.'-'.($hubIndex+1)], [
                    'logistics_company_id' => $company->id,
                    'name' => "{$name} {$city->name} Hub", 'province_code' => $province->code,
                    'province_name' => $name, 'city_code' => $city->code, 'city_name' => $city->name,
                    'barangay_code' => $barangay->code, 'barangay_name' => $barangay->name,
                    'street_address' => 'Demo Hub - actual site not verified',
                    'contact_number' => $company->contact_number, 'is_active' => true,
                ]);
                $hubs[] = $hub;
            }
            // Every municipality/city in the selected province gets a coverage zone.
            $zones = [];
            foreach ($cities as $cityIndex => $city) {
                $matchingHub = collect($hubs)->first(fn ($h) => $h->city_code === $city->code);
                $hub = $matchingHub ?: $hubs[$cityIndex % count($hubs)];
                $zone = Zone::updateOrCreate(['logistics_company_id' => $company->id, 'city_code' => $city->code], [
                    'hub_id' => $hub->id, 'name' => "{$city->name} Delivery Zone", 'code' => 'NX-Z-'.$city->code,
                    'province_code' => $province->code, 'province_name' => $province->name,
                    'city_name' => $city->name, 'is_active' => true,
                ]);
                $zones[] = $zone;
                // Attach known valid barangays for specificity, without inventing geographic codes.
                foreach (PsgcBarangay::where('city_code', $city->code)->cursor() as $brgy) {
                    ZoneBarangay::updateOrCreate(
                        ['logistics_company_id' => $company->id, 'barangay_code' => $brgy->code],
                        ['zone_id' => $zone->id, 'province_code' => $province->code,
                         'province_name' => $province->name, 'city_code' => $city->code,
                         'city_name' => $city->name, 'barangay_name' => $brgy->name]
                    );
                }
            }
            $this->companies[] = compact('province', 'cities', 'company', 'hubs', 'zones');
            $this->makeAddress($company, $province, $homeCity, 0);
        }
    }

    private function chooseHubCities($cities, string $provinceName, int $limit): array
    {
        $chosen = []; $used = [];
        foreach (self::PREFERRED_HUB_CITIES[$provinceName] ?? [] as $preferred) {
            $found = $cities->first(fn ($city) => str_contains(strtolower($city->name), strtolower($preferred))
                || str_contains(strtolower($preferred), strtolower($city->name)));
            if ($found && ! isset($used[$found->code])) { $chosen[] = $found; $used[$found->code] = true; }
            if (count($chosen) === $limit) return $chosen;
        }
        foreach ($cities as $city) {
            if (isset($used[$city->code])) continue;
            $chosen[] = $city; $used[$city->code] = true;
            if (count($chosen) === $limit) break;
        }
        return $chosen;
    }

    private function createRidersAndCouriers(): void
    {
        $counter = 0;
        foreach ($this->companies as $index => $item) {
            $region = $item['province']->region_code;
            $numberRiders = $region === '040000000' ? 8 : 2;
            for ($j=1; $j<=$numberRiders; $j++) {
                $counter++;
                $rider = $this->createAccount('rider', $counter,
                    'rider.'.strtolower($item['province']->code).".{$j}@nexora.test",
                    'Rider', sprintf('%03d', $counter), 300000000+$counter);
                $hub = $item['hubs'][($j-1) % count($item['hubs'])];
                $this->createCourierProfile($rider,$item['company'],$hub,'Motorcycle','NX-R-'.str_pad((string)$counter,5,'0',STR_PAD_LEFT));
                $z = collect($item['zones'])->filter(fn ($zone) => (int)$zone->hub_id === (int)$hub->id)->pluck('id')->take(8)->all();
                if (!$z) $z = [collect($item['zones'])->first()->id];
                $rider->assignedZones()->sync($z);
            }
            if ($region === '040000000') {
                for ($j=1;$j<=2;$j++) {
                    $courierIndex = ((int)$index*2)+$j;
                    $courier = $this->createAccount('courier',$courierIndex,
                        'transfer.'.$item['province']->code.".{$j}@nexora.test",
                        'Transfer',sprintf('%03d',$courierIndex),500000000+$courierIndex);
                    $hub = $item['hubs'][($j-1) % count($item['hubs'])];
                    $this->createCourierProfile($courier,$item['company'],$hub,'Van','NX-T-'.str_pad((string)$courierIndex,5,'0',STR_PAD_LEFT));
                }
            }
        }
    }

    private function createCourierProfile(User $person, User $company, Hub $hub, string $vehicleType, string $plate): void
    {
        CourierProfile::updateOrCreate(['user_id'=>$person->id], [
            'sorting_center_id'=>$company->id,'logistics_company_id'=>$company->id,
            'assigned_hub_id'=>$hub->id,'is_online'=>false,
            'earnings_balance'=>0,'unremitted_cod_balance'=>0,
            'vehicle_type'=>$vehicleType,'plate_number'=>$plate,
            'license_number'=>'DEMO-LIC-'.str_pad((string)$person->id,6,'0',STR_PAD_LEFT),
            'license_expiry_date'=>now()->addYears(2)->toDateString(),
        ]);
    }

    private function createBuyers(): void
    {
        $locations = [];
        // Use actual PSGC city records; unique city per buyer where dataset has enough cities.
        foreach ($this->companies as $item) {
            foreach ($item['cities'] as $city) {
                $locations[] = [$item['province'],$city];
            }
        }
        if (count($locations) < 100) throw new RuntimeException('Fewer than 100 distinct PSGC municipalities available for buyer addresses.');
        // Spread across all 22 covered provinces, then fill to 100 distinct cities.
        $selected = []; $seen=[];
        foreach ($this->companies as $item) {
            $city=$item['cities']->first();$selected[] = [$item['province'],$city];$seen[$city->code]=true;
        }
        foreach ($locations as [$province,$city]) {
            if (count($selected) >= 100) break;
            if (isset($seen[$city->code])) continue;
            $selected[]=[$province,$city]; $seen[$city->code]=true;
        }
        foreach ($selected as $i=>[$province,$city]) {
            $n=$i+1;
            $buyer=$this->createAccount('buyer',$n,
                sprintf('buyer%03d@nexora.test',$n),'Buyer',sprintf('%03d',$n),100000000+$n);
            $this->makeAddress($buyer,$province,$city,$n);
        }
    }

    private function createSellersAndProducts(): void
    {
        $manifestFile=__DIR__.'/demo_product_images.json';
        if (!is_file($manifestFile)) throw new RuntimeException('Required demo_product_images.json is missing');
        $catalog=json_decode(file_get_contents($manifestFile),true,512,JSON_THROW_ON_ERROR);
        if (count($catalog)<50) throw new RuntimeException('At least 50 catalog image entries are required');
        foreach ($catalog as $photo) {
            if (!Storage::disk('public')->exists('seed_products/'.$photo['image'])) {
                throw new RuntimeException('Missing product asset: storage/app/public/seed_products/'.$photo['image']);
            }
        }
        $byCategory=collect($catalog)->groupBy('category');
        $categories=Category::orderBy('id')->get();
        if ($categories->count()!==13) throw new RuntimeException('Expected the original Nexora 13 product categories');
        for ($i=0; $i<50; $i++) {
            $provinceItem=$this->companies[$i%5]; // CALABARZON-focused sellers
            $province=$provinceItem['province']; $cities=$provinceItem['cities'];
            $preferredHub = $provinceItem['hubs'][intdiv($i,5)%count($provinceItem['hubs'])];
            $city = $cities->firstWhere('code', $preferredHub->city_code) ?? $cities->first();
            $cat=$categories[$i%$categories->count()];
            $seller=$this->createAccount('seller',$i+1,
                sprintf('seller%03d@nexora.test',$i+1),'Seller',sprintf('%03d',$i+1),400000000+$i+1);
            $storeName="{$province->name} {$cat->name} Store ".sprintf('%02d',$i+1);
            SellerProfile::updateOrCreate(['user_id'=>$seller->id], [
                'business_name'=>$storeName,'category'=>$cat->slug,
                'description'=>"Demo {$cat->name} store in {$city->name}, {$province->name}. All items are fictional test listings.",
                'contact_phone'=>$seller->contact_number,'contact_email'=>$seller->email,
                'is_vacation_mode'=>false,
            ]);
            $this->makeAddress($seller,$province,$city,$i+1);
            $seller->allowedCategories()->syncWithoutDetaching([$cat->id]);
            $storeCategory=StoreCategory::firstOrCreate(
                ['seller_id'=>$seller->id,'slug'=>'featured-picks'],
                ['name'=>'Featured Picks','order_index'=>1,'is_visible'=>true]);
            StoreCategory::firstOrCreate(
                ['seller_id'=>$seller->id,'slug'=>'new-arrivals'],
                ['name'=>'New Arrivals','order_index'=>2,'is_visible'=>true]);
            $templates=$byCategory->get($cat->slug,collect());
            if ($templates->isEmpty()) throw new RuntimeException("No product image templates for category {$cat->slug}");
            $templateList=$templates->values();
            for ($j=1;$j<=20;$j++) {
                $p=$templateList[($i*7+$j-1)%$templateList->count()];
                $variant=$j%4;
                $name=$p['name']." — ".['Core','Plus','Compact','Premium'][$variant].' '.sprintf('%02d',$j);
                $sku=sprintf('NX-DEMO-%03d-%02d',$i+1,$j);
                $basePrice=round((float)$p['price']*(1+(($j%5)-2)*0.035),2);
                $stockA=9+(($i+$j*3)%30);$stockB=6+(($i*7+$j)%25);
                $main='/storage/seed_products/'.$p['image'];
                $second=$templateList[($i*7+$j)%$templateList->count()];
                $gallery=[$main];
                if ($second['image']!==$p['image']) $gallery[]='/storage/seed_products/'.$second['image'];
                $variants=[
                    ['name'=>'Standard','sku'=>$sku.'-STD','color'=>'Standard','size'=>'Standard','price'=>$basePrice,'stock'=>$stockA,'image'=>$main],
                    ['name'=>'Premium','sku'=>$sku.'-PRM','color'=>'Premium','size'=>'Premium','price'=>round($basePrice*1.15,2),'stock'=>$stockB,'image'=>$main],
                ];
                Product::updateOrCreate(['sku'=>$sku], [
                    'seller_id'=>$seller->id,'category_id'=>$cat->id,'store_category_id'=>$storeCategory->id,
                    'name'=>$name,'slug'=>Str::slug('nx-demo-'.$sku.'-'.$p['name']),
                    'description'=>"{$p['name']}. Sample product for Nexora Market system testing. {$storeName}.",
                    'price'=>$basePrice,'compare_price'=>round($basePrice*1.2,2),
                    'flash_sale_price'=>$j%10===0?round($basePrice*.9,2):null,
                    'flash_sale_ends_at'=>$j%10===0?now()->addDays(7):null,
                    'stock'=>$stockA+$stockB,'low_stock_threshold'=>5,
                    'weight'=>(float)$p['weight'],'dimensions'=>'18x14x12 cm',
                    'max_purchase_limit'=>8,'status'=>'active',
                    'gallery'=>$gallery,'colors'=>['Standard','Premium'],
                    'sizes'=>['Standard','Premium'],'variants_data'=>$variants,
                ]);
            }
        }
    }

    private function createAccount(string $role,int $number,string $email,string $first,string $last,int $phoneIndex): User
    {
        $birthday='199'.($number%10).'-'.str_pad((string)(($number%12)+1),2,'0',STR_PAD_LEFT).'-15';
        return User::updateOrCreate(['email'=>$email],[
            'first_name'=>$first,'last_name'=>$last,'sex'=>$number%2?'male':'female',
            'birthday'=>$birthday,'age'=>Carbon::parse($birthday)->age,
            'contact_number'=>'09'.str_pad((string)$phoneIndex,9,'0',STR_PAD_LEFT),
            'password'=>$this->passwordHash,'role'=>$role,
            'approval_status'=>'approved','account_status'=>'active','email_verified_at'=>now(),
        ]);
    }

    private function makeAddress(User $user, PsgcProvince $province,PsgcCity $city,int $number): void
    {
        $barangay=PsgcBarangay::where('city_code',$city->code)->first();
        $isVerified=(bool)$barangay;
        Address::updateOrCreate(['user_id'=>$user->id,'is_default'=>true], [
            'region_code'=>$province->region_code,
            'region_name'=>self::REGION_NAMES[$province->region_code]??$province->region_name,
            'province_code'=>$province->code,'province_name'=>$province->name,
            'municipality_code'=>$city->code,'municipality_name'=>$city->name,
            'barangay_code'=>$barangay?->code,'barangay_name'=>$barangay?->name,
            'street'=>'Demo Market Street','house_number'=>'Block '.($number+1),
            'recipient_name'=>trim("{$user->first_name} {$user->last_name}"),
            'recipient_phone'=>$user->contact_number,'postal_code'=>null,
            'is_default'=>true,'needs_manual_review'=>!$isVerified,
            'manual_review_reason'=>$isVerified?null:'Barangay not present in local PSGC reference; verify before a real delivery.',
        ]);
    }

    private function assertDataset(): void
    {
        $expected=['buyer'=>100,'seller'=>50,'logistics'=>22,'rider'=>74,'courier'=>10];
        foreach ($expected as $role=>$count) {
            $actual=User::where('role',$role)->count();
            if ($actual!==$count) throw new RuntimeException("Expected {$count} {$role} accounts, found {$actual}");
        }
        if (Product::count()!==1000) throw new RuntimeException('Expected exactly 1000 products');
        foreach (['orders','order_items','hub_transfers','transfer_manifests','transfer_manifest_items','rider_earnings','cod_collections','delivery_attempts','job_offers'] as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table) && DB::table($table)->count()!==0)
                throw new RuntimeException("{$table} is not empty; this seed must not create parcels or financial activity");
        }
        $this->command?->info('Nexora demo dataset: 100 buyers, 50 sellers, 22 logistics, 74 riders, 10 couriers, 1000 products, 0 orders/parcels.');
        $this->command?->info('Test password for these demo users: '.self::PASSWORD.' (LOCAL/TEST ONLY)');
    }
}
