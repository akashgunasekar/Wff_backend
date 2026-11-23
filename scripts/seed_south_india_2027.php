<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

// 1. Check or Insert Event
$slug = "south-india-natural-league-2027";
$stmt = $db->prepare("SELECT id FROM events WHERE slug = ?");
$stmt->execute([$slug]);
$eventId = $stmt->fetchColumn();

if (!$eventId) {
    $insertEvent = $db->prepare("INSERT INTO events (slug, event_name, event_date, date_type, event_end_date, venue, tan_spray_price, banner_image, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertEvent->execute([
        $slug,
        "South India Natural League 2027",
        "2027-01-10",
        "single",
        null,
        "Chennai, Tamil Nadu, India",
        1000,
        "/uploads/events/south-india-natural-league-2027.jpg",
        "ongoing",
        "A Celebration of Natural Fitness, Strength & Championship Excellence. World Fitness Federation Tamil Nadu presents South India Natural League 2027 in Chennai, Tamil Nadu."
    ]);
    $eventId = $db->lastInsertId();
    echo "Created event with ID: $eventId\n";
} else {
    echo "Updating existing event with ID: $eventId\n";
    $updateEvent = $db->prepare("UPDATE events SET event_name = ?, event_date = ?, date_type = ?, venue = ?, banner_image = ?, status = ?, description = ? WHERE id = ?");
    $updateEvent->execute([
        "South India Natural League 2027",
        "2027-01-10",
        "single",
        "Chennai, Tamil Nadu, India",
        "/uploads/events/south-india-natural-league-2027.jpg",
        "ongoing",
        "A Celebration of Natural Fitness, Strength & Championship Excellence. World Fitness Federation Tamil Nadu presents South India Natural League 2027 in Chennai, Tamil Nadu.",
        $eventId
    ]);
}

// 2. Officials
$officialsData = [
    [
        "name" => "Mohan Kumar",
        "role" => "President, WFF Tamil Nadu",
        "phone" => "9952922686",
        "designation" => "President, WFF Tamil Nadu",
        "bio" => "President, WFF Tamil Nadu. Contact: 9952922686"
    ],
    [
        "name" => "R. Laganathan",
        "role" => "Secretary, WFF Tamil Nadu",
        "phone" => "+91 90874 89127",
        "designation" => "Secretary, WFF Tamil Nadu",
        "bio" => "Secretary, WFF Tamil Nadu. Contact: +91 90874 89127"
    ],
    [
        "name" => "Rupert Raj",
        "role" => "Senior Vice President, WFF Tamil Nadu",
        "phone" => "+91 97900 96385",
        "designation" => "Senior Vice President, WFF Tamil Nadu",
        "bio" => "Senior Vice President, WFF Tamil Nadu. Contact: +91 97900 96385"
    ]
];

$eventOfficialsList = [];
$order = 1;
foreach ($officialsData as $off) {
    $stmt = $db->prepare("SELECT id FROM officials WHERE name LIKE ?");
    $stmt->execute(["%" . $off["name"] . "%"]);
    $offId = $stmt->fetchColumn();
    if (!$offId) {
        $stmtIns = $db->prepare("INSERT INTO officials (name, role, designation, photo, bio, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmtIns->execute([
            $off["name"],
            $off["role"],
            $off["designation"],
            "/assets/wff-international.png",
            $off["bio"],
            $order
        ]);
        $offId = $db->lastInsertId();
    } else {
        $stmtUp = $db->prepare("UPDATE officials SET role = ?, designation = ?, bio = ?, status = 1 WHERE id = ?");
        $stmtUp->execute([$off["role"], $off["designation"], $off["bio"], $offId]);
    }

    $key = "official_{$offId}_contact";
    $val = json_encode(["phone" => $off["phone"], "role" => $off["role"]]);
    $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $val]);

    $eventOfficialsList[] = [
        "official_id" => (int)$offId,
        "name" => $off["name"],
        "role" => $off["role"],
        "phone" => $off["phone"],
        "display_order" => $order++
    ];
}

