<?php
declare(strict_types=1);

namespace Database\Seeders\Product;

use Database\Seeders\BaseSeeder;

/**
 * Seeds the product catalog (categories, subcategories, products)
 * for development and testing environments.
 *
 * Migrated from the legacy monolithic Seed.php.
 * Uses INSERT ... ON CONFLICT (id) DO NOTHING — fully idempotent.
 */
class CatalogSeeder extends BaseSeeder
{
    private const USER_ID = 'e165e33e-0b13-4db9-93bb-79858a78a74a';

    public function module(): string
    {
        return 'Product';
    }

    public function environments(): array
    {
        return ['development', 'testing'];
    }

    protected function seed(): void
    {
        $this->seedCategories();
    }

    private function seedCategories(): void
    {
        $insertCategory = $this->pdo->prepare("
            INSERT INTO categories (id, name, user_id)
            VALUES (:id, :name, :user_id)
            ON CONFLICT (id) DO NOTHING
        ");

        $insertSubcategory = $this->pdo->prepare("
            INSERT INTO subcategories (id, category_id, name, user_id)
            VALUES (:id, :category_id, :name, :user_id)
            ON CONFLICT (id) DO NOTHING
        ");

        $insertProduct = $this->pdo->prepare("
            INSERT INTO products (id, user_id, category_id, subcategory_id, name, unit, hsn_code, gst_rate)
            VALUES (:id, :user_id, :cat_id, :sub_id, :name, :unit, :hsn, :gst)
            ON CONFLICT (id) DO NOTHING
        ");

        $catalog = $this->catalog();

        foreach ($catalog as $cat) {
            $insertCategory->execute([
                ':id'      => $cat['id'],
                ':name'    => $cat['name'],
                ':user_id' => self::USER_ID,
            ]);
            echo "  ✅ Seeded category: {$cat['name']}\n";

            foreach ($cat['subcategories'] as $sub) {
                $insertSubcategory->execute([
                    ':id'          => $sub['id'],
                    ':category_id' => $cat['id'],
                    ':name'        => $sub['name'],
                    ':user_id'     => self::USER_ID,
                ]);
                echo "     ✅ Seeded subcategory: {$sub['name']}\n";

                foreach ($sub['products'] as $prod) {
                    $insertProduct->execute([
                        ':id'      => $prod[0],
                        ':user_id' => self::USER_ID,
                        ':cat_id'  => $cat['id'],
                        ':sub_id'  => $sub['id'],
                        ':name'    => $prod[1],
                        ':unit'    => $prod[2],
                        ':hsn'     => $prod[3],
                        ':gst'     => $prod[4],
                    ]);
                    echo "        ✅ Seeded product: {$prod[1]}\n";
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'id'   => 'a1111111-2222-3333-4444-555555555551',
                'name' => 'Apparel & Textiles',
                'subcategories' => [
                    [
                        'id'   => 'b1111111-2222-3333-4444-555555555551',
                        'name' => 'Men Shirts',
                        'products' => [
                            ['c1111111-2222-3333-4444-555555555501', 'Premium Cotton Shirt',    'pcs',   'HSN12345', 18.00],
                            ['c1111111-2222-3333-4444-555555555502', 'Oxford Button Down Shirt', 'pcs',  'HSN12346', 18.00],
                        ],
                    ],
                ],
            ],
            [
                'id'   => 'a1111111-2222-3333-4444-555555555552',
                'name' => 'Footwear',
                'subcategories' => [
                    [
                        'id'   => 'b1111111-2222-3333-4444-555555555552',
                        'name' => 'Sneakers',
                        'products' => [
                            ['c1111111-2222-3333-4444-555555555503', 'Classic White Sneakers', 'pairs', 'HSN54321', 12.00],
                        ],
                    ],
                ],
            ],
            [
                'id'   => 'a1111111-2222-3333-4444-555555555553',
                'name' => 'Electronics',
                'subcategories' => [
                    [
                        'id'   => 'b1111111-2222-3333-4444-555555555553',
                        'name' => 'Smartphones',
                        'products' => [
                            ['c1111111-2222-3333-4444-555555555504', 'Pro Phone 15', 'pcs', 'HSN98765', 18.00],
                        ],
                    ],
                ],
            ],
            [
                'id'   => 'a1111111-2222-3333-4444-555555555554',
                'name' => 'Home & Kitchen',
                'subcategories' => [
                    [
                        'id'   => 'b1111111-2222-3333-4444-555555555554',
                        'name' => 'Cookware',
                        'products' => [
                            ['c1111111-2222-3333-4444-555555555505', 'Non-Stick Frying Pan', 'pcs', 'HSN45678', 12.00],
                        ],
                    ],
                ],
            ],
            [
                'id'   => 'a1111111-2222-3333-4444-555555555555',
                'name' => 'Books & Stationery',
                'subcategories' => [
                    [
                        'id'   => 'b1111111-2222-3333-4444-555555555555',
                        'name' => 'Novels',
                        'products' => [
                            ['c1111111-2222-3333-4444-555555555506', 'The Great Adventure Novel', 'pcs', 'HSN78901', 5.00],
                        ],
                    ],
                ],
            ],
        ];
    }
}
