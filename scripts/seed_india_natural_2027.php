<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

// 1. Check or Insert Event: India Natural 2027
$slug = "india-natural-2027";
$stmt = $db->prepare("SELECT id FROM events WHERE slug = ?");
$stmt->execute([$slug]);
$eventId = $stmt->fetchColumn();

if (!$eventId) {
    $insertEvent = $db->prepare("INSERT INTO events (slug, event_name, event_date, date_type, event_end_date, venue, tan_spray_price, banner_image, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertEvent->execute([
        $slug,
        "INDIA NATURAL 2027",
        "2027-02-27",
        "multi",
        "2027-02-28",
        "Chennai, Tamil Nadu, India",
        1000,
        "/uploads/events/india-natural-2027.png",
        "upcoming",
        "WFF Natural League presents India Natural 2027 — Natural National Championship & Fashion Show in Chennai, Tamil Nadu on February 27 & 28, 2027. A premier drug-free national championship platform for elite natural athletes and fashion show models across India."
    ]);
    $eventId = $db->lastInsertId();
    echo "Created event with ID: $eventId\n";
} else {
    echo "Updating existing event with ID: $eventId\n";
    $updateEvent = $db->prepare("UPDATE events SET event_name = ?, event_date = ?, date_type = ?, event_end_date = ?, venue = ?, banner_image = ?, status = ?, description = ? WHERE id = ?");
    $updateEvent->execute([
        "INDIA NATURAL 2027",
        "2027-02-27",
        "multi",
        "2027-02-28",
        "Chennai, Tamil Nadu, India",
        "/uploads/events/india-natural-2027.png",
        "upcoming",
        "WFF Natural League presents India Natural 2027 — Natural National Championship & Fashion Show in Chennai, Tamil Nadu on February 27 & 28, 2027. A premier drug-free national championship platform for elite natural athletes and fashion show models across India.",
        $eventId
    ]);
}

// 2. Event Officials & Contact Numbers from Official Poster
$officialsData = [
    [
        "name" => "Mohan Kumar",
        "role" => "President, WFF Tamil Nadu",
        "phone" => "9952922686",
        "designation" => "President, WFF Tamil Nadu",
        "bio" => "President, WFF Tamil Nadu. Official Event Coordinator. Contact: 9952922686"
    ],
    [
        "name" => "WFF India Information Desk",
        "role" => "Championship Coordinator",
        "phone" => "+91 94350 48586",
        "designation" => "National Coordinator, WFF India",
        "bio" => "National Event Coordinator, WFF India Natural League. Contact: +91 94350 48586"
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

// 3. Setup Categories for India Natural 2027
$categories = [
    // Fashion Show Model (Prominently featured on poster)
    ["main" => "Fashion Show Model", "sub" => "Teenager — Up to 18 years", "age_group" => "Up to 18 years", "gender" => "Open", "entry_fee" => "3000", "type" => "age", "order" => 1],
    ["main" => "Fashion Show Model", "sub" => "Sub Junior — Up to 20 years", "age_group" => "Up to 20 years", "gender" => "Open", "entry_fee" => "3000", "type" => "age", "order" => 2],
    ["main" => "Fashion Show Model", "sub" => "Junior — Up to 23 years", "age_group" => "Up to 23 years", "gender" => "Open", "entry_fee" => "3000", "type" => "age", "order" => 3],
    ["main" => "Fashion Show Model", "sub" => "Senior — Open for all", "age_group" => "Open for all", "gender" => "Open", "entry_fee" => "3000", "type" => "open", "order" => 4],

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
    "about_title" => "India Natural 2027 — Natural National Championship & Fashion Show",
    "about_content" => "<p>WFF Natural League proudly presents <strong>INDIA NATURAL 2027</strong> in Chennai on <strong>February 27 & 28, 2027</strong>. This landmark 2-day championship brings together elite drug-free athletes and fashion models from across India to compete on a prestigious national stage.</p><p>Featuring the <strong>Natural National Championship</strong> across Men Bodybuilding and Bermuda Beach Model divisions alongside the high-energy <strong>Fashion Show Model</strong> showcase. All athletes compete under strict WFF anti-doping clinical testing standards.</p>",
    "why_title" => "Championship Highlights",
    "why_intro" => "India Natural 2027 sets a benchmark for drug-free athletic competition and fashion presentation in India.",
    "why_highlights" => [
        ["title" => "Natural National Championship", "description" => "Rigorous clinical medical testing guarantees a 100% fair, transparent, and authentic drug-free stage."],
        ["title" => "Exclusive Fashion Show Stage", "description" => "A high-fashion runway showcase for emerging and elite models to demonstrate stage presence and style."],
        ["title" => "WFF India & International Recognition", "description" => "Direct pathway to WFF Universe qualification and official national federation ranking."],
        ["title" => "Official Digital Entry Pass", "description" => "Secure QR verification ticket issued to all approved athletes for seamless backstage and stage entry."]
    ],
    "pricing_tiers" => [
        ["tier" => "Single Category", "fee" => 3000, "clinical_charge" => 2000, "total" => 5000],
        ["tier" => "Double Category", "fee" => 5000, "clinical_charge" => 2000, "total" => 7000],
        ["tier" => "Triple Category", "fee" => 6000, "clinical_charge" => 2000, "total" => 8000]
    ],
    "clinical_charge" => 2000,
    "faqs" => [
        ["question" => "What are the event dates for India Natural 2027?", "answer" => "India Natural 2027 is a 2-day championship held across February 27 & 28, 2027 in Chennai, Tamil Nadu."],
        ["question" => "Why is clinical testing mandatory?", "answer" => "WFF Natural League is committed to drug-free competition. The clinical testing charge covers certified medical testing to protect clean athletes."],
        ["question" => "How do I participate in both Bodybuilding and Fashion Show?", "answer" => "Athletes can enter multiple categories using our discounted multi-division pricing (₹3,000 for 1st division, +₹2,000 for 2nd division, and +₹1,000 for 3rd division). The medical clinical testing fee of ₹2,000 applies once per athlete."]
    ]
];
$metaKey = "event_{$eventId}_meta";
$metaVal = json_encode($contentMeta);
$db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$metaKey, $metaVal, $metaVal]);

// 5. Add Hero Slide for India Natural 2027
$stmt = $db->prepare("SELECT id FROM hero_slides WHERE event_id = ?");
$stmt->execute([$eventId]);
$slideId = $stmt->fetchColumn();

if ($slideId) {
    $db->prepare("UPDATE hero_slides SET title = ?, subtitle = ?, image = ?, date = ?, location = ?, sort_order = 1, status = 1 WHERE id = ?")
       ->execute([
           "INDIA NATURAL 2027",
           "Natural National Championship & Fashion Show",
           "/uploads/events/india-natural-2027.png",
           "27 & 28 February 2027",
           "Chennai, Tamil Nadu",
           $slideId
       ]);
} else {
    $db->prepare("INSERT INTO hero_slides (title, subtitle, image, date, location, event_id, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 1, 1)")
       ->execute([
           "INDIA NATURAL 2027",
           "Natural National Championship & Fashion Show",
           "/uploads/events/india-natural-2027.png",
           "27 & 28 February 2027",
           "Chennai, Tamil Nadu",
           $eventId
       ]);
}

echo "Successfully configured India Natural 2027 (Event ID: $eventId) with 20 categories, pricing, officials, and hero slide!\n";
