<?php
// chatbot/intent_router.php

// Start output buffer FIRST — catch any stray output before headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Always respond with JSON, even on fatal shutdown
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Server error: ' . $error['message']
        ]);
    } else {
        // Flush whatever is in buffer normally
        ob_end_flush();
    }
});

// Suppress warnings/notices from polluting the JSON output
error_reporting(0);
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in to use the assistant.']);
    exit;
}

require_once __DIR__ . '/../includes/db_config.php';

$user_id = (int)$_SESSION['user_id'];
$role_id = (int)($_SESSION['role_id'] ?? 0);

// Get Building ID for data isolation
$building_id = null;
if ($role_id == 2) { // Resident
    $bq = mysqli_query($conn, "SELECT a.building_id FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '$user_id' LIMIT 1");
    if ($bq && mysqli_num_rows($bq) > 0) {
        $building_id = mysqli_fetch_assoc($bq)['building_id'];
    }
} elseif ($role_id == 1) { // Owner
    // Owners have their building_id stored in session by the login flow
    if (!empty($_SESSION['building_id'])) {
        $building_id = $_SESSION['building_id'];
    } else {
        // Fallback: get building_id from apartment_assignments for this user
        $bq2 = mysqli_query($conn, "SELECT a.building_id FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '$user_id' LIMIT 1");
        if ($bq2 && mysqli_num_rows($bq2) > 0) {
            $building_id = mysqli_fetch_assoc($bq2)['building_id'];
        } else {
            // Last resort: get any building_id from apartments (single-building setups)
            $bq3 = mysqli_query($conn, "SELECT building_id FROM apartments LIMIT 1");
            if ($bq3 && mysqli_num_rows($bq3) > 0) {
                $building_id = mysqli_fetch_assoc($bq3)['building_id'];
            }
        }
    }
}

$query = trim($_POST['query'] ?? '');
if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Empty query.']);
    exit;
}

// ==========================================
// GEMINI API CONFIGURATION
// ==========================================
$gemini_api_key = 'AIzaSyDhua6u8MxCcmJDGxvh7uGfW_vt19QfhRc';
// ==========================================

$role_label = ($role_id == 1) ? 'owner' : 'resident';

$system_instruction = <<<PROMPT
You are the AI assistant for 'Nibash', a smart building management system. The current user is a {$role_label}.
Your ONLY job is to parse the user's natural language into a strict JSON intent object.
You must output ONLY valid JSON without any markdown code block wrappers (no ```json or ```).

=== INTENT TYPES ===
1. NAVIGATE — When the user wants to go to a specific page.
   Output: {"intent": "navigate", "target": "<target_name>"}
2. ACTION — When the user wants to perform a specific task.
   Output: {"intent": "action", "action_type": "<action_type>", "params": {<any_extracted_params>}}
3. QUERY_DATA — When the user asks for specific info like bills, visitors, etc.
   Output: {"intent": "query_data", "query_type": "<query_type>"}
4. CONVERSATIONAL — For general greetings, small talk, or questions about who you are. You are Nibash AI.
   Output: {"intent": "conversational", "response_text": "<your friendly response>"}
5. UNKNOWN — Cannot match any intent and not conversational.
   Output: {"intent": "unknown"}

=== NAVIGATION TARGETS & SYNONYMS (all roles) ===
- "dashboard"         → go to dashboard, home, home page, main page, start page, control panel, index, open dashboard
- "profile"           → go to my profile, account settings, my account, view profile, edit profile, user settings
- "notifications"     => go to notifications, alerts, recent activity, bell icon, what's new, check notifications
- "community_hub"     → go to community hub, feed, posts, chat section, chats page, forum, message board, open community
- "nearby_spots"      → go to nearby spots, places nearby, local area, map, surroundings, nearby essentials
- "logout"            → sign out, log out, exit, leave, end session, log me off
- "emergency_console" → open emergency console, emergency panel, crisis center, safety dashboard

=== NAVIGATION TARGETS & SYNONYMS (resident only) ===
- "billing"           → go to billing, bills, payment history, invoices, open billing section, my dues, financial page
- "tickets"           → go to service tickets, my requests, maintenance, support, open a ticket, view complaints
- "guest_passes"      → go to guest passes, visitors, guests page, visitor log, view my guests
- "add_guest"         → add a new guest, create guest pass, invite someone, new visitor, pre-register guest
- "parking"           → go to parking page, parking list, my parking, garage, parking slots, open parking section
- "rentals"           → go to rental page, browse rentals, view listings, to-let, rent an apartment, open rentals
- "service_provider"  → go to service provider page, find a service, hire someone, maids, plumbers, mechanics

