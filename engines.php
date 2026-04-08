<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

// কমন ডাটা রিকোয়েস্ট ফাংশন
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

    // আইডি দিয়ে কান্ট্রি কোড খোঁজা
    $url = "$supabaseUrl/rest/v1/user_data?user_id=eq." . trim($user_id) . "&select=country_code";
    $data = call_supabase($url);

    if (!empty($data) && isset($data[0]['country_code'])) {
        $found_country = strtoupper($data[0]['country_code']);
        header("X-Debug-Found-Country: " . $found_country); // ব্রাউজারের নেটওয়ার্ক ট্যাবে দেখাবে
        return $found_country;
    }
    
    header("X-Debug-Found-Country: NOT_IN_DB");
    return null;
}

function load_offers($user_id) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $country = get_user_country($user_id);

    // যদি কান্ট্রি না পাওয়া যায়, তবে একটি ফেক অফার দেখাবে ডিবাগিং এর জন্য
    if (!$country) {
        return [[
            "title" => "Error: Country not found for ID: " . $user_id,
            "image" => "https://via.placeholder.com/60/FF0000/FFFFFF?text=ERR",
            "link" => "#",
            "task_type" => "SYSTEM",
            "is_completed" => false
        ]];
    }

    // অফার আনা
    $offers = call_supabase("$supabaseUrl/rest/v1/all_offers?country_code=ilike.*$country*&device=eq.Android&task_type=not.ilike.*survey*&select=*");

    if (empty($offers)) {
        return [[
            "title" => "No offers found for country: " . $country,
            "image" => "https://via.placeholder.com/60/CCCCCC/FFFFFF?text=EMPTY",
            "link" => "#",
            "task_type" => "INFO",
            "is_completed" => false
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

// মেইন এক্সিকিউশন
$userId = $_GET['user_id'] ?? '';

// সার্ভার কি আইডি পেয়েছে? সেটা হেডারে পাঠাবে
header("X-Received-User-ID: " . $userId);

if (empty($userId)) {
    echo json_encode([[
        "title" => "Error: No User ID received by Engine",
        "image" => "",
        "link" => "#",
        "task_type" => "ERROR"
    ]]);
} else {
    $finalResult = load_offers($userId);
    echo json_encode($finalResult, JSON_UNESCAPED_UNICODE);
}
?>
