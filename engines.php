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
    if (empty($user_id)) return null;
    
    $supabaseUrl = getenv('SUPABASE_URL');
    // trim ব্যবহার করেছি যাতে আইডির আশেপাশের স্পেস ঝামেলা না করে
    $url = "$supabaseUrl/rest/v1/user_data?id=eq." . trim($user_id) . "&select=country_code";
    
    $data = call_supabase($url);
    
    // ডাটাবেজে ইউজার পাওয়া গেলে তার দেশের কোড দিবে, না পেলে null দিবে
    return (!empty($data) && isset($data[0]['country_code'])) ? strtoupper($data[0]['country_code']) : null;
}

function load_offers($user_id) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $country = get_user_country($user_id);

    // যদি ডাটাবেজে ইউজারকে না পাওয়া যায় বা কান্ট্রি না থাকে
    if (!$country) {
        return ["error" => "user_not_found", "message" => "Please complete your profile first!"];
    }

    // ১. অফার টেবিলে country_code কলাম দিয়ে ফিল্টার করা (তোর ডাটাবেজ অনুযায়ী)
    $offers = call_supabase("$supabaseUrl/rest/v1/all_offers?country_code=ilike.*$country*&device=eq.Android&task_type=not.ilike.*survey*&select=*");

    if (empty($offers)) {
        return ["error" => "no_offers", "message" => "No offers available for $country right now."];
    }

    // ২. কমপ্লিট করা অফার চেক
    $completed_data = call_supabase("$supabaseUrl/rest/v1/postback_logs?user_id=eq.$user_id&select=offer_id");
    $completed_ids = is_array($completed_data) ? array_column($completed_data, 'offer_id') : [];

    // ৩. ডাটা সাজানো
    foreach ($offers as &$o) {
        $o['is_completed'] = in_array($o['id'], $completed_ids);
        $o['link'] = $o['link'] . (strpos($o['link'], '?') !== false ? '&' : '?') . "s1=" . $user_id;
    }

    return $offers;
}

$userId = $_GET['user_id'] ?? '';
$finalResult = load_offers($userId);

// JSON_UNESCAPED_UNICODE দিয়েছি যাতে বাংলা লেখা ঠিকমতো দেখা যায়
echo json_encode($finalResult, JSON_UNESCAPED_UNICODE);
?>