=== NAVIGATION TARGETS & SYNONYMS (owner only) ===
- "residents"         → go to residents page, manage residents, view tenants, resident list, apartment owners
- "add_resident"      → create resident account, add new resident, register tenant, onboard resident
- "billing"           → go to billing section, manage bills, collect payments, financial overview, invoices
- "guest_entries"     → go to guest entries, visitor log, guests page, building visitors, check in log
- "cctv"              → go to CCTV page, surveillance, cameras, security feed, building cameras, camera monitor
- "cctv_broadcast"    → go to CCTV broadcast, start broadcast, live stream, video broadcast
- "rentals"           → go to rental page, my listings, manage to-let, available apartments
- "tickets"           → go to service tickets, maintenance requests, complaints, support tickets
- "service_provider"  → go to service providers, building staff, external services
- "nearby_spots"      → go to nearby spots, neighborhood
- "subscribe"         => subscription page, upgrade plan, buy plan, renew subscription
- "manual_guest"      => add manual guest, offline guest, manual entry

=== ACTION TYPES & SYNONYMS ===
- "sos"               → trigger SOS, send sos, emergency, help me, call police, call ambulance, danger, red alert, panic
- "pay_bill"          → pay bill, pay my due, make payment, clear balance, pay invoice, pay rent now
- "open_guest"        → open guest <name>, view details for <name>, check guest <name> [params: {"guest_name": "name"}]
- "edit_guest"        → edit guest <name>, update guest <name>, change guest <name> [params: {"guest_name": "name"}]
- "book_parking"      → book parking slot, reserve parking, get parking <slot> [params: {"slot": "5-A"}]
- "book_provider"     → book a provider, schedule service <name> at <time> [params: {"provider_name": "name", "time": "5 to 6"}]
- "send_community_msg"→ post a message, write to @user, message in community [params: {"mention": "@username", "message": "text"}]
- "delete_rental"     → delete rental <name>, remove listing, take down to-let [params: {"rental_name": "name"}]
- "delete_guest_entry"→ delete guest <name>, remove person from entries (owner only) [params: {"person_name": "name"}]
- "create_bill"       → create bill for <name>, generate invoice for <name> (owner only) [params: {"resident_name": "name"}]
- "edit_resident"     → edit resident <name>, update resident <name> (owner only) [params: {"resident_name": "name"}]
- "view_resident"     → view resident <name>, resident details <name> (owner only) [params: {"resident_name": "name"}]

=== QUERY TYPES & SYNONYMS ===
- "due_bill"          → what is my current due, pending bill amount, how much do I owe, is my bill clear
- "active_tickets"    → how many active tickets, open service requests, status of my complaints
- "pending_guests"    → how many pending guest passes, any visitors waiting, guest requests
- "my_apartment"      → which apartment am I in, my unit number, my flat details
- "my_parking"        → what is my parking slot, where do I park, my reserved space
- "total_residents"   → how many residents in building, total tenants, building population (owner only)
- "overdue_bills"     → how many overdue bills, pending invoices, unpaid rent (owner only)
- "available_parking" → how many parking slots are available, free parking spots

=== EXTENSIVE EXAMPLES ===
"go to billing" → {"intent": "navigate", "target": "billing"}
"open billing section" → {"intent": "navigate", "target": "billing"}
"trigger SOS" → {"intent": "action", "action_type": "sos"}
"book parking 5-A" → {"intent": "action", "action_type": "book_parking", "params": {"slot": "5-A"}}
"write message to @alice hello there" → {"intent": "action", "action_type": "send_community_msg", "params": {"mention": "@alice", "message": "hello there"}}
"edit resident John Doe" → {"intent": "action", "action_type": "edit_resident", "params": {"resident_name": "John Doe"}}
"view resident Jane" → {"intent": "action", "action_type": "view_resident", "params": {"resident_name": "Jane"}}
"edit guest Alice" → {"intent": "action", "action_type": "edit_guest", "params": {"guest_name": "Alice"}}
"what is my due bill" → {"intent": "query_data", "query_type": "due_bill"}
"how many parking slots available" → {"intent": "query_data", "query_type": "available_parking"}
"sign out" → {"intent": "navigate", "target": "logout"}
PROMPT;

$data = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [['text' => $query]]
        ]
    ],
    'systemInstruction' => [
        'role' => 'user',
        'parts' => [['text' => $system_instruction]]
    ],
    'generationConfig' => [
        'temperature' => 0.0,
        'responseMimeType' => 'application/json'
    ]
];

