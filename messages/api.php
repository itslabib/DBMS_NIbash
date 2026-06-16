<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'search_users') {
    $q = mysqli_real_escape_string($conn, trim($_GET['q'] ?? ''));
    if (strlen($q) < 3) {
        echo json_encode([]);
        exit();
    }
    
    $query = "SELECT u.id, p.full_name, p.profile_image, p.phone, u.email 
              FROM users u 
              LEFT JOIN user_profiles p ON u.id = p.user_id 
              WHERE (u.email = '$q' OR p.phone = '$q' OR p.full_name LIKE '%$q%') 
              AND u.id != $user_id 
              LIMIT 10";
              
    $res = mysqli_query($conn, $query);
    $users = [];
    while($row = mysqli_fetch_assoc($res)) {
        $users[] = $row;
    }
    echo json_encode($users);
    exit();
}

if ($action === 'get_conversations') {
    $query = "
        SELECT 
            CASE 
                WHEN sender_id = $user_id THEN receiver_id 
                ELSE sender_id 
            END as contact_id,
            MAX(created_at) as last_msg_time,
            (SELECT message FROM personal_messages pm2 WHERE 
                (pm2.sender_id = contact_id AND pm2.receiver_id = $user_id) 
                OR (pm2.sender_id = $user_id AND pm2.receiver_id = contact_id)
                ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT is_read FROM personal_messages pm2 WHERE 
                pm2.sender_id = contact_id AND pm2.receiver_id = $user_id
                ORDER BY created_at DESC LIMIT 1) as is_last_read
        FROM personal_messages
        WHERE sender_id = $user_id OR receiver_id = $user_id
        GROUP BY contact_id
        ORDER BY last_msg_time DESC
    ";
    
    $res = mysqli_query($conn, $query);
    $conversations = [];
    while($row = mysqli_fetch_assoc($res)) {
        $cid = $row['contact_id'];
        $u_res = mysqli_query($conn, "SELECT p.full_name, p.profile_image FROM user_profiles p WHERE p.user_id = $cid");
        $u_info = mysqli_fetch_assoc($u_res);
        $row['full_name'] = $u_info['full_name'] ?? 'Unknown User';
        $row['profile_image'] = $u_info['profile_image'] ?? '';
        
        $unread_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM personal_messages WHERE sender_id = $cid AND receiver_id = $user_id AND is_read = 0");
        $row['unread_count'] = mysqli_fetch_assoc($unread_q)['c'] ?? 0;
        
        $conversations[] = $row;
    }
    echo json_encode($conversations);
    exit();
}

if ($action === 'get_messages') {
    $contact_id = (int)($_GET['contact_id'] ?? 0);
    
    mysqli_query($conn, "UPDATE personal_messages SET is_read = 1 WHERE sender_id = $contact_id AND receiver_id = $user_id AND is_read = 0");
    
    $query = "SELECT * FROM personal_messages 
              WHERE (sender_id = $user_id AND receiver_id = $contact_id) 
                 OR (sender_id = $contact_id AND receiver_id = $user_id) 
              ORDER BY created_at ASC";
              
    $res = mysqli_query($conn, $query);
    $messages = [];
    while($row = mysqli_fetch_assoc($res)) {
        $messages[] = $row;
    }
    echo json_encode($messages);
    exit();
}

if ($action === 'send_message') {
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));
    
    if ($receiver_id > 0 && !empty($message)) {
        mysqli_query($conn, "INSERT INTO personal_messages (sender_id, receiver_id, message) VALUES ($user_id, $receiver_id, '$message')");
        
        $sender_q = mysqli_query($conn, "SELECT full_name FROM user_profiles WHERE user_id = $user_id");
        $sender_name = mysqli_fetch_assoc($sender_q)['full_name'] ?? 'Someone';
        
        $title = "New Message";
        $notif_msg = "$sender_name sent you a message.";
        $link = "messages/index.php?user_id=$user_id";
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ($receiver_id, '$title', '$notif_msg', '$link')");
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

if ($action === 'get_user_details') {
    $target_id = (int)($_GET['user_id'] ?? 0);
    $u_res = mysqli_query($conn, "SELECT p.full_name, p.profile_image, u.email, p.phone FROM user_profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = $target_id");
    if($row = mysqli_fetch_assoc($u_res)) {
        $row['id'] = $target_id;
        echo json_encode($row);
    } else {
        echo json_encode(null);
    }
    exit();
}

echo json_encode(['error' => 'invalid action']);
?>
