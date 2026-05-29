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

    // 3. Insert Test Category
    $categoryId = 'a1111111-2222-3333-4444-555555555555';
    $insertCategory = $pdo->prepare("
        INSERT INTO categories (id, name, user_id) 
        VALUES (?, ?, ?)
    ");
    $insertCategory->execute([$categoryId, 'Apparel & Textiles', $userId]);
    echo "✅ Seeded category: Apparel & Textiles\n";

    // 4. Insert Test Subcategory
    $subcategoryId = 'b1111111-2222-3333-4444-555555555555';
    $insertSubcategory = $pdo->prepare("
        INSERT INTO subcategories (id, category_id, name, user_id) 
        VALUES (?, ?, ?, ?)
    ");
    $insertSubcategory->execute([$subcategoryId, $categoryId, 'Men Shirts', $userId]);
    echo "✅ Seeded subcategory: Men Shirts\n";

    // 5. Insert Test Product
    $productId = 'c1111111-2222-3333-4444-555555555555';
    $insertProduct = $pdo->prepare("
        INSERT INTO products (id, user_id, category_id, subcategory_id, name, unit, hsn_code, gst_rate) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertProduct->execute([
        $productId, 
        $userId, 
        $categoryId, 
        $subcategoryId, 
        'Premium Cotton Shirt', 
        'pcs', 
        'HSN12345', 
        18.00
    ]);
    echo "✅ Seeded product: Premium Cotton Shirt\n";
    echo "\nAll test seed data successfully populated!\n";

} catch (Exception $e) {
    echo "❌ Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