// ==========================================
// LOCAL KEYWORD FALLBACK (runs when AI fails)
// ==========================================
function local_parse_intent(string $q, int $role_id): array {
    $q = mb_strtolower(trim($q));
    
    // Helper function to check an array of regex patterns against the query
    $matches_any = function(array $patterns, string $subject) use (&$matches) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches)) {
                return true;
            }
        }
        return false;
    };

    // ---------------------------------------------------------
    // 1. ACTIONS
    // ---------------------------------------------------------

    $sos_patterns = [
        '/\bsos\b/i', '/send sos/i', '/trigger sos/i', '/emergency alert/i', 
        '/help me/i', '/i need help/i', '/call 911/i', '/call police/i', 
        '/call ambulance/i', '/medical emergency/i', '/danger/i', '/fire alarm/i',
        '/panic/i', '/red alert/i', '/save me/i', '/security threat/i'
    ];
    if ($matches_any($sos_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'sos'];
    }

    $emergency_console_patterns = [
        '/emergency console/i', '/emergency panel/i', '/crisis center/i', 
        '/safety dashboard/i', '/open emergency/i', '/security console/i'
    ];
    if ($matches_any($emergency_console_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'emergency_console'];
    }

    $pay_bill_patterns = [
        '/pay (my |the |)bill/i', '/make payment/i', '/pay now/i', 
        '/clear due/i', '/pay invoice/i', '/clear balance/i', 
        '/pay rent/i', '/pay maintenance/i', '/settle bill/i'
    ];
    if ($matches_any($pay_bill_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'pay_bill'];
    }

    $book_parking_patterns = [
        '/book parking\s*([\w\-]+)/i', '/reserve parking\s*([\w\-]+)/i', 
        '/parking slot\s*([\w\-]+)/i', '/book slot\s*([\w\-]+)/i', 
        '/reserve slot\s*([\w\-]+)/i'
    ];
    if ($matches_any($book_parking_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'book_parking', 'params' => ['slot' => trim($matches[1] ?? '')]];
    }

    $sign_out_patterns = [
        '/sign[ -]?out/i', '/log[ -]?out/i', '/exit/i', 
        '/log me off/i', '/end session/i', '/disconnect/i'
    ];
    if ($matches_any($sign_out_patterns, $q)) {
        return ['intent' => 'navigate', 'target' => 'logout'];
    }

    $open_guest_patterns = [
        '/(?:open|show|view) guest (.+)/i', '/guest detail.*? (.+)/i', 
        '/check guest (.+)/i', '/find guest (.+)/i'
    ];
    if ($matches_any($open_guest_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'open_guest', 'params' => ['guest_name' => trim($matches[1])]];
    }

    $edit_guest_patterns = [
        '/edit guest (.+)/i', '/update guest (.+)/i', '/change guest (.+)/i'
    ];
    if ($matches_any($edit_guest_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'edit_guest', 'params' => ['guest_name' => trim($matches[1])]];
    }

    $delete_rental_patterns = [
        '/delete rental\s+(.+)/i', '/remove rental\s+(.+)/i', 
        '/delete listing\s+(.+)/i', '/remove listing\s+(.+)/i', 
        '/take down to-let\s+(.+)/i'
    ];
    if ($matches_any($delete_rental_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'delete_rental', 'params' => ['rental_name' => trim($matches[1])]];
    }

    $create_bill_patterns = [
        '/create bill for\s+(.+)/i', '/generate invoice for\s+(.+)/i', 
        '/make bill for\s+(.+)/i', '/add bill for\s+(.+)/i'
    ];
    if ($matches_any($create_bill_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'create_bill', 'params' => ['resident_name' => trim($matches[1])]];
    }

    $edit_resident_patterns = [
        '/edit resident\s+(.+)/i', '/update resident\s+(.+)/i', '/change resident\s+(.+)/i'
    ];
    if ($matches_any($edit_resident_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'edit_resident', 'params' => ['resident_name' => trim($matches[1])]];
    }

    $view_resident_patterns = [
        '/view resident\s+(.+)/i', '/resident details\s+(.+)/i', '/show resident\s+(.+)/i'
    ];
    if ($matches_any($view_resident_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'view_resident', 'params' => ['resident_name' => trim($matches[1])]];
    }

    $send_message_patterns = [
        '/message to\s+(@\w+)\s*(.+)?/i', '/write to\s+(@\w+)\s*(.+)?/i', 
        '/send to\s+(@\w+)\s*(.+)?/i', '/dm\s+(@\w+)\s*(.+)?/i',
        '/post a message to\s+(@\w+)\s*(.+)?/i'
    ];
    if ($matches_any($send_message_patterns, $q)) {
        return ['intent' => 'action', 'action_type' => 'send_community_msg', 'params' => ['mention' => $matches[1], 'message' => trim($matches[2] ?? '')]];
    }

    // ---------------------------------------------------------
    // 2. DATA QUERIES
    // ---------------------------------------------------------

    $due_bill_patterns = [
        '/due bill/i', '/pending bill/i', '/how much.*(owe|due)/i', 
        '/my bill/i', '/current due/i', '/unpaid amount/i', 
        '/is my bill clear/i', '/balance due/i'
    ];
    if ($matches_any($due_bill_patterns, $q)) {
        return ['intent' => 'query_data', 'query_type' => 'due_bill'];
    }

    $active_tickets_patterns = [
        '/active ticket/i', '/open ticket/i', '/service request/i', 
        '/my request/i', '/status of my complaint/i', '/unresolved ticket/i'
    ];
    if ($matches_any($active_tickets_patterns, $q)) {
        return ['intent' => 'query_data', 'query_type' => 'active_tickets'];
    }

    $pending_guests_patterns = [
        '/pending guest/i', '/visitor request/i', '/guest request/i', 
        '/visitors waiting/i', '/unapproved guest/i'
    ];
    if ($matches_any($pending_guests_patterns, $q)) {
        return ['intent' => 'query_data', 'query_type' => 'pending_guests'];
    }

    $my_apartment_patterns = [
        '/my apartment/i', '/which (flat|unit|apt)/i', '/my unit/i', 
        '/my flat/i', '/apartment details/i', '/where do i live/i'
    ];
    if ($matches_any($my_apartment_patterns, $q)) {
        return ['intent' => 'query_data', 'query_type' => 'my_apartment'];
    }

    $my_parking_patterns = [
        '/my parking/i', '/parking slot/i', '/where do i park/i', 
        '/reserved space/i', '/my garage slot/i'
    ];
    if ($matches_any($my_parking_patterns, $q)) {
        return ['intent' => 'query_data', 'query_type' => 'my_parking'];
    }

    $available_parking_patterns = [
        '/available parking/i', '/free parking/i', '/parking slots available/i'
    ];
    if ($matches_any($available_parking_patterns, $q)) {
        return ['intent' => 'query_data', 'query_type' => 'available_parking'];
    }

    if ($role_id == 1) {
        $total_residents_patterns = [
            '/total resident/i', '/how many resident/i', '/resident count/i', 
            '/total tenant/i', '/building population/i'
        ];
        if ($matches_any($total_residents_patterns, $q)) {
            return ['intent' => 'query_data', 'query_type' => 'total_residents'];
        }

        $overdue_bills_patterns = [
            '/overdue bill/i', '/overdue invoice/i', '/unpaid rent/i', 
            '/defaulted bill/i', '/late payment/i'
        ];
        if ($matches_any($overdue_bills_patterns, $q)) {
            return ['intent' => 'query_data', 'query_type' => 'overdue_bills'];
        }
    }

    // ---------------------------------------------------------
    // 3. NAVIGATION (Massive Keyword Map)
    // ---------------------------------------------------------
    
    // We iterate through an associative array where the key is the target,
    // and the value is an array of all possible natural language synonyms.
    $nav_targets = [
        'dashboard' => [
            'dashboard', 'home page', 'go home', 'homepage', 'main page', 
            'start page', 'control panel', 'index', 'open dashboard', 
            'take me home', 'front page', 'main menu'
        ],
        'profile' => [
            'profile', 'account setting', 'my account', 'view profile', 
            'edit profile', 'user setting', 'personal info'
        ],
        'billing' => [
            'billing', 'bill section', 'invoice', 'bills', 'payment page', 
            'financial', 'accounts', 'my dues', 'open billing', 
            'show me billing', 'statement', 'payment history', 'financial page'
        ],
        'tickets' => [
            'ticket', 'service request', 'my request', 'maintenance', 
            'support', 'complaint', 'help desk', 'issue tracker'
        ],
        'notifications' => [
            'notification', 'alert', 'recent activity', 'bell icon', 
            'whats new', 'check notification'
        ],
        'community_hub' => [
            'community', 'hub', 'post', 'feed', 'chat', 'conversation', 
            'forum', 'message board', 'open community', 'social'
        ],
        'nearby_spots' => [
            'nearby', 'places near', 'emergency service', 'local area', 
            'map', 'surrounding', 'neighborhood', 'local map'
        ],
        'parking' => [
            'parking page', 'parking section', 'parking', 'garage', 
            'parking slot', 'open parking'
        ],
        'rentals' => [
            'rental', 'listing', 'browse rental', 'to-let', 
            'rent an apartment', 'open rental', 'available flat'
        ],
        'service_provider' => [
            'service provider', 'find provider', 'provider', 'hire someone', 
            'maid', 'plumber', 'mechanic', 'electrician'
        ],
        'emergency_console' => [
            'emergency console', 'emergency panel', 'crisis center', 
            'safety dashboard', 'open emergency', 'security console'
        ],
    ];

    if ($role_id == 1) { // Owner specifics
        $nav_targets['residents'] = [
            'resident page', 'manage resident', 'residents', 'view tenant', 
            'resident list', 'apartment owner'
        ];
        $nav_targets['add_resident'] = [
            'add resident', 'create resident', 'new resident', 'register tenant', 
            'onboard resident'
        ];
        $nav_targets['guest_entries'] = [
            'guest entr', 'entry log', 'visitor log', 'building visitor', 
            'check in log', 'guests page', 'visitor page'
        ];
        $nav_targets['cctv'] = [
            'cctv', 'surveillance', 'camera', 'security feed', 
            'building camera', 'video feed', 'monitor'
        ];
        $nav_targets['cctv_broadcast'] = [
            'broadcast', 'cctv stream', 'live stream', 'start broadcast', 'video broadcast'
        ];
        $nav_targets['subscribe'] = [
            'subscription', 'upgrade plan', 'buy plan', 'renew subscription', 'pricing'
        ];
        $nav_targets['manual_guest'] = [
            'manual guest', 'offline guest', 'add offline visitor'
        ];
    } else { // Resident specifics
        $nav_targets['guest_passes'] = [
            'guest pass', 'visitor', 'guests page', 'view my guest', 
            'visitor log'
        ];
        $nav_targets['add_guest'] = [
            'add guest', 'new guest', 'create guest', 'invite someone', 
            'new visitor', 'pre-register guest', 'invite guest'
        ];
    }

    // Loop through all nav targets and their synonyms
    foreach ($nav_targets as $target => $synonyms) {
        foreach ($synonyms as $synonym) {
            // Use word boundaries \b to ensure we match whole words/phrases securely
            // e.g. /open dashboard/i
            $pattern = '/' . preg_quote($synonym, '/') . '/i';
            // We also check for action words combined with the synonym
            // like "go to", "open", "show me", "take me to"
            $action_patterns = [
                '/(?:go to|open|show me|take me to|view|navigate to|open up|bring up)\s+' . preg_quote($synonym, '/') . '/i',
                $pattern // also just match the raw keyword
            ];
            
            if ($matches_any($action_patterns, $q)) {
                return ['intent' => 'navigate', 'target' => $target];
            }
        }
    }

    // ---------------------------------------------------------
    // 4. CONVERSATIONAL (Fallback)
    // ---------------------------------------------------------
    
    $identity_patterns = [
        '/who are you/i', '/your name/i', '/what are you/i', 
        '/tell me about yourself/i', '/introduce yourself/i'
    ];
    if ($matches_any($identity_patterns, $q)) {
        return ['intent' => 'conversational', 'response_text' => "I am Nibash AI, your personal building assistant. I can help you navigate pages, check your bills, or book a parking slot!"];
    }

    $greeting_patterns = [
        '/^(hi|hello|hey|greetings|good morning|good afternoon|good evening|yo)/i'
    ];
    if ($matches_any($greeting_patterns, $q)) {
        return ['intent' => 'conversational', 'response_text' => "Hello there! I am Nibash AI. How can I help you today?"];
    }

    $wellness_patterns = [
        '/how are you/i', '/how are you doing/i', '/how do you do/i', 
        '/whats up/i', '/hows it going/i'
    ];
    if ($matches_any($wellness_patterns, $q)) {
        return ['intent' => 'conversational', 'response_text' => "I'm just a computer program, but I'm doing great! How can I assist you with your Nibash platform?"];
    }

    return ['intent' => 'unknown'];
}

