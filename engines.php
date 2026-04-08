<?php
// Cache control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");


// কমন ডাটা রিকোয়েস্ট ফাংশন
function call_supabase($url) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $supabaseKey", "Authorization: Bearer $supabaseKey", "Content-Type: application/json"]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function get_user_country($user_id) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');
    
    // Search country code from database by user id
    // using urlencode so id doesn't have problems if there are spaces or something else
    $url = "$supabaseUrl/rest/v1/user_data?id=eq." . urlencode($user_id) . "&select=country_code";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $supabaseKey", 
        "Authorization: Bearer $supabaseKey", 
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return 'BD'; // If there is an error in the connection, the default Bangladesh will be taken
    }

    $data = json_decode($response, true);
    
    // If that user is found in the database
    if (!empty($data) && isset($data[0]['country_code'])) {
        // Returns whatever is in the database (ie: BD or US)
        return $data[0]['country_code']; 
    }
    
    // If the user is not found in the database, only return 'BD'
    return 'BD'; 
}

function load_offers($user_id) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $country = get_user_country($user_id);

    // ১. সব অফার আনা
  $offers = call_supabase("$supabaseUrl/rest/v1/all_offers?country=ilike.*$country*&device=eq.Android&task_type=not.ilike.*survey*&select=*");

    // ২. এই ইউজারের কমপ্লিট করা অফারগুলোর আইডি আনা
    $completed_data = call_supabase("$supabaseUrl/rest/v1/postback_logs?user_id=eq.$user_id&select=offer_id");
    $completed_ids = is_array($completed_data) ? array_column($completed_data, 'offer_id') : [];

    // ৩. প্রতিটি অফারে স্ট্যাটাস এবং ট্র্যাকিং লিঙ্ক বসানো
    if (is_array($offers)) {
        foreach ($offers as &$o) {
            // যদি অফার আইডিটি completed_ids এ থাকে, তবে is_completed = true
            $o['is_completed'] = in_array($o['id'], $completed_ids);
            
            // লিঙ্কের সাথে ইউজার আইডি যুক্ত করা (CPAGrip এর জন্য)
            // আপনার লিঙ্কের ফরম্যাট অনুযায়ী '&s1=' বা '&subid=' ব্যবহার করুন
            $o['link'] = $o['link'] . (strpos($o['link'], '?') !== false ? '&' : '?') . "s1=" . $user_id;
        }
    }

    return $offers;
}
$userId = $_GET['user_id'] ?? '';
$finalOffers = load_offers($userId);
echo json_encode($finalOffers);
?>
