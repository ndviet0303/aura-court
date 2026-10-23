<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueStock;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Per-organization POS catalogs, shaped like PosService::createProduct writes them: metadata carries
 * category/unit/min_stock/supplier/tax_rate (vat_rate mirrors tax_rate for the POS screen). Every venue of the
 * organization opens with the catalog's initial stock: a venue_stocks row and an "initial_stock" movement per venue
 * (pos-venue-inventory D1). Sales and restocks are seeded by PosSalesSeeder.
 */
class PosProductSeeder extends Seeder
{
    public function run(): void
    {
        // The catalog predates the oldest seeded POS sale (about 13 months ago, see PosSalesSeeder).
        $setupAt = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->startOfDay()->subMonths(14)->setTime(8, 30);

        foreach ($this->catalogs() as $slug => [$ownerEmail, $products]) {
            $org = Organization::where('slug', $slug)->first();
            if (! $org) {
                continue;
            }
            $ownerId = User::where('email', $ownerEmail)->value('id');
            $venueIds = Venue::where('organization_id', $org->id)->orderBy('id')->pluck('id');

            foreach ($products as [$sku, $barcode, $name, $category, $unit, $price, $cost, $stock, $minStock, $supplier, $description]) {
                $taxRate = in_array($category, ['drinks', 'nutrition'], true) ? 8 : 10;
                $metadata = [
                    'category' => $category,
                    'unit' => $unit,
                    'min_stock' => $minStock,
                    'supplier' => $supplier,
                    'tax_rate' => $taxRate,
                    'vat_rate' => $taxRate / 100,
                ];
                if ($category === 'rental') {
                    // Hire items return to the shelf, so selling an hour must not consume stock.
                    $metadata += ['is_rental' => true, 'track_stock' => false];
                }

                $product = PosProduct::firstOrNew(['organization_id' => $org->id, 'sku' => $sku]);
                $isNew = ! $product->exists;
                $product->forceFill([
                    'barcode' => $barcode,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'cost' => $cost,
                    'is_active' => true,
                    'metadata' => $metadata,
                    'created_at' => $isNew ? $setupAt : $product->created_at,
                    'updated_at' => $setupAt,
                ])->save();

                foreach ($isNew && $stock > 0 ? $venueIds : [] as $venueId) {
                    (new VenueStock)->forceFill([
                        'organization_id' => $org->id,
                        'venue_id' => $venueId,
                        'product_id' => $product->id,
                        'quantity' => $stock,
                        'created_at' => $setupAt,
                        'updated_at' => $setupAt,
                    ])->save();
                    (new StockMovement)->forceFill([
                        'organization_id' => $org->id,
                        'venue_id' => $venueId,
                        'product_id' => $product->id,
                        'type' => 'in',
                        'quantity_delta' => $stock,
                        'unit_cost' => $cost,
                        'reference_type' => 'initial_stock',
                        'reference_id' => null,
                        'created_by' => $ownerId,
                        'metadata' => [
                            'note' => 'Khởi tạo tồn kho ban đầu',
                            'balance_after' => $stock,
                        ],
                        'created_at' => $setupAt,
                        'updated_at' => $setupAt,
                    ])->save();
                }
            }
        }
    }