// ==========================================
// CALL GEMINI API
// ==========================================
$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $gemini_api_key);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10s max wait

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// If Gemini fails → fall back to local keyword parser
if ($http_status !== 200 || !$response) {
    $intent_data = local_parse_intent($query, $role_id);
} else {
    $res_data    = json_decode($response, true);
    $ai_text     = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    $intent_data = json_decode($ai_text, true);
    if (!$intent_data) {
        $intent_data = local_parse_intent($query, $role_id);
    }
}

$intent = $intent_data['intent'] ?? 'unknown';


// ==========================================
// HANDLE: NAVIGATE
// ==========================================
if ($intent === 'navigate') {
    $target = $intent_data['target'] ?? '';
    $folder = ($role_id == 1) ? 'owner' : 'resident';
    $base   = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/Nibash/';

    // Shared routes for both roles
    $routes = [
        'dashboard'       => $base . "$folder/dashboard.php",
        'profile'         => $base . "$folder/profile.php",
        'billing'         => $base . "$folder/billing.php",
        'tickets'         => $base . "$folder/tickets.php",
        'notifications'   => $base . "notifications_history.php",
        'community_hub'   => $base . "community_hub.php",
        'nearby_spots'    => $base . "essentials/index.php",
        'logout'          => $base . "logout.php",
        'service_provider'=> $base . "essentials/provider.php",
        'rentals'         => $base . "rentals/browse.php",
        'emergency_console'=> $base . "emergency_console.php",
    ];

    // Resident-specific routes
    if ($role_id == 2) {
        $routes['guest_passes'] = $base . "resident/guest_passes.php";
        $routes['add_guest']    = $base . "resident/guest_passes.php?action=add";
        $routes['parking']      = $base . "resident/parking.php";
    }

    // Owner-specific routes
    if ($role_id == 1) {
        $routes['residents']     = $base . "owner/residents.php";
        $routes['add_resident']  = $base . "owner/add_resident.php";
        $routes['guest_entries'] = $base . "owner/guest_entries.php";
        $routes['cctv']          = $base . "owner/cctv_surveillance.php";
        $routes['cctv_broadcast']= $base . "owner/cctv_broadcast.php";
        $routes['subscribe']     = $base . "owner/subscribe.php";
        $routes['manual_guest']  = $base . "owner/manual_guest.php";
    }

    $url  = $routes[$target] ?? $base . "$folder/dashboard.php";
    $name = ucwords(str_replace('_', ' ', $target));

    echo json_encode([
        'success'     => true,
        'type'        => 'navigation',
        'target_name' => $name,
        'url'         => $url
    ]);
    exit;
}

