<?php

require_once __DIR__ . '/../config/Database.php';

try {
    $pdo = \Config\Database::getConnection();
} catch (Exception $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Seeding database with test user and product catalog...\n";

// Seed user details
$userId = 'e165e33e-0b13-4db9-93bb-79858a78a74a';
$username = 'testuser';
$email = 'testuser@example.com';
$password = 'password123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$fullName = 'Test User';

try {
    // 1. Clean existing test data
    $pdo->exec("TRUNCATE TABLE users, categories, subcategories, products CASCADE");

    // 2. Insert Test User
    $insertUser = $pdo->prepare("
        INSERT INTO users (id, username, email, password_hash, full_name) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertUser->execute([$userId, $username, $email, $hashedPassword, $fullName]);
    echo "✅ Seeded user: $username (Password: $password)\n";

    // 3. Insert Test Categories, Subcategories, and Products
    $categoriesToSeed = [
        [
            'id' => 'a1111111-2222-3333-4444-555555555551',
            'name' => 'Apparel & Textiles',
            'subcategories' => [
                [
                    'id' => 'b1111111-2222-3333-4444-555555555551',
                    'name' => 'Men Shirts',
                    'products' => [
                        ['c1111111-2222-3333-4444-555555555501', 'Premium Cotton Shirt', 'pcs', 'HSN12345', 18.00],
                        ['c1111111-2222-3333-4444-555555555502', 'Oxford Button Down Shirt', 'pcs', 'HSN12346', 18.00]
                    ]
                ]
            ]
        ],
        [
            'id' => 'a1111111-2222-3333-4444-555555555552',
            'name' => 'Footwear',
            'subcategories' => [
                [
                    'id' => 'b1111111-2222-3333-4444-555555555552',
                    'name' => 'Sneakers',
                    'products' => [
                        ['c1111111-2222-3333-4444-555555555503', 'Classic White Sneakers', 'pairs', 'HSN54321', 12.00]
                    ]
                ]
            ]
        ],
        [
            'id' => 'a1111111-2222-3333-4444-555555555553',
            'name' => 'Electronics',
            'subcategories' => [
                [
                    'id' => 'b1111111-2222-3333-4444-555555555553',
                    'name' => 'Smartphones',
                    'products' => [
                        ['c1111111-2222-3333-4444-555555555504', 'Pro Phone 15', 'pcs', 'HSN98765', 18.00]
                    ]
                ]
            ]
        ],
        [
            'id' => 'a1111111-2222-3333-4444-555555555554',
            'name' => 'Home & Kitchen',
            'subcategories' => [
                [
                    'id' => 'b1111111-2222-3333-4444-555555555554',
                    'name' => 'Cookware',
                    'products' => [
                        ['c1111111-2222-3333-4444-555555555505', 'Non-Stick Frying Pan', 'pcs', 'HSN45678', 12.00]
                    ]
                ]
            ]
        ],
        [
            'id' => 'a1111111-2222-3333-4444-555555555555',
            'name' => 'Books & Stationery',
            'subcategories' => [
                [
                    'id' => 'b1111111-2222-3333-4444-555555555555',
                    'name' => 'Novels',
                    'products' => [
                        ['c1111111-2222-3333-4444-555555555506', 'The Great Adventure Novel', 'pcs', 'HSN78901', 5.00]
                    ]
                ]
            ]
        ]
    ];

    $insertCategory = $pdo->prepare("
        INSERT INTO categories (id, name, user_id) 
        VALUES (?, ?, ?)
    ");
    $insertSubcategory = $pdo->prepare("
        INSERT INTO subcategories (id, category_id, name, user_id) 
        VALUES (?, ?, ?, ?)
    ");
    $insertProduct = $pdo->prepare("
        INSERT INTO products (id, user_id, category_id, subcategory_id, name, unit, hsn_code, gst_rate) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($categoriesToSeed as $cat) {
        $insertCategory->execute([$cat['id'], $cat['name'], $userId]);
        echo "✅ Seeded category: {$cat['name']}\n";
        
        foreach ($cat['subcategories'] as $sub) {
            $insertSubcategory->execute([$sub['id'], $cat['id'], $sub['name'], $userId]);
            echo "   ✅ Seeded subcategory: {$sub['name']}\n";
            
            foreach ($sub['products'] as $prod) {
                $insertProduct->execute([
                    $prod[0],
                    $userId,
                    $cat['id'],
                    $sub['id'],
                    $prod[1],
                    $prod[2],
                    $prod[3],
                    $prod[4]
                ]);
                echo "      ✅ Seeded product: {$prod[1]}\n";
            }
        }
    }
    echo "\nAll test seed data successfully populated!\n";

} catch (Exception $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
