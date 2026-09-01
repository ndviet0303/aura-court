<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\PosProduct;
use Illuminate\Database\Seeder;

class PosProductSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'aura-badminton-club')->first() ?? Organization::first();
        if (! $org) {
            return;
        }

        $products = [
            [
                'sku' => 'POS-POCARI-500',
                'barcode' => '8935001701011',
                'name' => 'Nước bù khoáng Pocari Sweat 500ml',
                'description' => 'Bù điện giải và ion thiết yếu nhanh chóng khi vận động cường độ cao.',
                'price' => 15000,
                'cost' => 10000,
                'stock_quantity' => 120,
            ],
            [
                'sku' => 'POS-REVIVE-500',
                'barcode' => '8935001701028',
                'name' => 'Nước ngọt thể thao Revive Chanh Muối 500ml',
                'description' => 'Cung cấp năng lượng tức thì và giải tỏa cơn khát.',
                'price' => 15000,
                'cost' => 9500,
                'stock_quantity' => 95,
            ],
            [
                'sku' => 'POS-REDBULL-250',
                'barcode' => '8935001701035',
                'name' => 'Nước tăng lực Red Bull Thái Lan 250ml',
                'description' => 'Bật năng lượng, bứt phá giới hạn trong từng séc đấu.',
                'price' => 20000,
                'cost' => 13500,
                'stock_quantity' => 80,
            ],
            [
                'sku' => 'POS-LAVIE-500',
                'barcode' => '8935001701042',
                'name' => 'Nước khoáng thiên nhiên LaVie 500ml',
                'description' => 'Nước khoáng thanh khiết nguyên bản từ thiên nhiên.',
                'price' => 10000,
                'cost' => 5000,
                'stock_quantity' => 200,
            ],
            [
                'sku' => 'POS-COCO-330',
                'barcode' => '8935001701059',
                'name' => 'Nước dừa tươi nguyên chất Vietcoco 330ml',
                'description' => 'Nước dừa tươi 100% giàu kali và chất điện giải tự nhiên.',
                'price' => 25000,
                'cost' => 16000,
                'stock_quantity' => 60,
            ],
            [
                'sku' => 'POS-GUGEL-32',
                'barcode' => '8935001701066',
                'name' => 'Gel năng lượng GU Energy Gel (Vị Cam/Dâu)',
                'description' => 'Nạp carbohydrate và chất điện giải tức thì cho VĐV.',
                'price' => 45000,
                'cost' => 30000,
                'stock_quantity' => 45,
            ],
            [
                'sku' => 'POS-PROBAR-60',
                'barcode' => '8935001701073',
                'name' => 'Thanh bánh ngũ cốc Protein Bar 60g',
                'description' => 'Cung cấp 20g Protein hỗ trợ phục hồi cơ bắp.',
                'price' => 40000,
                'cost' => 26000,
                'stock_quantity' => 50,
            ],
            [
                'sku' => 'POS-YONEX-GRIP',
                'barcode' => '8935001701080',
                'name' => 'Quấn cán vợt Yonex Super Grap AC102EX',
                'description' => 'Chống trơn trượt thấm hút mồ hôi số 1 thế giới.',
                'price' => 35000,
                'cost' => 22000,
                'stock_quantity' => 150,
            ],
            [
                'sku' => 'POS-YONEX-AS50',
                'barcode' => '8935001701097',
                'name' => 'Ống cầu lông Yonex Aerosensa AS-50 (12 quả)',
                'description' => 'Cầu lông thi đấu quốc tế cao cấp nhất của Yonex.',
                'price' => 780000,
                'cost' => 650000,
                'stock_quantity' => 30,
            ],
            [
                'sku' => 'POS-VINASTAR-BLUE',
                'barcode' => '8935001701103',
                'name' => 'Ống cầu lông VinaStar Xanh (12 quả)',
                'description' => 'Cầu lông phong trào bền bỉ, đường bay đầm tay và chuẩn xác.',
                'price' => 260000,
                'cost' => 210000,
                'stock_quantity' => 85,
            ],
            [
                'sku' => 'POS-WILSON-USOPEN',
                'barcode' => '8935001701110',
                'name' => 'Hộp bóng Tennis Wilson US Open (3 quả)',
                'description' => 'Bóng tennis chính thức của giải US Open Grand Slam.',
                'price' => 140000,
                'cost' => 105000,
                'stock_quantity' => 40,
            ],
            [
                'sku' => 'POS-FRANKLIN-X40',
                'barcode' => '8935001701127',
                'name' => 'Hộp bóng Pickleball Franklin X-40 Outdoor (3 quả)',
                'description' => 'Bóng thi đấu pickleball ngoài trời bền bỉ được USAP chứng nhận.',
                'price' => 160000,
                'cost' => 115000,
                'stock_quantity' => 50,
            ],
            [
                'sku' => 'POS-WRISTBAND-YNX',
                'barcode' => '8935001701134',
                'name' => 'Băng đeo cổ tay thấm mồ hôi Yonex',
                'description' => 'Chất liệu cotton co giãn cao cấp, thấm hút mồ hôi cực nhanh.',
                'price' => 65000,
                'cost' => 40000,
                'stock_quantity' => 70,
            ],
            [
                'sku' => 'POS-SOCKS-AURA',
                'barcode' => '8935001701141',
                'name' => 'Tất thể thao dệt cổ cao Aura Elite Cushion',
                'description' => 'Đệm bàn chân êm ái, chống phồng rộp và khử mùi kháng khuẩn.',
                'price' => 50000,
                'cost' => 25000,
                'stock_quantity' => 100,
            ],
        ];

        foreach ($products as $prod) {
            PosProduct::updateOrCreate(
                ['sku' => $prod['sku']],
                [
                    'organization_id' => $org->id,
                    'barcode' => $prod['barcode'],
                    'name' => $prod['name'],
                    'description' => $prod['description'],
                    'price' => $prod['price'],
                    'cost' => $prod['cost'],
                    'stock_quantity' => $prod['stock_quantity'],
                    'is_active' => true,
                    'metadata' => ['category' => 'Retail', 'brand' => 'Aura Pro'],
                ]
            );
        }
    }
}
