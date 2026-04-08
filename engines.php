<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

function call_supabase($url) {
    $supabaseKey = getenv('SUPABASE_KEY');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey", 
        "Authorization: Bearer $supabaseKey", 
        "Content-Type: application/json"
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function get_user_country($user_id) {
    $supabaseUrl = getenv('SUPABASE_URL');
    if (empty($user_id)) return null;

    // এখানে তোর কলামের নাম 'user_id' দিয়ে সেট করে দিলাম
    $url = "$supabaseUrl/rest/v1/user_data?user_id=eq." . trim($user_id) . "&select=country_code";
    
    $data = call_supabase($url);

    if (!empty($data) && isset($data[0]['country_code'])) {
        return strtoupper($data[0]['country_code']);
    }
    return null;
}

function load_offers($user_id) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $country = get_user_country($user_id);

    // ইউজার না পাওয়া গেলে এরর মেসেজ
    if (!$country) {
        return [[
            "title" => "Error: No data found for ID: " . $user_id,
            "image" => "",
            "link" => "#",
            "task_type" => "SYSTEM"
        ]];
    }

    // কান্ট্রি কোড অনুযায়ী অফার আনা (country_code কলাম ব্যবহার করে)
    $offers = call_supabase("$supabaseUrl/rest/v1/all_offers?country=ilike.*$country*&device=eq.Android&task_type=not.ilike.*survey*&select=*");

    if (empty($offers)) {
        return [[
            "title" => "No offers available for " . $country,
            "image" => "",
            "link" => "#",
            "task_type" => "INFO"
        ]];
    }

    // কমপ্লিট করা অফার চেক
    $completed_data = call_supabase("$supabaseUrl/rest/v1/postback_logs?user_id=eq.$user_id&select=offer_id");
    $completed_ids = is_array($completed_data) ? array_column($completed_data, 'offer_id') : [];

    foreach ($offers as &$o) {
        $o['is_completed'] = in_array($o['id'], $completed_ids);
        $o['link'] = $o['link'] . (strpos($o['link'], '?') !== false ? '&' : '?') . "s1=" . $user_id;
    }

    return $offers;
}

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode([["title" => "Error: User ID missing", "image" => "", "link" => "#"]]);
} else {
    $finalResult = load_offers($userId);
    // JSON_UNESCAPED_UNICODE টা খুব জরুরি বাংলা লেখার জন্য
    echo json_encode($finalResult, JSON_UNESCAPED_UNICODE);
}
?>
