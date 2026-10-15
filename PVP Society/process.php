<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// CONFIGURATION: Replace with your actual Discord Webhook URL
$webhookUrl = 'https://discord.com/api/webhooks/1560151970906636414/GuBVXCcmpA479LVVOiGSRJtdhfTwDLBJA56MrI8mvgh2OFuHTtVRub4RSveebwTg0M5f';

// ------------------------------------------------------------------
// 1. INPUT SANITIZATION & VALIDATION
// ------------------------------------------------------------------
$ign          = htmlspecialchars(trim($_POST['ign'] ?? ''), ENT_QUOTES, 'UTF-8');
$discord      = htmlspecialchars(trim($_POST['discord'] ?? ''), ENT_QUOTES, 'UTF-8');
$age          = filter_var($_POST['age'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
$timezone     = htmlspecialchars(trim($_POST['timezone'] ?? ''), ENT_QUOTES, 'UTF-8');
$playtime     = htmlspecialchars(trim($_POST['playtime'] ?? ''), ENT_QUOTES, 'UTF-8');
$play_hours   = htmlspecialchars(trim($_POST['play_hours'] ?? ''), ENT_QUOTES, 'UTF-8');
$experience   = htmlspecialchars(trim($_POST['experience'] ?? ''), ENT_QUOTES, 'UTF-8');
$punishments  = htmlspecialchars(trim($_POST['punishments'] ?? ''), ENT_QUOTES, 'UTF-8');
$skills       = htmlspecialchars(trim($_POST['skills'] ?? ''), ENT_QUOTES, 'UTF-8');
$why_helper   = htmlspecialchars(trim($_POST['why_helper'] ?? ''), ENT_QUOTES, 'UTF-8');
$why_you      = htmlspecialchars(trim($_POST['why_you'] ?? ''), ENT_QUOTES, 'UTF-8');
$additional   = htmlspecialchars(trim($_POST['additional'] ?? ''), ENT_QUOTES, 'UTF-8');

if (empty($ign) || empty($discord) \vert{}\vert{} empty($age) || empty($timezone) \vert{}\vert{} empty($playtime) || empty($play_hours) \vert{}\vert{} empty($experience) || empty($punishments) \vert{}\vert{} empty($skills) || empty($why_helper) \vert{}\vert{} empty($why_you)) {
    echo json_encode(['success' => false, 'error' => 'Please fill out all required fields.']);
    exit;
}

if ($age < 13) {
    echo json_encode(['success' => false, 'error' => 'You must meet the age requirements (13+) to apply.']);
    exit;
}

// ------------------------------------------------------------------
// 2. SERVER-SIDE 7-DAY COOLDOWN ENFORCEMENT
// ------------------------------------------------------------------
$cooldownFile = __DIR__ . '/submissions.json';$cooldownSeconds = 7 * 24 * 60 * 60; // 7 Days in seconds (604,800 seconds)
$userIP =$_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$ignLower = strtolower($ign);

$cooldownData = [];
if (file_exists($cooldownFile)) {
    $cooldownData = json_decode(file_get_contents($cooldownFile), true) ?? [];
}

$now = time();

// Check if IP or IGN has submitted within the last 7 days
foreach ($cooldownData as$entry) {
    if (($entry['ip'] === $userIP \vert{}\vert{} strtolower($entry['ign']) === $ignLower)) {
        $timePassed = $now -$entry['timestamp'];
        if ($timePassed < $cooldownSeconds) {$daysRemaining = ceil(($cooldownSeconds -$timePassed) / 86400);
            echo json_encode([
                'success' => false, 
                'error' => "You have already submitted an application recently. Please wait {$daysRemaining} day(s) before applying again."
            ]);
            exit;
        }
    }
}

// Record current submission timestamp
$cooldownData[] = [
    'ip' => $userIP,
    'ign' => $ign,
    'timestamp' => $now
];
file_put_contents($cooldownFile, json_encode($cooldownData, JSON_PRETTY_PRINT));

// ------------------------------------------------------------------
// 3. RICH DISCORD WEBHOOK EMBED DESIGN
// ------------------------------------------------------------------
// Fetch Minecraft avatar for the player's head icon
$avatarUrl = "https://mc-heads.net/avatar/" . urlencode($ign) . "/100";

$embed = [
    "title" => "🛡️ NEW HELPER APPLICATION",
    "description" => "A new staff application has been submitted for **PVP Society**.",
    "color" => 65534, // Neon Cyan hex color (#00F2FE -> Decimal 65534)
    "thumbnail" => [
        "url" => $avatarUrl
    ],
    "fields" => [
        [
            "name" => "👤 Applicant Overview",
            "value" => "• **IGN:** `{$ign}`\n• **Discord:** `{$discord}`\n• **Age:** `{$age}`\n• **Timezone:** `{$timezone}`",
            "inline" => false
        ],
        [
            "name" => "⏳ Activity & Availability",
            "value" => "• **Time on Server:** {$playtime}\n• **Weekly Availability:** {$play_hours}",
            "inline" => false
        ],
        [
            "name" => "📜 7. Past Staff Experience",
            "value" => $experience,
            "inline" => false
        ],
        [
            "name" => "⚠️ 8. Punishment History",
            "value" => $punishments,
            "inline" => false
        ],
        [
            "name" => "⭐ 9. Skills & Qualities",
            "value" => $skills,
            "inline" => false
        ],
        [
            "name" => "🎯 10. Motivation for Helper",
            "value" => $why_helper,
            "inline" => false
        ],
        [
            "name" => "🏅 11. Why Choose You?",
            "value" => $why_you,
            "inline" => false
        ],
        [
            "name" => "💬 12. Additional Information",
            "value" => !empty($additional) ?$additional : "*None provided*",
            "inline" => false
        ]
    ],
    "footer" => [
        "text" => "PVP Society Recruitment • Submitted at",
        "icon_url" => "https://i.imgur.com/8N4X1yO.png" // Replace with your logo URL if available
    ],
    "timestamp" => date("c")
];

$payload = json_encode([
    "username" => "PVP Society Staff Portal",
    "avatar_url" => "https://mc-heads.net/avatar/Steve/100",
    "embeds" => [$embed]
]);

// ------------------------------------------------------------------
// 4. DISPATCH WEBHOOK VIA CURL
// ------------------------------------------------------------------
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS,$payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($payload)
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 &&$httpCode < 300) {
    echo json_encode([
        'success' => true, 
        'message' => 'Helper application submitted successfully! Our management team will review it shortly on Discord.'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to dispatch webhook notification. Please contact a server admin.'
    ]);
}
?>