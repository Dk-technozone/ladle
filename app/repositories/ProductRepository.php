<?php

function product_query(string $mode = 'featured', array $filters = []): array
{
    $params = [];
    $where = ['products.status = "published"', 'products.approval_status = "approved"'];
    if (!empty($filters['category'])) {
        $where[] = 'categories.slug = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(products.name LIKE ? OR products.short_description LIKE ? OR products.tags LIKE ?)';
        $term = '%' . $filters['q'] . '%';
        array_push($params, $term, $term, $term);
    }
    if (!empty($filters['vendor_id'])) {
        $where[] = 'products.vendor_id = ?';
        $params[] = (int) $filters['vendor_id'];
    }
    $order = match ($mode) {
        'trending' => 'products.trending_score DESC, products.total_views DESC',
        'latest' => 'products.created_at DESC',
        default => 'products.trending_score DESC, products.rating_average DESC',
    };
    $limit = (int) ($filters['limit'] ?? 24);
    $sql = 'SELECT products.*, categories.name AS category_name, categories.slug AS category_slug, vendors.store_name, vendors.verified FROM products LEFT JOIN categories ON categories.id = products.category_id LEFT JOIN vendors ON vendors.id = products.vendor_id WHERE ' . implode(' AND ', $where) . " ORDER BY {$order} LIMIT {$limit}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT products.*, categories.name AS category_name, categories.slug AS category_slug, vendors.store_name, vendors.slug AS vendor_slug, vendors.verified, vendors.headline FROM products LEFT JOIN categories ON categories.id = products.category_id LEFT JOIN vendors ON vendors.id = products.vendor_id WHERE products.slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    if (!$product) {
        return null;
    }
    db()->prepare('UPDATE products SET total_views = total_views + 1, trending_score = trending_score + 0.5 WHERE id = ?')->execute([(int) $product['id']]);
    return $product;
}