// 3. Setup Categories
$categories = [
    // Denim Jeans Model
    ["main" => "Denim Jeans Model", "sub" => "Teenager — Up to 18 years", "age_group" => "Up to 18 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 1],
    ["main" => "Denim Jeans Model", "sub" => "Sub Junior — Up to 20 years", "age_group" => "Up to 20 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 2],
    ["main" => "Denim Jeans Model", "sub" => "Junior — Up to 23 years", "age_group" => "Up to 23 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 3],
    ["main" => "Denim Jeans Model", "sub" => "Senior — Open for all", "age_group" => "Open for all", "gender" => "Male", "entry_fee" => "3000", "type" => "open", "order" => 4],

    // Bermuda Beach Model
    ["main" => "Bermuda Beach Model", "sub" => "Teenager — Up to 18 years", "age_group" => "Up to 18 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 5],
    ["main" => "Bermuda Beach Model", "sub" => "Sub Junior — Up to 20 years", "age_group" => "Up to 20 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 6],
    ["main" => "Bermuda Beach Model", "sub" => "Junior — Up to 23 years", "age_group" => "Up to 23 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 7],
    ["main" => "Bermuda Beach Model", "sub" => "Senior Short — Up to 1.65 metres", "age_group" => "Senior Short (Up to 1.65m)", "gender" => "Male", "entry_fee" => "3000", "type" => "height", "order" => 8],
    ["main" => "Bermuda Beach Model", "sub" => "Senior Tall — Above 1.65 metres", "age_group" => "Senior Tall (Above 1.65m)", "gender" => "Male", "entry_fee" => "3000", "type" => "height", "order" => 9],

    // Men Bodybuilding
    ["main" => "Men Bodybuilding", "sub" => "Teenager — Up to 18 years", "age_group" => "Up to 18 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 10],
    ["main" => "Men Bodybuilding", "sub" => "Sub Junior — Up to 20 years", "age_group" => "Up to 20 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 11],
    ["main" => "Men Bodybuilding", "sub" => "Junior — Up to 23 years", "age_group" => "Up to 23 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 12],
    ["main" => "Men Bodybuilding", "sub" => "Masters — Above 40 years", "age_group" => "Above 40 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 13],
    ["main" => "Men Bodybuilding", "sub" => "Master's — Above 50 years", "age_group" => "Above 50 years", "gender" => "Male", "entry_fee" => "3000", "type" => "age", "order" => 14],
    ["main" => "Men Bodybuilding", "sub" => "Senior — Up to 55 kg", "age_group" => "Senior (Up to 55 kg)", "gender" => "Male", "entry_fee" => "3000", "type" => "weight", "order" => 15],
    ["main" => "Men Bodybuilding", "sub" => "Senior — Up to 60 kg", "age_group" => "Senior (Up to 60 kg)", "gender" => "Male", "entry_fee" => "3000", "type" => "weight", "order" => 16],
    ["main" => "Men Bodybuilding", "sub" => "Senior — Up to 65 kg", "age_group" => "Senior (Up to 65 kg)", "gender" => "Male", "entry_fee" => "3000", "type" => "weight", "order" => 17],
    ["main" => "Men Bodybuilding", "sub" => "Senior — Up to 70 kg", "age_group" => "Senior (Up to 70 kg)", "gender" => "Male", "entry_fee" => "3000", "type" => "weight", "order" => 18],
    ["main" => "Men Bodybuilding", "sub" => "Senior — Up to 75 kg", "age_group" => "Senior (Up to 75 kg)", "gender" => "Male", "entry_fee" => "3000", "type" => "weight", "order" => 19],
    ["main" => "Men Bodybuilding", "sub" => "Senior — Above 75 kg", "age_group" => "Senior (Above 75 kg)", "gender" => "Male", "entry_fee" => "3000", "type" => "weight", "order" => 20]
];

// Clean categories for this event
$db->prepare("DELETE FROM event_categories WHERE event_id = ?")->execute([$eventId]);

$insertCat = $db->prepare("INSERT INTO event_categories (event_id, name, age_group, gender, entry_fee, availability) VALUES (?, ?, ?, ?, ?, 'open')");
foreach ($categories as $cat) {
    $fullName = $cat["main"] . " — " . $cat["sub"];
    $insertCat->execute([$eventId, $fullName, $cat["age_group"], $cat["gender"], $cat["entry_fee"]]);
    $catId = $db->lastInsertId();

    $extData = [
        "main_category" => $cat["main"],
        "sub_category" => $cat["sub"],
        "short_description" => $cat["sub"],
        "structure_type" => $cat["type"],
        "display_order" => $cat["order"]
    ];
    $extKey = "category_ext_{$catId}";
    $extVal = json_encode($extData);
    $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$extKey, $extVal, $extVal]);
}

// 4. Save Event Settings (Pricing, Cash, Officials, Meta)
$pricingConfig = [
    "pricing_model" => "tiered",
    "single_category" => 3000,
    "double_category" => 5000,
    "triple_category" => 6000,
    "clinical_testing_charge" => 2000,
    "max_categories" => 3
];
$pKey = "event_{$eventId}_pricing";
$pVal = json_encode($pricingConfig);
$db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$pKey, $pVal, $pVal]);

$cashKey = "event_{$eventId}_cash_enabled";
$db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$cashKey, "1", "1"]);

$offKey = "event_{$eventId}_officials";
$offVal = json_encode($eventOfficialsList);
$db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$offKey, $offVal, $offVal]);

$contentMeta = [
    "about_title" => "South India Natural League 2027",
    "about_content" => "<p>World Fitness Federation (WFF) Tamil Nadu proudly presents the <strong>SOUTH INDIA NATURAL LEAGUE 2027</strong> in Chennai on <strong>10 January 2027</strong>. This championship is a grand celebration of natural fitness, raw strength, and championship excellence across South India.</p><p>Athletes from across South India will compete under official WFF international drug-free testing standards across Denim Jeans Model, Bermuda Beach Model, and Men Bodybuilding divisions.</p>",
    "why_title" => "Championship Highlights",
    "why_intro" => "South India Natural League 2027 delivers a world-class platform for natural athletes.",
    "why_highlights" => [
        ["title" => "Drug-Free Natural Championship", "description" => "Rigorous clinical testing ensures a 100% fair, transparent, and authentic competitive platform."],
        ["title" => "Official WFF Affiliation", "description" => "Recognized by World Fitness Federation International & World Fitness Federation India."],
        ["title" => "Grand Trophy & Title Awards", "description" => "Medals, official certificates, championship trophies, and cash awards for title winners."],
        ["title" => "Official Stage Pass & Verification", "description" => "Digitally verified official entry ticket with QR verification issued upon registration approval."]
    ],
    "pricing_tiers" => [
        ["tier" => "Single Category", "fee" => 3000, "clinical_charge" => 2000, "total" => 5000],
        ["tier" => "Double Category", "fee" => 5000, "clinical_charge" => 2000, "total" => 7000],
        ["tier" => "Triple Category", "fee" => 6000, "clinical_charge" => 2000, "total" => 8000]
    ],
    "clinical_charge" => 2000,
    "faqs" => [
        ["question" => "Why is clinical testing mandatory?", "answer" => "WFF is dedicated to pure natural bodybuilding. The mandatory clinical testing charge of ₹2,000 per athlete covers professional medical testing to safeguard clean competition."],
        ["question" => "How does multi-category pricing work?", "answer" => "Single category entry is ₹3,000; double category cross-entry is ₹5,000; and triple category entry is ₹6,000. The mandatory clinical testing charge of ₹2,000 is added once per athlete regardless of how many categories you enter."],
        ["question" => "How do I get my official entry ticket?", "answer" => "Upon completing the online registration form and uploading your UPI payment screenshot, you will receive a Sample Ticket (Pending Verification). Once our administrative team verifies your payment, your Official Approved Ticket will be emailed to you with a valid entry QR code."]
    ]
];
$metaKey = "event_{$eventId}_meta";
$metaVal = json_encode($contentMeta);
$db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$metaKey, $metaVal, $metaVal]);