    /**
     * [sku, barcode, name, category, unit, price, cost, initial stock per venue, min stock, supplier, description]
     *
     * @return array<string, array{0: string, 1: list<array>}>
     */
    private function catalogs(): array
    {
        return [
            'aura-badminton-club' => ['owner@demo.auracourt.vn', [
                // Cheapest item (5.400₫ with VAT): lets a presenter pay a real QR transfer at the Cầu Giấy counter.
                ['POS-TRA-DA', null, 'Trà đá (ly)', 'drinks', 'ly', 5000, 1500, 200, 30, 'Quầy pha chế tại sân', 'Trà đá pha tại quầy, uống giải nhiệt giữa hiệp.'],
                ['POS-LAVIE-500', '8935001701042', 'Nước suối LaVie 500ml', 'drinks', 'chai', 10000, 5000, 240, 48, 'NPP nước giải khát Minh Phát', 'Nước khoáng thiên nhiên, ướp lạnh tại quầy.'],
                ['POS-REVIVE-500', '8935001701028', 'Revive Chanh Muối 500ml', 'drinks', 'chai', 15000, 9500, 120, 24, 'NPP nước giải khát Minh Phát', 'Nước uống bổ sung ion, bù nước sau trận.'],
                ['POS-POCARI-500', '8935001701011', 'Pocari Sweat 500ml', 'drinks', 'chai', 18000, 12000, 72, 24, 'NPP nước giải khát Minh Phát', 'Nước bù điện giải cho buổi chơi cường độ cao.'],
                ['POS-REDBULL-250', '8935001701035', 'Nước tăng lực Red Bull 250ml', 'drinks', 'lon', 20000, 11000, 48, 24, 'NPP nước giải khát Minh Phát', 'Lon 250ml, ướp lạnh.'],
                ['POS-COCO-330', '8935001701059', 'Nước dừa Vietcoco 330ml', 'drinks', 'hộp', 25000, 15000, 48, 12, 'Công ty Vietcoco', 'Nước dừa nguyên chất, giàu kali.'],
                ['POS-CAPHE-SUADA', '8935001701158', 'Cà phê sữa đá (chai 330ml)', 'drinks', 'chai', 25000, 12000, 12, 10, 'Xưởng cà phê Phương Vy', 'Cà phê phin pha sẵn mỗi sáng, giao theo ngày.'],
                ['POS-CHUOI-GIA', null, 'Chuối già Nam Mỹ (quả)', 'nutrition', 'quả', 8000, 4000, 20, 15, 'Vựa trái cây Long Biên', 'Bổ sung năng lượng nhanh giữa hiệp.'],
                ['POS-OISHI-40', '8935001701165', 'Snack Oishi tôm cay 40g', 'nutrition', 'gói', 10000, 6000, 40, 20, 'NPP bánh kẹo Hoàng Long', 'Snack ăn nhẹ tại quầy.'],
                ['POS-PROBAR-60', '8935001701073', 'Thanh protein ngũ cốc 60g', 'nutrition', 'thanh', 40000, 26000, 30, 10, 'NPP bánh kẹo Hoàng Long', '20g protein, hỗ trợ phục hồi cơ.'],
                ['POS-GUGEL-32', '8935001701066', 'Gel năng lượng GU Energy 32g', 'nutrition', 'gói', 45000, 30000, 16, 10, 'NPP dinh dưỡng thể thao Fitlife', 'Nạp carbohydrate nhanh cho trận dài.'],
                ['POS-YONEX-AS50', '8935001701097', 'Ống cầu lông Yonex Aerosensa AS-50 (12 quả)', 'equipment', 'ống', 780000, 650000, 8, 4, 'NPP Yonex miền Bắc', 'Cầu lông lông vũ thi đấu.'],
                ['POS-VINASTAR-12', '8935001701103', 'Ống cầu lông VinaStar xanh (12 quả)', 'equipment', 'ống', 260000, 210000, 40, 10, 'Cầu lông VinaStar', 'Cầu phong trào, bền, đường bay ổn định.'],
                ['POS-YONEX-GRIP', '8935001701080', 'Quấn cán vợt Yonex AC102EX', 'equipment', 'cuộn', 40000, 25000, 120, 20, 'NPP Yonex miền Bắc', 'Quấn cán chống trơn, thấm mồ hôi.'],
                ['POS-FRANKLIN-X40', '8935001701127', 'Bóng pickleball Franklin X-40 (hộp 3 quả)', 'equipment', 'hộp', 220000, 165000, 30, 6, 'NPP Pickleball Việt', 'Bóng thi đấu ngoài trời chuẩn USAP.'],
                ['POS-WILSON-USOPEN', '8935001701110', 'Bóng tennis Wilson US Open (hộp 3 quả)', 'equipment', 'hộp', 150000, 110000, 36, 6, 'NPP Wilson Việt Nam', 'Bóng tennis mặt cứng.'],
                ['POS-WRISTBAND-YNX', '8935001701134', 'Băng chặn mồ hôi cổ tay Yonex', 'equipment', 'chiếc', 65000, 40000, 40, 8, 'NPP Yonex miền Bắc', 'Cotton co giãn, thấm hút nhanh.'],
                ['POS-SOCKS-AURA', '8935001701141', 'Tất thể thao cổ cao Aura', 'equipment', 'đôi', 50000, 25000, 60, 12, 'Xưởng dệt Phú Thịnh', 'Đệm bàn chân, chống phồng rộp.'],
                ['POS-BONGBAN-DF3', '8935001701189', 'Bóng bàn Double Fish 3 sao (hộp 3 quả)', 'equipment', 'hộp', 45000, 30000, 30, 6, 'NPP bóng bàn Thành Long', 'Bóng thi đấu 40+, nhựa ABS.'],
                ['POS-RENT-BMT', null, 'Thuê vợt cầu lông Yonex Astrox (giờ)', 'rental', 'giờ', 30000, 0, 16, 4, null, 'Vợt căng sẵn 10,5kg, đổi tại quầy.'],
                ['POS-RENT-PKB', null, 'Thuê vợt pickleball Joola (giờ)', 'rental', 'giờ', 40000, 0, 12, 4, null, 'Vợt carbon, kèm bóng tập.'],
                ['POS-RENT-TNS', null, 'Thuê vợt tennis Wilson Clash (giờ)', 'rental', 'giờ', 50000, 0, 12, 4, null, 'Vợt 100 sq.in, phù hợp người mới.'],
                ['POS-RENT-BALL', null, 'Thuê bóng đá/bóng rổ (giờ)', 'rental', 'giờ', 20000, 0, 12, 4, null, 'Bóng size 5 hoặc size 7, nhận tại quầy.'],
                ['POS-SVC-DANLUOI', null, 'Đan lưới vợt cầu lông (cước Yonex BG65)', 'services', 'lượt', 120000, 70000, 15, 5, 'Thợ đan vợt tại quầy', 'Lấy trong ngày, mức căng theo yêu cầu.'],
                ['POS-SVC-TUDO', null, 'Thuê tủ đồ cá nhân (lượt)', 'services', 'lượt', 10000, 0, 30, 5, null, 'Tủ khóa có mã, dùng trong ngày.'],
            ]],
            'hoang-gia-sports' => ['owner.hoanggia@demo.auracourt.vn', [
                ['HG-LAVIE-500', '8935001701042', 'Nước suối LaVie 500ml', 'drinks', 'chai', 10000, 5200, 120, 24, 'NPP nước giải khát Phú Mỹ', 'Nước khoáng ướp lạnh.'],
                ['HG-REVIVE-500', '8935001701028', 'Revive Chanh Muối 500ml', 'drinks', 'chai', 15000, 9800, 72, 12, 'NPP nước giải khát Phú Mỹ', 'Bù ion sau trận.'],
                ['HG-POCARI-500', '8935001701011', 'Pocari Sweat 500ml', 'drinks', 'chai', 18000, 12300, 72, 12, 'NPP nước giải khát Phú Mỹ', 'Nước bù điện giải.'],
                ['HG-CAPHE-SUADA', '8935001701158', 'Cà phê sữa đá (chai 330ml)', 'drinks', 'chai', 25000, 12000, 30, 8, 'Cà phê Ba Ngàn Quận 7', 'Pha sẵn mỗi sáng.'],
                ['HG-FRANKLIN-X40', '8935001701127', 'Bóng pickleball Franklin X-40 (hộp 3 quả)', 'equipment', 'hộp', 220000, 168000, 24, 6, 'NPP Pickleball Việt', 'Bóng thi đấu ngoài trời.'],
                ['HG-WILSON-USOPEN', '8935001701110', 'Bóng tennis Wilson US Open (hộp 3 quả)', 'equipment', 'hộp', 150000, 112000, 24, 6, 'NPP Wilson Việt Nam', 'Bóng tennis mặt cứng.'],
                ['HG-GRIP-WILSON', '8935001701172', 'Quấn cán Wilson Pro Overgrip', 'equipment', 'cuộn', 45000, 28000, 60, 12, 'NPP Wilson Việt Nam', 'Quấn cán mỏng cho vợt tennis và pickleball.'],
                ['HG-RENT-PKB', null, 'Thuê vợt pickleball (giờ)', 'rental', 'giờ', 40000, 0, 12, 4, null, 'Vợt composite, kèm bóng tập.'],
                ['HG-RENT-BALL', null, 'Thuê bóng đá sân 5 (giờ)', 'rental', 'giờ', 20000, 0, 12, 4, null, 'Bóng size 4 cho sân 5 người.'],
            ]],
        ];
    }
}