function product_features(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_features WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function product_faqs(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_faqs WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function product_changelog(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_changelog WHERE product_id = ? ORDER BY released_at DESC, id DESC');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function categories_with_counts(): array
{
    return db()->query('SELECT categories.*, COUNT(products.id) AS product_count FROM categories LEFT JOIN products ON products.category_id = categories.id AND products.status = "published" GROUP BY categories.id ORDER BY categories.sort_order, categories.name')->fetchAll();
}

function unique_slug(string $table, string $slug, ?int $ignoreId = null): string
{
    $base = slugify($slug);
    $candidate = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = ?";
        $params = [$candidate];
        if ($ignoreId) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $i++;
    }
}

function product_payload_from_post(?array $existing = null, ?int $vendorId = null): array
{
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        throw new RuntimeException('Product name is required.');
    }
    $slugInput = $_POST['slug'] ?? '';
    $slug = unique_slug('products', $slugInput ?: $name, $existing['id'] ?? null);
    $postedVendorId = $_POST['vendor_id'] ?? '';
    return [
        ':vendor_id' => $vendorId ?: ($postedVendorId !== '' ? (int) $postedVendorId : null),
        ':category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
        ':name' => $name,
        ':slug' => $slug,
        ':short_description' => trim($_POST['short_description'] ?? ''),
        ':full_description' => trim($_POST['full_description'] ?? ''),
        ':product_type' => trim($_POST['product_type'] ?? 'Theme'),
        ':tags' => trim($_POST['tags'] ?? ''),
        ':version' => trim($_POST['version'] ?? '1.0.0'),
        ':release_date' => trim($_POST['release_date'] ?? date('Y-m-d')),
        ':last_updated' => trim($_POST['last_updated'] ?? date('Y-m-d')),
        ':status' => trim($_POST['status'] ?? 'draft'),
        ':approval_status' => trim($_POST['approval_status'] ?? 'pending'),
        ':regular_price' => (float) ($_POST['regular_price'] ?? 0),
        ':sale_price' => $_POST['sale_price'] !== '' ? (float) $_POST['sale_price'] : null,
        ':extended_license_price' => $_POST['extended_license_price'] !== '' ? (float) $_POST['extended_license_price'] : null,
        ':subscription_price' => $_POST['subscription_price'] !== '' ? (float) $_POST['subscription_price'] : null,
        ':offer_expiry' => trim($_POST['offer_expiry'] ?? ''),
        ':is_free' => isset($_POST['is_free']) ? 1 : 0,
        ':live_preview_url' => trim($_POST['live_preview_url'] ?? ''),
        ':admin_demo_url' => trim($_POST['admin_demo_url'] ?? ''),
        ':documentation_url' => trim($_POST['documentation_url'] ?? ''),
        ':video_preview_url' => trim($_POST['video_preview_url'] ?? ''),
        ':download_preview_url' => trim($_POST['download_preview_url'] ?? ''),
        ':thumbnail' => trim($_POST['thumbnail'] ?? ''),
        ':featured_banner' => trim($_POST['featured_banner'] ?? ''),
        ':product_logo' => trim($_POST['product_logo'] ?? ''),
        ':screenshot_gallery' => trim($_POST['screenshot_gallery'] ?? ''),
        ':mobile_screenshots' => trim($_POST['mobile_screenshots'] ?? ''),
        ':gif_preview' => trim($_POST['gif_preview'] ?? ''),
        ':video_showcase' => trim($_POST['video_showcase'] ?? ''),
        ':compatible_browsers' => trim($_POST['compatible_browsers'] ?? ''),
        ':compatible_cms' => trim($_POST['compatible_cms'] ?? ''),
        ':php_version' => trim($_POST['php_version'] ?? ''),
        ':framework' => trim($_POST['framework'] ?? ''),
        ':responsive' => isset($_POST['responsive']) ? 1 : 0,
        ':retina_ready' => isset($_POST['retina_ready']) ? 1 : 0,
        ':dark_mode' => isset($_POST['dark_mode']) ? 1 : 0,
        ':rtl_support' => isset($_POST['rtl_support']) ? 1 : 0,
        ':multi_language' => isset($_POST['multi_language']) ? 1 : 0,
        ':meta_title' => trim($_POST['meta_title'] ?? ''),
        ':meta_description' => trim($_POST['meta_description'] ?? ''),
        ':focus_keywords' => trim($_POST['focus_keywords'] ?? ''),
        ':og_image' => trim($_POST['og_image'] ?? ''),
        ':structured_data' => trim($_POST['structured_data'] ?? ''),
        ':canonical_url' => trim($_POST['canonical_url'] ?? ''),
        ':robots_control' => trim($_POST['robots_control'] ?? 'index,follow'),
        ':main_download_file' => trim($_POST['main_download_file'] ?? ''),
        ':additional_files' => trim($_POST['additional_files'] ?? ''),
        ':file_size' => trim($_POST['file_size'] ?? ''),
        ':download_limit' => (int) ($_POST['download_limit'] ?? 10),
        ':license_type' => trim($_POST['license_type'] ?? 'single-domain'),
        ':support_expiry' => trim($_POST['support_expiry'] ?? ''),
        ':auto_update_token' => trim($_POST['auto_update_token'] ?? ''),
    ];
}

function save_product_from_post(?array $existing = null, ?int $vendorId = null): int
{
    $data = product_payload_from_post($existing, $vendorId);
    $columns = array_map(fn($key) => substr($key, 1), array_keys($data));
    if ($existing) {
        $data[':id'] = (int) $existing['id'];
        $sets = implode(', ', array_map(fn($column) => "{$column} = :{$column}", $columns));
        db()->prepare("UPDATE products SET {$sets}, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute($data);
        $productId = (int) $existing['id'];
    } else {
        $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_keys($data)) . ')';
        db()->prepare($sql)->execute($data);
        $productId = (int) db()->lastInsertId();
    }
    sync_product_lists($productId);
    return $productId;
}

function sync_product_lists(int $productId): void
{
    db()->prepare('DELETE FROM product_features WHERE product_id = ?')->execute([$productId]);
    foreach (lines_to_array($_POST['features'] ?? '') as $i => $feature) {
        db()->prepare('INSERT INTO product_features (product_id, feature, sort_order) VALUES (?, ?, ?)')->execute([$productId, $feature, $i]);
    }
    db()->prepare('DELETE FROM product_faqs WHERE product_id = ?')->execute([$productId]);
    foreach (lines_to_array($_POST['faqs'] ?? '') as $i => $line) {
        [$question, $answer] = array_pad(explode('|', $line, 2), 2, '');
        if ($question && $answer) {
            db()->prepare('INSERT INTO product_faqs (product_id, question, answer, sort_order) VALUES (?, ?, ?, ?)')->execute([$productId, $question, $answer, $i]);
        }
    }
    db()->prepare('DELETE FROM product_changelog WHERE product_id = ?')->execute([$productId]);
    foreach (lines_to_array($_POST['changelog'] ?? '') as $line) {
        [$version, $notes] = array_pad(explode('|', $line, 2), 2, $line);
        db()->prepare('INSERT INTO product_changelog (product_id, version, notes, released_at) VALUES (?, ?, ?, date("now"))')->execute([$productId, $version, $notes]);
    }
}