// ==========================================
// HANDLE: ACTION
// ==========================================
if ($intent === 'action') {
    $action_type = $intent_data['action_type'] ?? '';
    $params      = $intent_data['params'] ?? [];
    $folder      = ($role_id == 1) ? 'owner' : 'resident';
    $base        = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/Nibash/';

    switch ($action_type) {

        case 'sos':
            // Tell the frontend to fire the real AJAX SOS endpoint
            $base_url = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://localhost/Nibash';
            echo json_encode([
                'success'    => true,
                'type'       => 'action',
                'action'     => 'sos',
                'sos_endpoint' => $base_url . '/api/sos_trigger.php',
                'response_text' => '🚨 Triggering SOS — sending emergency alerts to your contacts...'
            ]);
            break;

        case 'emergency_console':
            echo json_encode([
                'success'     => true,
                'type'        => 'navigation',
                'target_name' => 'Emergency Console',
                'url'         => $base . 'emergency_console.php',
                'response_text' => 'Opening <b>Emergency Console</b>...'
            ]);
            break;

        case 'pay_bill':
            echo json_encode([
                'success'     => true,
                'type'        => 'navigation',
                'target_name' => 'Payment',
                'url'         => $base . 'payment_integration/payment_init.php',
                'response_text' => 'Redirecting you to the <b>Payment</b> page...'
            ]);
            break;

        case 'open_guest':
            $guest_name = mysqli_real_escape_string($conn, $params['guest_name'] ?? '');
            if (!$guest_name) {
                echo json_encode(['success' => false, 'error' => 'Please specify a guest name to open.']);
                exit;
            }
            // Try to find the guest ID
            $gq = mysqli_query($conn, "SELECT id, full_name FROM guests WHERE full_name LIKE '%$guest_name%' LIMIT 1");
            if ($gq && mysqli_num_rows($gq) > 0) {
                $gdata = mysqli_fetch_assoc($gq);
                echo json_encode([
                    'success'     => true,
                    'type'        => 'navigation',
                    'target_name' => htmlspecialchars($gdata['full_name']) . "'s Details",
                    'url'         => $base . 'guest_details.php?id=' . $gdata['id'],
                    'response_text' => "Opening details for <b>" . htmlspecialchars($gdata['full_name']) . "</b>..."
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'type' => 'action',
                    'response_text' => "Sorry, I couldn't find a guest named <b>" . htmlspecialchars($guest_name) . "</b>."
                ]);
            }
            break;

        case 'edit_guest':
            $guest_name = mysqli_real_escape_string($conn, $params['guest_name'] ?? '');
            if (!$guest_name) {
                echo json_encode(['success' => false, 'error' => 'Please specify a guest name to edit.']);
                exit;
            }
            $gq = mysqli_query($conn, "SELECT id, full_name FROM guests WHERE full_name LIKE '%$guest_name%' LIMIT 1");
            if ($gq && mysqli_num_rows($gq) > 0) {
                $gdata = mysqli_fetch_assoc($gq);
                echo json_encode([
                    'success'     => true,
                    'type'        => 'navigation',
                    'target_name' => 'Edit ' . htmlspecialchars($gdata['full_name']),
                    'url'         => $base . 'resident/edit_guest.php?id=' . $gdata['id'],
                    'response_text' => "Opening edit form for <b>" . htmlspecialchars($gdata['full_name']) . "</b>..."
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'type' => 'action',
                    'response_text' => "Sorry, I couldn't find a guest named <b>" . htmlspecialchars($guest_name) . "</b>."
                ]);
            }
            break;

        case 'edit_resident':
            if ($role_id != 1) {
                echo json_encode(['success' => true, 'type' => 'data', 'response_text' => '❌ Only owners can edit residents.']);
                break;
            }
            $resident_name = mysqli_real_escape_string($conn, $params['resident_name'] ?? '');
            $rq = mysqli_query($conn, "SELECT u.id, u.full_name FROM users u JOIN apartment_assignments aa ON u.id = aa.user_id JOIN apartments a ON aa.apt_id = a.id WHERE a.building_id = '$building_id' AND u.full_name LIKE '%$resident_name%' LIMIT 1");
            if ($rq && mysqli_num_rows($rq) > 0) {
                $rdata = mysqli_fetch_assoc($rq);
                echo json_encode([
                    'success'     => true,
                    'type'        => 'navigation',
                    'target_name' => 'Edit ' . htmlspecialchars($rdata['full_name']),
                    'url'         => $base . 'owner/edit_resident.php?id=' . $rdata['id'],
                    'response_text' => "Opening edit form for resident <b>" . htmlspecialchars($rdata['full_name']) . "</b>..."
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'type' => 'action',
                    'response_text' => "Sorry, I couldn't find a resident named <b>" . htmlspecialchars($resident_name) . "</b>."
                ]);
            }
            break;

        case 'view_resident':
            if ($role_id != 1) {
                echo json_encode(['success' => true, 'type' => 'data', 'response_text' => '❌ Only owners can view resident details.']);
                break;
            }
            $resident_name = mysqli_real_escape_string($conn, $params['resident_name'] ?? '');
            $rq = mysqli_query($conn, "SELECT u.id, u.full_name FROM users u JOIN apartment_assignments aa ON u.id = aa.user_id JOIN apartments a ON aa.apt_id = a.id WHERE a.building_id = '$building_id' AND u.full_name LIKE '%$resident_name%' LIMIT 1");
            if ($rq && mysqli_num_rows($rq) > 0) {
                $rdata = mysqli_fetch_assoc($rq);
                echo json_encode([
                    'success'     => true,
                    'type'        => 'navigation',
                    'target_name' => htmlspecialchars($rdata['full_name']) . "'s Details",
                    'url'         => $base . 'owner/view_resident.php?id=' . $rdata['id'],
                    'response_text' => "Opening details for resident <b>" . htmlspecialchars($rdata['full_name']) . "</b>..."
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'type' => 'action',
                    'response_text' => "Sorry, I couldn't find a resident named <b>" . htmlspecialchars($resident_name) . "</b>."
                ]);
            }
            break;

        case 'book_parking':
            $slot = htmlspecialchars($params['slot'] ?? '');
            $slot_param = urlencode($slot);
            echo json_encode([
                'success'     => true,
                'type'        => 'navigation',
                'target_name' => 'Parking Booking',
                'url'         => $base . "resident/parking.php?request_slot={$slot_param}",
                'response_text' => "Opening parking page to request slot <b>{$slot}</b>..."
            ]);
            break;

        case 'book_provider':
            $prov = htmlspecialchars($params['provider_name'] ?? '');
            $time = htmlspecialchars($params['time'] ?? '');
            $prov_param = urlencode($prov);
            $time_param = urlencode($time);
            echo json_encode([
                'success'     => true,
                'type'        => 'navigation',
                'target_name' => 'Service Provider',
                'url'         => $base . "essentials/provider.php?book={$prov_param}&time={$time_param}",
                'response_text' => "Booking <b>{$prov}</b>" . ($time ? " for <b>{$time}</b>" : "") . ". Opening service provider page..."
            ]);
            break;

        case 'send_community_msg':
            $mention = htmlspecialchars($params['mention'] ?? '');
            $message = htmlspecialchars($params['message'] ?? '');
            $msg_param = urlencode($message);
            $mention_param = urlencode($mention);
            echo json_encode([
                'success'     => true,
                'type'        => 'navigation',
                'target_name' => 'Community Hub',
                'url'         => $base . "community_hub.php?mention={$mention_param}&message={$msg_param}",
                'response_text' => "Opening <b>Community Hub</b> to post your message" . ($mention ? " to <b>{$mention}</b>" : "") . "..."
            ]);
            break;

        case 'delete_rental':
            $rental_name = mysqli_real_escape_string($conn, $params['rental_name'] ?? '');
            // Find rental owned by this user
            $rq = mysqli_query($conn, "SELECT id, title FROM rental_listings WHERE user_id = '$user_id' AND title LIKE '%$rental_name%' LIMIT 1");
            if ($rq && mysqli_num_rows($rq) > 0) {
                $rental = mysqli_fetch_assoc($rq);
                $rid = $rental['id'];
                mysqli_query($conn, "DELETE FROM rental_listings WHERE id = '$rid' AND user_id = '$user_id'");
                echo json_encode([
                    'success'       => true,
                    'type'          => 'data',
                    'response_text' => "✅ Rental listing <b>" . htmlspecialchars($rental['title']) . "</b> has been deleted."
                ]);
            } else {
                echo json_encode([
                    'success'       => true,
                    'type'          => 'data',
                    'response_text' => "❌ No rental listing named <b>" . htmlspecialchars($rental_name) . "</b> found for your account."
                ]);
            }
            break;

        case 'delete_guest_entry':
            if ($role_id != 1) {
                echo json_encode(['success' => true, 'type' => 'data', 'response_text' => '❌ Only owners can delete guest entries.']);
                break;
            }
            $person_name = mysqli_real_escape_string($conn, $params['person_name'] ?? '');
            $gq = mysqli_query($conn, "SELECT id, name FROM guest_entries WHERE building_id = '$building_id' AND name LIKE '%$person_name%' LIMIT 1");
            if ($gq && mysqli_num_rows($gq) > 0) {
                $guest = mysqli_fetch_assoc($gq);
                $gid = $guest['id'];
                mysqli_query($conn, "DELETE FROM guest_entries WHERE id = '$gid'");
                echo json_encode([
                    'success'       => true,
                    'type'          => 'data',
                    'response_text' => "✅ Guest entry for <b>" . htmlspecialchars($guest['name']) . "</b> has been deleted."
                ]);
            } else {
                echo json_encode([
                    'success'       => true,
                    'type'          => 'data',
                    'response_text' => "❌ No guest entry found matching <b>" . htmlspecialchars($person_name) . "</b>."
                ]);
            }
            break;

        case 'create_bill':
            if ($role_id != 1) {
                echo json_encode(['success' => true, 'type' => 'data', 'response_text' => '❌ Only owners can create bills.']);
                break;
            }
            $resident_name = htmlspecialchars($params['resident_name'] ?? '');
            $name_param    = urlencode($resident_name);
            echo json_encode([
                'success'     => true,
                'type'        => 'navigation',
                'target_name' => 'Create Bill',
                'url'         => "../owner/billing.php?create=1&resident={$name_param}",
                'response_text' => "Opening billing form for <b>{$resident_name}</b>..."
            ]);
            break;

        default:
            echo json_encode([
                'success'       => true,
                'type'          => 'data',
                'response_text' => "I understood you want to do something, but I'm not sure how to handle that action yet."
            ]);
    }
    exit;
}