// 5. Update Hero Slides table to feature South India Natural League 2027 at sort_order 0
// Shift other slides down
$db->query("UPDATE hero_slides SET sort_order = sort_order + 1");

$stmt = $db->prepare("SELECT id FROM hero_slides WHERE event_id = ?");
$stmt->execute([$eventId]);
$slideId = $stmt->fetchColumn();

if ($slideId) {
    $db->prepare("UPDATE hero_slides SET title = ?, subtitle = ?, image = ?, date = ?, location = ?, sort_order = 0, status = 1 WHERE id = ?")
       ->execute([
           "SOUTH INDIA NATURAL LEAGUE 2027",
           "A Celebration of Natural Fitness, Strength & Championship Excellence",
           "/uploads/events/south-india-natural-league-2027.jpg",
           "10 January 2027",
           "Chennai, Tamil Nadu",
           $slideId
       ]);
} else {
    $db->prepare("INSERT INTO hero_slides (title, subtitle, image, date, location, event_id, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 0, 1)")
       ->execute([
           "SOUTH INDIA NATURAL LEAGUE 2027",
           "A Celebration of Natural Fitness, Strength & Championship Excellence",
           "/uploads/events/south-india-natural-league-2027.jpg",
           "10 January 2027",
           "Chennai, Tamil Nadu",
           $eventId
       ]);
}

echo "Successfully configured South India Natural League 2027 (Event ID: $eventId) with 20 categories, pricing, officials, and hero slide!\n";
