<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCategory;
use Database\Seeders\Support\SeedImageGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Seeds 100 products (5 per seller across 20 sellers) following all
     * Seller\ProductController validation & storage rules, with real PNG
     * images on the public storage disk and full variation matrices.
     */
    public function run(): void
    {
        $catalogTemplates = $this->templatesByCategory();
        $categoryPalettes = $this->categoryPalettes();

        $sellerProfiles = SellerProfile::with('user')->orderBy('id')->get();
        $categories = Category::all()->keyBy('slug');

        $globalCounter = 0;

        foreach ($sellerProfiles as $sellerIndex => $profile) {
            $seller = $profile->user;
            if (! $seller) {
                continue;
            }

            $allowedCategories = $seller->getEffectiveSellerCategories();
            $primaryCategory = $allowedCategories->first() ?? $categories->get($profile->category);
            if (! $primaryCategory) {
                continue;
            }

            $storeCategories = StoreCategory::where('seller_id', $seller->id)->orderBy('id')->get();
            $templates = $catalogTemplates[$primaryCategory->slug] ?? $catalogTemplates['electronics'];
            $palette = $categoryPalettes[$primaryCategory->slug] ?? [[26, 86, 219], [96, 165, 250]];

            // Pick 5 products for this seller (rotating templates so names/SKUs stay unique across multiple sellers in same category)
            $sameCatOffset = ($sellerIndex % 3) * 5;
            for ($i = 0; $i < 5; $i++) {
                $globalCounter++;
                $tpl = $templates[($sameCatOffset + $i) % count($templates)];

                $suffix = $sellerIndex >= 13 || in_array($primaryCategory->slug, ['electronics', 'home-living', 'fashion', 'sports', 'beauty', 'gaming'], true)
                    ? ' ' . $this->editionSuffix($sellerIndex, $i)
                    : '';
                $name = trim($tpl['name'] . $suffix);
                $slug = $this->uniqueSlug($name);
                $baseSku = 'NX-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $primaryCategory->slug), 0, 3))
                    . '-' . str_pad((string) $globalCounter, 3, '0', STR_PAD_LEFT);

                // Generate 2 main product gallery PNG images
                $gallery = [];
                for ($g = 1; $g <= 2; $g++) {
                    $primary = $palette[0];
                    $accent = $palette[1];
                    if ($g === 2) {
                        [$primary, $accent] = [$accent, $primary];
                    }
                    $gallery[] = SeedImageGenerator::generate(
                        "products/{$slug}-{$g}.png",
                        $primary,
                        $accent,
                        $globalCounter + $g
                    );
                }

                // Build variations matrix (colors x sizes) with real variant PNG images
                $colors = $tpl['colors'];
                $sizes = $tpl['sizes'];
                $variantsData = [];
                $totalStock = 0;
                $variantPrices = [];

                foreach ($colors as $cIdx => $color) {
                    $variantColorRgb = $this->colorSwatch($color, $palette[0]);
                    $variantImage = SeedImageGenerator::generate(
                        "products/variants/{$slug}-c" . ($cIdx + 1) . '.png',
                        $variantColorRgb,
                        $palette[1],
                        $globalCounter + $cIdx
                    );

                    if (! in_array($variantImage, $gallery, true)) {
                        $gallery[] = $variantImage;
                    }

                    foreach ($sizes as $sIdx => $size) {
                        $variantPrice = round($tpl['price'] + ($sIdx * $tpl['step']), 2);
                        $variantStock = 8 + (($globalCounter + $cIdx * 3 + $sIdx * 5) % 22);
                        $totalStock += $variantStock;
                        $variantPrices[] = $variantPrice;

                        $variantsData[] = [
                            'name' => "{$color} / {$size}",
                            'sku' => $baseSku . '-C' . ($cIdx + 1) . 'S' . ($sIdx + 1),
                            'color' => $color,
                            'size' => $size,
                            'price' => $variantPrice,
                            'stock' => $variantStock,
                            'image' => $variantImage,
                        ];
                    }
                }

                $minPrice = min($variantPrices);
                $hasCompare = ($globalCounter % 2 === 1);
                $comparePrice = $hasCompare ? round($minPrice * 1.22, 2) : null;
                $isFlash = ($globalCounter % 10 === 1); // 10 flash-sale products out of 100
                $storeCategory = $storeCategories->isNotEmpty()
                    ? $storeCategories[$i % $storeCategories->count()]
                    : null;

                Product::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'seller_id' => $seller->id,
                        'category_id' => $primaryCategory->id,
                        'store_category_id' => $storeCategory?->id,
                        'name' => $name,
                        'sku' => $baseSku,
                        'description' => $tpl['description'] . " Sold and fulfilled by {$profile->business_name} with nationwide COD delivery.",
                        'price' => $minPrice,
                        'compare_price' => $comparePrice,
                        'flash_sale_price' => $isFlash ? round($minPrice * 0.78, 2) : null,
                        'flash_sale_ends_at' => $isFlash ? now()->addDays(3) : null,
                        'stock' => $totalStock,
                        'low_stock_threshold' => 10,
                        'weight' => $tpl['weight'],
                        'dimensions' => $tpl['dimensions'],
                        'max_purchase_limit' => 5,
                        'status' => 'active',
                        'gallery' => array_values($gallery),
                        'colors' => array_values($colors),
                        'sizes' => array_values($sizes),
                        'variants_data' => $variantsData,
                    ]
                );
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-" . $i++;
        }

        return $slug;
    }

    private function editionSuffix(int $sellerIndex, int $itemIndex): string
    {
        $editions = ['Pro', 'Plus', 'SE', 'Max', 'Lite', 'Studio', '2026', 'Series II', 'Edition', 'Prime'];
        return $editions[($sellerIndex + $itemIndex) % count($editions)];
    }

    private function colorSwatch(string $colorName, array $fallback): array
    {
        $lower = strtolower($colorName);
        return match (true) {
            str_contains($lower, 'black'), str_contains($lower, 'obsidian'), str_contains($lower, 'graphite'), str_contains($lower, 'charcoal') => [34, 38, 46],
            str_contains($lower, 'white'), str_contains($lower, 'ivory'), str_contains($lower, 'cream'), str_contains($lower, 'pearl') => [226, 232, 240],
            str_contains($lower, 'blue'), str_contains($lower, 'navy'), str_contains($lower, 'cobalt'), str_contains($lower, 'ocean') => [37, 99, 235],
            str_contains($lower, 'red'), str_contains($lower, 'crimson'), str_contains($lower, 'coral'), str_contains($lower, 'rose') => [220, 38, 38],
            str_contains($lower, 'green'), str_contains($lower, 'sage'), str_contains($lower, 'olive'), str_contains($lower, 'mint') => [22, 163, 74],
            str_contains($lower, 'gold'), str_contains($lower, 'amber'), str_contains($lower, 'sand'), str_contains($lower, 'oak') => [217, 119, 6],
            str_contains($lower, 'purple'), str_contains($lower, 'lavender'), str_contains($lower, 'violet') => [124, 58, 237],
            str_contains($lower, 'silver'), str_contains($lower, 'titanium'), str_contains($lower, 'steel') => [148, 163, 184],
            default => $fallback,
        };
    }

    private function categoryPalettes(): array
    {
        return [
            'electronics' => [[30, 64, 175], [59, 130, 246]],
            'fashion' => [[157, 23, 77], [244, 114, 182]],
            'home-living' => [[146, 64, 14], [245, 158, 11]],
            'beauty' => [[190, 24, 93], [251, 182, 206]],
            'sports' => [[6, 95, 70], [16, 185, 129]],
            'gaming' => [[88, 28, 135], [168, 85, 247]],
            'accessories' => [[55, 65, 81], [156, 163, 175]],
            'kids' => [[2, 132, 199], [56, 189, 248]],
            'groceries' => [[101, 163, 13], [163, 230, 53]],
            'health' => [[13, 148, 136], [45, 212, 191]],
            'automotive' => [[153, 27, 27], [239, 68, 68]],
            'pet-supplies' => [[180, 83, 9], [251, 191, 36]],
            'books-stationery' => [[67, 56, 202], [129, 140, 248]],
        ];
    }

    private function templatesByCategory(): array
    {
        return [
            'electronics' => [
                ['name' => 'PulseBuds ANC Wireless Earbuds', 'price' => 1499, 'step' => 200, 'weight' => 0.25, 'dimensions' => '10x10x5 cm', 'colors' => ['Obsidian Black', 'Pearl White', 'Cobalt Blue'], 'sizes' => ['Standard', 'Wireless Case'], 'description' => 'Active noise-cancelling Bluetooth 5.3 earbuds with 32-hour battery life and clear dual-mic calls.'],
                ['name' => 'MagCharge 65W GaN Fast Charger', 'price' => 899, 'step' => 250, 'weight' => 0.30, 'dimensions' => '12x8x4 cm', 'colors' => ['Charcoal', 'Frost White'], 'sizes' => ['65W Dual-Port', '100W Tri-Port'], 'description' => 'Compact GaN USB-C fast charger built for laptops, tablets, and flagship smartphones.'],
                ['name' => 'AeroView 1080p Smart Webcam', 'price' => 1650, 'step' => 300, 'weight' => 0.35, 'dimensions' => '14x9x6 cm', 'colors' => ['Obsidian Black', 'Space Silver'], 'sizes' => ['1080p 60fps', '2K QHD'], 'description' => 'Crisp autofocus desktop camera with built-in ring fill light and privacy shutter.'],
                ['name' => 'VoltBank 20000mAh Power Bank', 'price' => 1299, 'step' => 200, 'weight' => 0.48, 'dimensions' => '16x8x3 cm', 'colors' => ['Midnight Black', 'Navy Blue', 'Sage Green'], 'sizes' => ['10000mAh', '20000mAh'], 'description' => 'High-capacity PD 22.5W portable power bank with digital LED percentage display.'],
                ['name' => 'Clarity USB-C 7-in-1 Hub', 'price' => 980, 'step' => 220, 'weight' => 0.22, 'dimensions' => '13x5x2 cm', 'colors' => ['Space Silver', 'Graphite'], 'sizes' => ['7-in-1', '9-in-1 Ethernet'], 'description' => 'Aluminum multiport adapter featuring 4K HDMI, SD/TF card slots, and 100W PD pass-through.'],
            ],
            'fashion' => [
                ['name' => 'Breeze Linen Relaxed Shirt', 'price' => 799, 'step' => 50, 'weight' => 0.32, 'dimensions' => '30x24x3 cm', 'colors' => ['Ivory White', 'Sage Green', 'Navy Blue'], 'sizes' => ['S', 'M', 'L', 'XL'], 'description' => 'Breathable cotton-linen blend button-down tailored for tropical weather comfort.'],
                ['name' => 'Metro Chino Tapered Trousers', 'price' => 1099, 'step' => 0, 'weight' => 0.45, 'dimensions' => '34x26x4 cm', 'colors' => ['Charcoal', 'Sand Khaki', 'Olive Green'], 'sizes' => ['28', '30', '32', '34'], 'description' => 'Stretch-twill everyday chinos with reinforced stitching and clean modern taper.'],
                ['name' => 'CloudKnit Oversized Hoodie', 'price' => 1250, 'step' => 100, 'weight' => 0.65, 'dimensions' => '35x28x6 cm', 'colors' => ['Midnight Black', 'Cream', 'Lavender'], 'sizes' => ['M', 'L', 'XL'], 'description' => 'Heavyweight brushed French terry pullover hoodie with double-lined hood.'],
                ['name' => 'Solstice Pleated Midi Dress', 'price' => 1350, 'step' => 0, 'weight' => 0.38, 'dimensions' => '32x24x4 cm', 'colors' => ['Rose Pink', 'Ivory White', 'Emerald Green'], 'sizes' => ['S', 'M', 'L'], 'description' => 'Flowy crepe midi dress with waist tie and breathable inner lining.'],
                ['name' => 'Everyday Canvas Tote & Crossbody', 'price' => 680, 'step' => 120, 'weight' => 0.42, 'dimensions' => '36x30x8 cm', 'colors' => ['Cream', 'Obsidian Black', 'Olive Green'], 'sizes' => ['Medium', 'Large'], 'description' => 'Heavy 16oz washed canvas commuter bag with padded laptop sleeve and zip closure.'],
            ],
            'home-living' => [
                ['name' => 'Artisan Ceramic Pour-Over Set', 'price' => 890, 'step' => 160, 'weight' => 0.95, 'dimensions' => '22x18x16 cm', 'colors' => ['Ivory White', 'Charcoal', 'Sage Green'], 'sizes' => ['500ml', '750ml'], 'description' => 'Hand-glazed stoneware coffee dripper and server crafted for balanced morning brews.'],
                ['name' => 'Nordic Bamboo Desk Organizer', 'price' => 540, 'step' => 110, 'weight' => 0.80, 'dimensions' => '28x18x12 cm', 'colors' => ['Warm Oak', 'Charcoal'], 'sizes' => ['3-Tier', '5-Tier'], 'description' => 'Eco-friendly bamboo modular tray system for tidy desks, cosmetics, and stationery.'],
                ['name' => 'CloudRest Hotel Pillow Pair', 'price' => 950, 'step' => 180, 'weight' => 1.40, 'dimensions' => '50x35x18 cm', 'colors' => ['Pearl White', 'Light Gray'], 'sizes' => ['Standard', 'Queen'], 'description' => 'Hypoallergenic microfiber down-alternative pillows with breathable cotton percale shell.'],
                ['name' => 'Lumina Touch Bedside Lamp', 'price' => 780, 'step' => 150, 'weight' => 0.65, 'dimensions' => '16x16x24 cm', 'colors' => ['Warm Amber', 'Pearl White', 'Black'], 'sizes' => ['USB-C Rechargeable', 'Plug-in'], 'description' => 'Dimmable 3-tone LED ambient lamp with minimalist matte aluminum base.'],
                ['name' => 'Borosilicate Airtight Pantry Jars', 'price' => 620, 'step' => 140, 'weight' => 1.25, 'dimensions' => '26x20x18 cm', 'colors' => ['Clear + Bamboo', 'Smoked Gray'], 'sizes' => ['Set of 3', 'Set of 5'], 'description' => 'Heat-resistant glass food storage canisters with silicone-sealed acacia lids.'],
            ],
            'beauty' => [
                ['name' => 'DewDrop Hyaluronic Barrier Serum', 'price' => 599, 'step' => 180, 'weight' => 0.18, 'dimensions' => '12x5x5 cm', 'colors' => ['Fragrance-Free', 'Rosewater'], 'sizes' => ['30ml', '50ml'], 'description' => 'Multi-weight hyaluronic acid + niacinamide serum for plump, calm daily hydration.'],
                ['name' => 'SunShield Matte UV Fluid SPF50+', 'price' => 450, 'step' => 150, 'weight' => 0.15, 'dimensions' => '13x5x3 cm', 'colors' => ['Invisible', 'Light Tint'], 'sizes' => ['50ml', '80ml'], 'description' => 'Zero-white-cast broad-spectrum sunscreen formulated for humid climates.'],
                ['name' => 'VelvetCloud Lip & Cheek Tint', 'price' => 320, 'step' => 40, 'weight' => 0.12, 'dimensions' => '10x3x3 cm', 'colors' => ['Coral Peach', 'Rose Berry', 'Terracotta'], 'sizes' => ['Full Size', 'Duo Pack'], 'description' => 'Weightless whipped matte pigment infused with vitamin E and jojoba oil.'],
                ['name' => 'SilkTouch 10-Piece Brush Kit', 'price' => 699, 'step' => 150, 'weight' => 0.35, 'dimensions' => '22x12x5 cm', 'colors' => ['Champagne Gold', 'Obsidian Black'], 'sizes' => ['10-Piece', '14-Piece + Pouch'], 'description' => 'Ultra-soft vegan taklon makeup brushes with ergonomic weighted handles.'],
                ['name' => 'Botanical Scalp & Hair Elixir', 'price' => 490, 'step' => 160, 'weight' => 0.25, 'dimensions' => '15x6x6 cm', 'colors' => ['Rosemary Mint', 'Argan Silk'], 'sizes' => ['60ml', '120ml'], 'description' => 'Lightweight cold-pressed botanical oil that tames frizz and nourishes roots.'],
            ],
            'sports' => [
                ['name' => 'AeroMax Elite Running Shoe', 'price' => 1490, 'step' => 0, 'weight' => 0.78, 'dimensions' => '32x22x12 cm', 'colors' => ['Midnight Black', 'Cloud White', 'Emerald Green'], 'sizes' => ['US 8', 'US 9', 'US 10'], 'description' => 'Lightweight daily road trainer with responsive EVA midsole and knit upper.'],
                ['name' => 'FlexGrip 6mm TPE Yoga Mat', 'price' => 650, 'step' => 150, 'weight' => 1.15, 'dimensions' => '62x15x15 cm', 'colors' => ['Sage Green', 'Ocean Blue', 'Charcoal'], 'sizes' => ['6mm Standard', '8mm Extra Cushion'], 'description' => 'Dual-layer non-slip exercise mat with alignment lines and carry strap.'],
                ['name' => 'HydroFlask Insulated Sport Bottle', 'price' => 580, 'step' => 140, 'weight' => 0.45, 'dimensions' => '27x9x9 cm', 'colors' => ['Obsidian Black', 'Cobalt Blue', 'Crimson Red'], 'sizes' => ['750ml', '1000ml'], 'description' => 'Double-wall 18/8 stainless steel flask keeping drinks ice-cold for 24 hours.'],
                ['name' => 'ProResistance Fabric Booty Bands', 'price' => 420, 'step' => 100, 'weight' => 0.35, 'dimensions' => '20x15x6 cm', 'colors' => ['Charcoal', 'Rose Pink'], 'sizes' => ['3-Band Set', '5-Band Set'], 'description' => 'Non-roll woven cotton-latex resistance bands for strength and mobility workouts.'],
                ['name' => 'PacePro Weighted Jump Rope', 'price' => 380, 'step' => 90, 'weight' => 0.30, 'dimensions' => '22x12x5 cm', 'colors' => ['Black / Red', 'Steel Blue'], 'sizes' => ['Standard Cable', 'Weighted 300g'], 'description' => 'Ball-bearing speed jump rope with anti-slip aluminum handles for HIIT cardio.'],
            ],
            'gaming' => [
                ['name' => 'Nimbus Mechanical Keyboard', 'price' => 2190, 'step' => 300, 'weight' => 0.92, 'dimensions' => '36x15x5 cm', 'colors' => ['Obsidian Black', 'Pearl White', 'Violet'], 'sizes' => ['Red Switch', 'Brown Switch'], 'description' => 'Hot-swappable 75% gasket-mounted mechanical keyboard with PBT keycaps and RGB.'],
                ['name' => 'ViperLite Wireless Gaming Mouse', 'price' => 1450, 'step' => 250, 'weight' => 0.22, 'dimensions' => '15x10x6 cm', 'colors' => ['Obsidian Black', 'Cloud White'], 'sizes' => ['58g Standard', 'With Charging Dock'], 'description' => 'Tri-mode 2.4GHz/BT/USB-C esports mouse with PAW3395 26K DPI sensor.'],
                ['name' => 'Aegis 7.1 Surround Headset', 'price' => 1590, 'step' => 200, 'weight' => 0.55, 'dimensions' => '22x20x10 cm', 'colors' => ['Midnight Black', 'Cobalt Blue'], 'sizes' => ['Wired USB', '2.4GHz Wireless'], 'description' => '50mm neodymium drivers with memory-foam earcups and detachable boom mic.'],
                ['name' => 'Stealth XXL Desk Mousepad', 'price' => 490, 'step' => 120, 'weight' => 0.60, 'dimensions' => '42x10x10 cm', 'colors' => ['Charcoal Topo', 'Violet Nebula'], 'sizes' => ['800x300mm', '900x400mm'], 'description' => 'Micro-woven speed-control gaming surface with stitched anti-fray edges.'],
                ['name' => 'Nova Pro Hall-Effect Controller', 'price' => 1690, 'step' => 200, 'weight' => 0.38, 'dimensions' => '18x14x8 cm', 'colors' => ['Obsidian Black', 'Pearl White'], 'sizes' => ['PC/Mobile', 'Multi-Platform + Dock'], 'description' => 'Zero-drift Hall-effect thumbsticks and triggers with remappable back paddles.'],
            ],
            'accessories' => [
                ['name' => 'Chrono Minimalist Quartz Watch', 'price' => 1490, 'step' => 250, 'weight' => 0.25, 'dimensions' => '12x10x7 cm', 'colors' => ['Silver / Navy', 'Gold / Black'], 'sizes' => ['38mm', '42mm'], 'description' => 'Sapphire-coated mineral glass timepiece with Japanese quartz movement and 5ATM rating.'],
                ['name' => 'RFID Slim Leather Bifold Wallet', 'price' => 690, 'step' => 100, 'weight' => 0.18, 'dimensions' => '13x11x3 cm', 'colors' => ['Espresso Brown', 'Obsidian Black'], 'sizes' => ['Classic Bifold', 'Money Clip'], 'description' => 'Full-grain cowhide slim wallet with RFID blocking lining and 8 card slots.'],
                ['name' => 'AeroPolarized Classic Sunglasses', 'price' => 590, 'step' => 120, 'weight' => 0.16, 'dimensions' => '17x7x5 cm', 'colors' => ['Matte Black', 'Tortoise Gold'], 'sizes' => ['Standard Fit', 'Wide Fit'], 'description' => 'UV400 TAC polarized lenses in lightweight TR90 frame with hard travel case.'],
                ['name' => 'UrbanShield Anti-Theft Backpack', 'price' => 1390, 'step' => 200, 'weight' => 0.78, 'dimensions' => '45x31x15 cm', 'colors' => ['Charcoal', 'Navy Blue'], 'sizes' => ['15.6" Laptop', '17" Travel'], 'description' => 'Water-repellent commuter pack with hidden rear pocket and luggage strap.'],
                ['name' => 'Braided Nylon Magnetic Watch Band', 'price' => 350, 'step' => 50, 'weight' => 0.12, 'dimensions' => '18x6x2 cm', 'colors' => ['Olive Green', 'Midnight Black', 'Cobalt Blue'], 'sizes' => ['40/41mm', '44/45mm'], 'description' => 'Breathable stretch-weave smartwatch strap with micro-adjustable clasp.'],
            ],
            'kids' => [
                ['name' => 'Sprout Organic Cotton Pajama Set', 'price' => 480, 'step' => 40, 'weight' => 0.25, 'dimensions' => '26x20x4 cm', 'colors' => ['Sky Blue', 'Mint Green', 'Soft Pink'], 'sizes' => ['2-3Y', '4-5Y', '6-7Y'], 'description' => 'Ultra-soft combed organic cotton 2-piece sleepwear gentle on sensitive skin.'],
                ['name' => 'Montessori Wooden Building Blocks', 'price' => 750, 'step' => 200, 'weight' => 1.20, 'dimensions' => '28x22x8 cm', 'colors' => ['Natural Wood', 'Pastel Mix'], 'sizes' => ['50 Pieces', '100 Pieces'], 'description' => 'Smooth-sanded beechwood blocks with non-toxic water-based finish for creative play.'],
                ['name' => 'Junior Insulated Bento Lunchbox', 'price' => 520, 'step' => 110, 'weight' => 0.45, 'dimensions' => '22x16x8 cm', 'colors' => ['Ocean Blue', 'Coral Pink'], 'sizes' => ['4-Compartment', '6-Compartment'], 'description' => 'BPA-free leakproof school bento box with removable stainless tray.'],
                ['name' => 'CloudStep Kids Velcro Sneakers', 'price' => 890, 'step' => 60, 'weight' => 0.48, 'dimensions' => '24x18x10 cm', 'colors' => ['Cloud White', 'Navy Blue'], 'sizes' => ['EU 26', 'EU 28', 'EU 30'], 'description' => 'Lightweight breathable mesh play shoes with easy dual hook-and-loop straps.'],
                ['name' => 'AquaFun Washable Tempera Paint Kit', 'price' => 390, 'step' => 100, 'weight' => 0.55, 'dimensions' => '25x18x6 cm', 'colors' => ['12 Colors', '24 Colors'], 'sizes' => ['Starter Kit', 'Deluxe + Easel'], 'description' => 'Kid-safe washable art paints with chunky brushes and spill-proof cups.'],
            ],
            'groceries' => [
                ['name' => 'Cordillera Single-Origin Arabica', 'price' => 390, 'step' => 220, 'weight' => 0.50, 'dimensions' => '22x14x8 cm', 'colors' => ['Whole Bean', 'Medium Grind'], 'sizes' => ['250g', '500g'], 'description' => 'Freshly roasted Benguet highland Arabica coffee with caramel and cocoa notes.'],
                ['name' => 'Raw Wildflower Mountain Honey', 'price' => 360, 'step' => 240, 'weight' => 0.70, 'dimensions' => '18x9x9 cm', 'colors' => ['Amber Gold'], 'sizes' => ['350g Jar', '700g Jar'], 'description' => '100% pure unfiltered Philippine forest honey harvested sustainably.'],
                ['name' => 'Artisanal Dark Cacao Tablea', 'price' => 280, 'step' => 160, 'weight' => 0.40, 'dimensions' => '16x12x6 cm', 'colors' => ['Unsweetened 100%', 'Muscovado Sweetened'], 'sizes' => ['250g', '500g'], 'description' => 'Stone-ground Davao heirloom cacao discs for rich sikwate and baking.'],
                ['name' => 'Cold-Pressed Extra Virgin Coconut Oil', 'price' => 310, 'step' => 180, 'weight' => 0.60, 'dimensions' => '20x8x8 cm', 'colors' => ['Organic VCO'], 'sizes' => ['250ml', '500ml'], 'description' => 'Centrifuge-extracted unrefined coconut oil for cooking, smoothies, and skincare.'],
                ['name' => 'Toasted Rolled Oats & Chia Mix', 'price' => 295, 'step' => 150, 'weight' => 0.85, 'dimensions' => '24x16x8 cm', 'colors' => ['Original', 'Cinnamon Honey'], 'sizes' => ['500g', '1kg'], 'description' => 'High-fiber wholegrain breakfast blend ready in 3 minutes or overnight.'],
            ],
            'health' => [
                ['name' => 'VitalPulse Digital Blood Pressure Monitor', 'price' => 1290, 'step' => 200, 'weight' => 0.52, 'dimensions' => '16x12x10 cm', 'colors' => ['Pearl White', 'Slate Gray'], 'sizes' => ['Standard Cuff', 'Wide Cuff + Adapter'], 'description' => 'Clinically accurate upper-arm BP monitor with 90-reading dual-user memory.'],
                ['name' => 'ThermoSense Non-Contact Thermometer', 'price' => 690, 'step' => 100, 'weight' => 0.22, 'dimensions' => '16x9x5 cm', 'colors' => ['Pearl White'], 'sizes' => ['Standard', 'With Hard Case'], 'description' => '1-second infrared forehead thermometer with fever color backlight alert.'],
                ['name' => 'PureWhey Isolate Daily Protein', 'price' => 1450, 'step' => 950, 'weight' => 1.10, 'dimensions' => '26x16x16 cm', 'colors' => ['Dutch Chocolate', 'Vanilla Bean'], 'sizes' => ['1 lb', '2 lbs'], 'description' => '25g clean grass-fed whey protein per serving with zero added sugar.'],
                ['name' => 'ErgoSpine Memory Foam Lumbar Cushion', 'price' => 780, 'step' => 120, 'weight' => 0.75, 'dimensions' => '38x34x12 cm', 'colors' => ['Charcoal', 'Navy Blue'], 'sizes' => ['Standard', 'Tall Back'], 'description' => 'Contoured high-density memory foam back support with washable mesh cover.'],
                ['name' => 'DeepRelax Percussion Massage Gun', 'price' => 1590, 'step' => 300, 'weight' => 0.95, 'dimensions' => '24x18x8 cm', 'colors' => ['Obsidian Black', 'Space Silver'], 'sizes' => ['Mini Travel', 'Pro 6-Head'], 'description' => 'Quiet brushless deep-tissue muscle massager with 6 speed levels and USB-C charging.'],
            ],
            'automotive' => [
                ['name' => 'RoadEye 2K Dashcam with Night Vision', 'price' => 2190, 'step' => 400, 'weight' => 0.42, 'dimensions' => '16x12x7 cm', 'colors' => ['Matte Black'], 'sizes' => ['Front 2K', 'Front + Rear Kit'], 'description' => '140-degree wide-angle dash camera with G-sensor loop recording and Wi-Fi app.'],
                ['name' => 'TurboAir Cordless Tire Inflator', 'price' => 1390, 'step' => 250, 'weight' => 0.68, 'dimensions' => '18x10x7 cm', 'colors' => ['Obsidian Black', 'Crimson Red'], 'sizes' => ['150 PSI Standard', 'Pro + Power Bank'], 'description' => 'Digital auto-stop portable air compressor for cars, motorcycles, and bicycles.'],
                ['name' => 'NanoShield Ceramic Spray Coating', 'price' => 540, 'step' => 180, 'weight' => 0.58, 'dimensions' => '22x9x9 cm', 'colors' => ['SiO2 Gloss'], 'sizes' => ['250ml', '500ml + Towel'], 'description' => 'Hydrophobic SiO2 quick-coat sealant delivering 6-month mirror shine and UV protection.'],
                ['name' => 'MotoGuard Waterproof Phone Mount', 'price' => 480, 'step' => 100, 'weight' => 0.28, 'dimensions' => '18x11x6 cm', 'colors' => ['Obsidian Black'], 'sizes' => ['Handlebar', 'Mirror Mount'], 'description' => 'Vibration-dampening 360-degree aluminum motorcycle & bike phone holder.'],
                ['name' => 'Cyclone 12V Car Vacuum Cleaner', 'price' => 890, 'step' => 200, 'weight' => 0.85, 'dimensions' => '34x12x12 cm', 'colors' => ['Black / Silver', 'Black / Red'], 'sizes' => ['Corded 12V', 'Cordless HEPA'], 'description' => '8000Pa high-suction handheld auto vacuum with crevice nozzle and brush tools.'],
            ],
            'pet-supplies' => [
                ['name' => 'CloudPaws Washable Donut Pet Bed', 'price' => 850, 'step' => 250, 'weight' => 1.15, 'dimensions' => '45x45x18 cm', 'colors' => ['Warm Gray', 'Sand Beige'], 'sizes' => ['Medium (60cm)', 'Large (80cm)'], 'description' => 'Plush calming bolster pet bed with non-skid waterproof bottom and removable cover.'],
                ['name' => 'PureFlow 2.5L Automatic Pet Fountain', 'price' => 980, 'step' => 180, 'weight' => 0.85, 'dimensions' => '22x22x16 cm', 'colors' => ['Pearl White', 'Sage Green'], 'sizes' => ['Standard', 'With 4 Extra Filters'], 'description' => 'Whisper-quiet circulating water dispenser with triple-layer activated carbon filter.'],
                ['name' => 'EasyWalk No-Pull Reflective Harness', 'price' => 490, 'step' => 80, 'weight' => 0.28, 'dimensions' => '24x18x4 cm', 'colors' => ['Cobalt Blue', 'Crimson Red', 'Obsidian Black'], 'sizes' => ['S', 'M', 'L'], 'description' => 'Breathable padded chest harness with front/back D-rings and 3M reflective trim.'],
                ['name' => 'GroomPro Deshedding & Nail Kit', 'price' => 420, 'step' => 100, 'weight' => 0.32, 'dimensions' => '22x14x5 cm', 'colors' => ['Mint Green', 'Charcoal'], 'sizes' => ['Cat & Small Dog', 'Large Breed'], 'description' => 'Self-cleaning slicker brush and safety-stop nail clipper for stress-free grooming.'],
                ['name' => 'AeroPod Breathable Pet Carrier', 'price' => 1290, 'step' => 200, 'weight' => 1.35, 'dimensions' => '44x28x28 cm', 'colors' => ['Light Gray', 'Navy Blue'], 'sizes' => ['Up to 6kg', 'Up to 10kg'], 'description' => '3-sided mesh ventilated travel carrier with washable fleece pad and shoulder strap.'],
            ],
            'books-stationery' => [
                ['name' => 'Archival Dotted Hardcover Journal', 'price' => 380, 'step' => 80, 'weight' => 0.42, 'dimensions' => '22x15x3 cm', 'colors' => ['Forest Green', 'Navy Blue', 'Obsidian Black'], 'sizes' => ['A5 120gsm', 'B5 120gsm'], 'description' => 'Lay-flat stitched notebook with 192 bleed-proof ivory pages and dual ribbon markers.'],
                ['name' => 'Precision Dual-Tip Brush Pen Set', 'price' => 450, 'step' => 200, 'weight' => 0.35, 'dimensions' => '20x16x4 cm', 'colors' => ['Pastel Palette', 'Classic Bold'], 'sizes' => ['24 Colors', '48 Colors'], 'description' => 'Water-based flexible brush + 0.4mm fineliner markers for journaling and illustration.'],
                ['name' => 'ErgoTilt Aluminum Laptop & Book Stand', 'price' => 690, 'step' => 150, 'weight' => 0.58, 'dimensions' => '26x22x4 cm', 'colors' => ['Space Silver', 'Graphite'], 'sizes' => ['Foldable Portable', 'Swivel Desk'], 'description' => '6-angle adjustable anodized aluminum stand with non-slip silicone pads.'],
                ['name' => 'Classic Brass Fountain Pen Set', 'price' => 790, 'step' => 160, 'weight' => 0.25, 'dimensions' => '18x8x3 cm', 'colors' => ['Matte Black', 'Brushed Brass'], 'sizes' => ['Fine Nib', 'Medium Nib'], 'description' => 'Balanced metal fountain pen with converter and 6 archival black ink cartridges.'],
                ['name' => 'Weekly Desk Planner & Sticky Pad', 'price' => 320, 'step' => 90, 'weight' => 0.45, 'dimensions' => '30x21x2 cm', 'colors' => ['Minimalist White', 'Sage Grid'], 'sizes' => ['52-Sheet Pad', '2-Pack Bundle'], 'description' => 'Undated A4 productivity desk pad with priority blocks and habit tracker.'],
            ],
        ];
    }
}