// ==========================================
// HANDLE: QUERY_DATA
// ==========================================
if ($intent === 'query_data') {
    $query_type        = $intent_data['query_type'] ?? '';
    $safe_building_id  = mysqli_real_escape_string($conn, $building_id ?? '');
    $response_text     = "I couldn't find data for that request.";

    if ($query_type === 'due_bill') {
        $q    = mysqli_query($conn, "SELECT SUM(total_amount) as total_due FROM bills WHERE resident_id = '$user_id' AND status IN ('Pending', 'Partially Paid', 'Overdue')");
        $row  = mysqli_fetch_assoc($q);
        $total = $row['total_due'] ?? 0;
        $response_text = "Your current total pending due is <b class=\"text-red-400\">৳" . number_format($total, 2) . "</b>.";
    }
    elseif ($query_type === 'active_tickets') {
        $q    = mysqli_query($conn, "SELECT COUNT(*) as c FROM service_requests WHERE user_id = '$user_id' AND status != 'Resolved'");
        $row  = mysqli_fetch_assoc($q);
        $count = $row['c'] ?? 0;
        $response_text = "You have <b>{$count}</b> active service request(s).";
    }
    elseif ($query_type === 'pending_guests') {
        if ($safe_building_id) {
            $q    = mysqli_query($conn, "SELECT COUNT(*) as c FROM visit_requests vr JOIN apartments a ON vr.apt_id = a.id WHERE a.building_id = '$safe_building_id' AND vr.status = 'Pending'");
            $row  = mysqli_fetch_assoc($q);
            $count = $row['c'] ?? 0;
            $response_text = "There are <b>{$count}</b> pending guest request(s) in your building.";
        } else {
            $response_text = "Building ID not found for your account.";
        }
    }
    elseif ($query_type === 'my_apartment') {
        $q = mysqli_query($conn, "SELECT a.apt_number, b.building_name AS apartment_name FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id LEFT JOIN buildings b ON a.building_id = b.id WHERE aa.user_id = '$user_id' LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            $response_text = "You are assigned to <b>" . htmlspecialchars($row['apartment_name'] . ' ' . $row['apt_number']) . "</b>.";
        } else {
            $response_text = "I couldn't find your apartment assignment.";
        }
    }
    elseif ($query_type === 'my_parking') {
        $q = mysqli_query($conn, "SELECT slot_number FROM parking_bookings WHERE user_id = '$user_id' AND status = 'Active' LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            $response_text = "Your active parking slot is <b>" . htmlspecialchars($row['slot_number']) . "</b>.";
        } else {
            $response_text = "You don't have an active parking slot booking.";
        }
    }
    elseif ($query_type === 'total_residents') {
        if ($safe_building_id) {
            $q    = mysqli_query($conn, "SELECT COUNT(DISTINCT aa.user_id) as c FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE a.building_id = '$safe_building_id'");
            $row  = mysqli_fetch_assoc($q);
            $count = $row['c'] ?? 0;
            $response_text = "Your building has <b>{$count}</b> registered resident(s).";
        } else {
            $response_text = "Building information not found.";
        }
    }
    elseif ($query_type === 'available_parking') {
        if ($safe_building_id) {
            $q = mysqli_query($conn, "SELECT COUNT(*) as c FROM parking_slots WHERE building_id = '$safe_building_id' AND status = 'Available'");
            $row = mysqli_fetch_assoc($q);
            $count = $row['c'] ?? 0;
            $response_text = "There are <b>{$count}</b> available parking slot(s) in your building.";
        } else {
            $response_text = "Building information not found.";
        }
    }
    elseif ($query_type === 'overdue_bills') {
        if ($safe_building_id) {
            $q    = mysqli_query($conn, "SELECT COUNT(*) as c FROM bills b JOIN apartment_assignments aa ON b.resident_id = aa.user_id JOIN apartments a ON aa.apt_id = a.id WHERE a.building_id = '$safe_building_id' AND b.status = 'Overdue'");
            $row  = mysqli_fetch_assoc($q);
            $count = $row['c'] ?? 0;
            $response_text = "There are <b>{$count}</b> overdue bill(s) in your building.";
        } else {
            $response_text = "Building information not found.";
        }
    }

    echo json_encode([
        'success'       => true,
        'type'          => 'data',
        'response_text' => $response_text
    ]);
    exit;
}

// ==========================================
// HANDLE: CONVERSATIONAL
// ==========================================
if ($intent === 'conversational') {
    echo json_encode([
        'success'       => true,
        'type'          => 'data',
        'response_text' => $intent_data['response_text'] ?? "Hello! I am Nibash AI, always here to help you."
    ]);
    exit;
}

// ==========================================
// FALLBACK: UNKNOWN
// ==========================================
echo json_encode([
    'success'       => true,
    'type'          => 'unknown',
    'response_text' => "I didn't quite understand that. You can ask me to navigate pages, perform actions like booking a parking slot, or check your data like due bills."
]);
exit;