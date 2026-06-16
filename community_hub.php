<?php
session_start();
require_once 'includes/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR);

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

// Update last viewed time
@mysqli_query($conn, "UPDATE user_profiles SET last_viewed_community = NOW() WHERE user_id = '$user_id'");

// Fetch user profile
$user_name = "User";
$user_image = "";
try {
    $q = "SELECT full_name, profile_image FROM user_profiles WHERE user_id = '$user_id'";
    $res = mysqli_query($conn, $q);
    if ($res && mysqli_num_rows($res) > 0) {
        $p = mysqli_fetch_assoc($res);
        $user_name = $p['full_name'];
        $user_image = $p['profile_image'];
    }
} catch (Exception $e) {}

// Fetch all categories
$categories = [];
$cat_res = mysqli_query($conn, "SELECT * FROM community_categories");
if ($cat_res) {
    while ($row = mysqli_fetch_assoc($cat_res)) {
        $categories[] = $row;
    }
}

// Fetch user's apt_id AND building_id
$user_apt_id = 0;
$user_building_id = '';
$apt_q = "SELECT aa.apt_id, a.building_id 
          FROM apartment_assignments aa 
          JOIN apartments a ON a.id = aa.apt_id 
          WHERE aa.user_id = '$user_id' AND aa.is_active = 1 
          LIMIT 1";
$apt_res = mysqli_query($conn, $apt_q);
if ($apt_res && mysqli_num_rows($apt_res) > 0) {
    $apt_row = mysqli_fetch_assoc($apt_res);
    $user_apt_id    = $apt_row['apt_id'];
    $user_building_id = $apt_row['building_id'];
} else {
    // Fallback: owners/admins not in apartment_assignments — use the first apartment they own
    $fallback_res = mysqli_query($conn, "SELECT id, building_id FROM apartments LIMIT 1");
    if ($fallback_res && mysqli_num_rows($fallback_res) > 0) {
        $fb_row = mysqli_fetch_assoc($fallback_res);
        $user_apt_id      = $fb_row['id'];
        $user_building_id = $fb_row['building_id'];
    }
}

function mysqli_real_escape_with_htmlspecialchars($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars($data));
}

function notifyMentions($content, $conn, $actor_id, $actor_name, $post_id) {
    preg_match_all('/@([a-zA-Z0-9_.-]+)/', $content, $matches);
    if (!empty($matches[1])) {
        $unique_mentions = array_unique($matches[1]);
        foreach ($unique_mentions as $mention_str) {
            $name_like = mysqli_real_escape_string($conn, str_replace('_', ' ', $mention_str));
            $where_clause = "p.full_name = '$name_like'";
            if (is_numeric($mention_str)) {
                $where_clause .= " OR u.id = '$mention_str'";
            }
            $q = "SELECT u.id FROM users u JOIN user_profiles p ON u.id = p.user_id WHERE ($where_clause) LIMIT 1";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                $target_user_id = $row['id'];
                if ($target_user_id != $actor_id) {
                    $title = "You were mentioned";
                    $message = "$actor_name mentioned you in the Community Hub.";
                    $link = "community_hub.php#post-$post_id";
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ($target_user_id, '$title', '$message', '$link')");
                }
            }
        }
    }
}

function notifyReply($post_id, $conn, $actor_id, $actor_name) {
    $q = "SELECT user_id, title FROM community_posts WHERE id = $post_id LIMIT 1";
    $res = mysqli_query($conn, $q);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $target_user_id = $row['user_id'];
        $post_title = $row['title'];
        if ($target_user_id != $actor_id) {
            $title = "New Reply";
            $message = "$actor_name replied to your post: \"$post_title\"";
            $link = "community_hub.php#post-$post_id";
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ($target_user_id, '$title', '$message', '$link')");
        }
    }
}

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'post_notice' && isset($_POST['title'], $_POST['content'])) {
        $title = mysqli_real_escape_with_htmlspecialchars($conn, $_POST['title']);
        $content = mysqli_real_escape_with_htmlspecialchars($conn, $_POST['content']);
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 1; 
        
        // Only owners can pin
        $is_pinned = 0;
        if ($role_id == 1 && isset($_POST['is_pinned'])) {
            $is_pinned = 1;
        }
        
        // Handle File Uploads
        $image_paths = [];
        if (!empty($_FILES['files']['name'][0])) {
            $target_dir = "assets/uploads/community/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES['files']['name'][$key]));
                    $target_file = $target_dir . $file_name;
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $image_paths[] = $target_file;
                    }
                }
            }
        }
        $safe_building_id = mysqli_real_escape_string($conn, $user_building_id);
        $insert = "INSERT INTO community_posts (user_id, apt_id, category_id, title, content, is_pinned, status) 
                   VALUES ('$user_id', '$user_apt_id', '$category_id', '$title', '$content', '$is_pinned', 'published')";
        
        if (mysqli_query($conn, $insert)) {
            $post_id = mysqli_insert_id($conn);
            
            // Insert images into community_post_images
            if (!empty($image_paths)) {
                foreach ($image_paths as $path) {
                    $safe_path = mysqli_real_escape_string($conn, $path);
                    mysqli_query($conn, "INSERT INTO community_post_images (post_id, image_path) VALUES ('$post_id', '$safe_path')");
                }
            }

            notifyMentions($_POST['content'], $conn, $user_id, $user_name, $post_id);
            header("Location: community_hub.php?success=posted");
            exit();
        } else {
            header("Location: community_hub.php?error=db_failed&msg=" . urlencode(mysqli_error($conn)));
            exit();
        }
    }
    elseif ($action === 'edit_notice' && isset($_POST['post_id'], $_POST['title'], $_POST['content'])) {
        $post_id = (int)$_POST['post_id'];
        $title = mysqli_real_escape_with_htmlspecialchars($conn, $_POST['title']);
        $content = mysqli_real_escape_with_htmlspecialchars($conn, $_POST['content']);
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 1;
        
        $is_pinned = 0;
        if ($role_id == 1 && isset($_POST['is_pinned'])) {
            $is_pinned = 1;
        }

        $check = mysqli_query($conn, "SELECT user_id FROM community_posts WHERE id = $post_id");
        if ($check && mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            if ($row['user_id'] == $user_id) {
                if ($role_id == 1) {
                    $update = "UPDATE community_posts SET title='$title', content='$content', category_id='$category_id', is_pinned='$is_pinned' WHERE id = $post_id";
                } else {
                    $update = "UPDATE community_posts SET title='$title', content='$content', category_id='$category_id' WHERE id = $post_id";
                }
                mysqli_query($conn, $update);
                notifyMentions($_POST['content'], $conn, $user_id, $user_name, $post_id);
                header("Location: community_hub.php?success=edited");
                exit();
            }
        }
        header("Location: community_hub.php?error=unauthorized_edit");
        exit();
    }
    elseif ($action === 'post_reply' && isset($_POST['post_id'], $_POST['content'])) {
        $post_id = (int)$_POST['post_id'];
        $content = mysqli_real_escape_with_htmlspecialchars($conn, $_POST['content']);
        $safe_building_id = mysqli_real_escape_string($conn, $user_building_id);
        
        $image_path = '';
        if (!empty($_FILES['file']['name'])) {
            $target_dir = "assets/uploads/community/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES['file']['name']));
                $target_file = $target_dir . $file_name;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }

        $insert = "INSERT INTO community_comments (post_id, user_id, apt_id, content, image_path) 
                   VALUES ('$post_id', '$user_id', '$user_apt_id', '$content', '$image_path')";
        
        if (mysqli_query($conn, $insert)) {
            notifyReply($post_id, $conn, $user_id, $user_name);
            notifyMentions($_POST['content'], $conn, $user_id, $user_name, $post_id);
            header("Location: community_hub.php?success=reply_posted");
            exit();
        } else {
            header("Location: community_hub.php?error=db_failed&msg=" . urlencode(mysqli_error($conn)));
            exit();
        }
    }
    elseif ($action === 'delete_notice' && isset($_POST['post_id'])) {
        $post_id = (int)$_POST['post_id'];
        
        $check = mysqli_query($conn, "SELECT user_id FROM community_posts WHERE id = $post_id");
        if ($check && mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            if ($row['user_id'] == $user_id || $role_id == 1) {
                mysqli_query($conn, "UPDATE community_posts SET status='archived' WHERE id = $post_id");
                header("Location: community_hub.php?success=deleted");
                exit();
            }
        }
        header("Location: community_hub.php?error=unauthorized_delete");
        exit();
    }
}

// Function to resolve @mentions
function resolveMentions($content, $conn) {
    preg_match_all('/@([a-zA-Z0-9_.-]+)/', $content, $matches);
    if (!empty($matches[1])) {
        $unique_mentions = array_unique($matches[1]);
        foreach ($unique_mentions as $mention_str) {
            $name_like = mysqli_real_escape_string($conn, str_replace('_', ' ', $mention_str));
            $where_clause = "p.full_name = '$name_like'";
            if (is_numeric($mention_str)) {
                $where_clause .= " OR u.id = '$mention_str'";
            }
            
            $q = "SELECT u.id, p.full_name, u.role_id, GROUP_CONCAT(a.apt_number SEPARATOR ', ') as apt_numbers 
                  FROM users u 
                  JOIN user_profiles p ON u.id = p.user_id 
                  JOIN apartment_assignments aa ON aa.user_id = u.id AND aa.is_active = 1
                  JOIN apartments a ON aa.apt_id = a.id 
                  WHERE $where_clause 
                  GROUP BY u.id, p.full_name, u.role_id 
                  LIMIT 1";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0) {
                $p = mysqli_fetch_assoc($res);
                $name = $p['full_name'];
                $mention_id = $p['id'];
                
                $apt_display = 'Resident';
                if ($p['role_id'] == 1) {
                    $apt_display = 'OWNER';
                } else {
                    $apts = explode(', ', $p['apt_numbers']);
                    $real_apts = [];
                    foreach ($apts as $a) {
                        if (strlen($a) < 15) {
                            $real_apts[] = $a;
                        }
                    }
                    if (!empty($real_apts)) {
                        $apt_display = $real_apts[0];
                    }
                }
                
                $content = preg_replace('/@' . preg_quote($mention_str, '/') . '(?![a-zA-Z0-9_.-])/', "<span class='text-emerald-700 font-bold bg-emerald-100 px-2 py-0.5 rounded-md border border-emerald-200 shadow-sm transition-all hover:bg-emerald-200 cursor-help' title='User ID: $mention_id'>@$name (Apt: $apt_display)</span>", $content);
            }
        }
    }
    return $content;
}

$categoryColors = [
    1 => 'bg-blue-100 text-blue-700 border-blue-200',
    2 => 'bg-red-100 text-red-700 border-red-200',
    3 => 'bg-purple-100 text-purple-700 border-purple-200',
    4 => 'bg-orange-100 text-orange-700 border-orange-200',
    5 => 'bg-slate-100 text-slate-700 border-slate-200',
    6 => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    7 => 'bg-rose-100 text-rose-700 border-rose-200',
    8 => 'bg-teal-100 text-teal-700 border-teal-200',
];
$defaultCategoryColor = 'bg-slate-50 text-slate-500 border-slate-200';

// Fetch notices scoped to the current user's building only
$notices = [];
$safe_building_id_filter = mysqli_real_escape_string($conn, $user_building_id);
$n_query = "SELECT n.*, p.full_name as author_name, u.role_id as author_role_id 
            FROM community_posts n 
            JOIN apartments a ON n.apt_id = a.id
            JOIN users u ON n.user_id = u.id
            LEFT JOIN user_profiles p ON n.user_id = p.user_id 
            WHERE a.building_id = '$safe_building_id_filter' AND (n.status IS NULL OR n.status != 'archived')
            ORDER BY n.is_pinned DESC, n.created_at DESC";
try {
    $n_res = @mysqli_query($conn, $n_query);
    if ($n_res) {
        $post_ids = [];
        while ($row = mysqli_fetch_assoc($n_res)) {
            $notices[] = $row;
            $post_ids[] = $row['id'];
        }
        
        // Fetch images for all fetched posts
        $post_images = [];
        if (!empty($post_ids)) {
            $ids_str = implode(',', $post_ids);
            $img_res = @mysqli_query($conn, "SELECT post_id, image_path FROM community_post_images WHERE post_id IN ($ids_str)");
            if ($img_res) {
                while ($img_row = mysqli_fetch_assoc($img_res)) {
                    $post_images[$img_row['post_id']][] = $img_row['image_path'];
                }
            }
        }
    }
} catch (Exception $e) {}

// Fetch comments
$comments = [];
$c_query = "SELECT c.*, p.full_name as author_name, u.role_id as author_role_id 
            FROM community_comments c 
            JOIN apartments a ON c.apt_id = a.id
            JOIN users u ON c.user_id = u.id
            LEFT JOIN user_profiles p ON c.user_id = p.user_id 
            WHERE a.building_id = '$safe_building_id_filter'
            ORDER BY c.created_at ASC";
try {
    $c_res = @mysqli_query($conn, $c_query);
    if ($c_res) {
        while ($row = mysqli_fetch_assoc($c_res)) {
            $comments[$row['post_id']][] = $row;
        }
    }
} catch (Exception $e) {}
?>
<?php
$resident_building_name = 'Nibash';
try {
    $uid_for_b = $_SESSION['user_id'] ?? 0;
    if ($uid_for_b) {
        $bq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id JOIN buildings b ON a.building_id = b.id WHERE aa.user_id = '$uid_for_b' AND aa.is_active=1 LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $brow = mysqli_fetch_assoc($bq);
            $resident_building_name = !empty($brow['building_name']) ? $brow['building_name'] : $brow['building_number'];
        } else {
            $mq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM building_managers bm JOIN buildings b ON bm.building_id = b.id WHERE bm.user_id = '$uid_for_b' LIMIT 1");
            if ($mq && mysqli_num_rows($mq) > 0) {
                $mrow = mysqli_fetch_assoc($mq);
                $resident_building_name = !empty($mrow['building_name']) ? $mrow['building_name'] : $mrow['building_number'];
            }
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Terminal | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(16, 185, 129, 0.15); border-color: #6ee7b7; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
        .mention-dropdown {
            position: absolute;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 50;
            width: max-content;
            min-width: 200px;
        }
        .mention-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f8fafc;
        }
        .mention-item:hover, .mention-item.active {
            background-color: #ecfdf5;
        }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    if ($role_id == 1) {
        include 'includes/owner_sidebar.php'; 
    } else {
        include 'includes/resident_sidebar.php'; 
    }
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(16,185,129,0.15)] flex-1 flex flex-col overflow-hidden border border-emerald-100/80 relative">
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Community Platform</span>
                        </h2>
                    </div>
                    
                    <div>
                        <a href="messages/index.php" class="flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-black text-xs uppercase tracking-widest rounded-xl transition-colors border border-emerald-200 shadow-sm">
                            <i data-lucide="message-square" class="w-4 h-4"></i> Inbox
                        </a>
                    </div>

                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10" x-data="{ showForm: false }">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Live Notice Stream
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="activity" class="w-4 h-4 text-emerald-700"></i></span>
                            Stay updated and connect with your building community.
                        </p>
                    </div>
                    <button @click="showForm = !showForm" class="w-full sm:w-auto px-6 py-3 bg-slate-900 hover:bg-emerald-600 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/40 flex items-center justify-center gap-2 group">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                        <span x-text="showForm ? 'Cancel Post' : 'Create New Post'"></span>
                    </button>
                </div>

                <div x-show="showForm" x-collapse x-cloak>
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm mb-6 relative" x-data="mentionableEditor(1)">
                        <form action="community_hub.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="post_notice">
                            <input type="hidden" name="category_id" :value="selectedCategory">
                            
                            <div class="space-y-3">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Select Category</label>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($categories as $cat): ?>
                                    <button type="button" 
                                            @click="selectedCategory = <?php echo $cat['id']; ?>"
                                            :class="selectedCategory == <?php echo $cat['id']; ?> ? 'bg-emerald-600 text-white border-emerald-600 shadow-md' : 'bg-slate-50 text-slate-600 border-slate-200 hover:border-emerald-300 hover:bg-emerald-50'"
                                            class="px-5 py-2 rounded-xl border text-[10px] font-black uppercase tracking-widest transition-all duration-300 shadow-sm">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Notice Title <span class="text-emerald-500">*</span></label>
                                    <input type="text" name="title" placeholder="What's this about?" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all font-bold text-slate-800 text-sm">
                                </div>
                                
                                <div class="flex flex-col justify-end">
                                    <div class="flex items-center justify-between px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl h-[50px]">
                                        <?php if ($role_id == 1): ?>
                                        <label class="flex items-center gap-2 cursor-pointer group">
                                            <input type="checkbox" name="is_pinned" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 transition-all">
                                            <span class="text-xs font-black text-slate-600 uppercase tracking-widest group-hover:text-emerald-700 transition-colors">Pin Notice</span>
                                        </label>
                                        <?php else: ?>
                                        <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Standard Post</span>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest bg-emerald-50 px-2 py-1 rounded border border-emerald-100">Type @ to mention</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="relative">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Message <span class="text-emerald-500">*</span></label>
                                <textarea name="content" x-ref="contentInput" @input="handleInput($event)" placeholder="Write your message here..." required class="w-full h-32 px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all font-medium text-sm resize-none"></textarea>
                                
                                <div x-show="showMentions" x-ref="mentionDropdown" class="mention-dropdown" :style="`top: ${cursorCoords.y}px; left: ${cursorCoords.x}px;`" @click.outside="showMentions = false" style="display: none;">
                                    <template x-for="(user, index) in filteredUsers" :key="user.id">
                                        <div class="mention-item flex flex-col" :class="{'active': index === focusedIndex}" @click="insertMention(user)">
                                            <span class="text-sm font-bold text-slate-800" x-text="user.name"></span>
                                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400" x-text="'Apt: ' + user.apt"></span>
                                        </div>
                                    </template>
                                    <div x-show="filteredUsers.length === 0" class="p-3 text-xs text-slate-500 italic">No residents found.</div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
                                <div class="relative w-full sm:w-auto">
                                    <input type="file" name="files[]" id="file-upload" multiple class="hidden" @change="fileName = $event.target.files.length > 0 ? $event.target.files.length + ' file(s) selected' : ''">
                                    <label for="file-upload" class="flex items-center justify-center gap-2 px-6 py-3 bg-white hover:bg-slate-50 text-slate-600 rounded-xl cursor-pointer transition-all text-[10px] font-black uppercase tracking-widest border border-slate-200 shadow-sm">
                                        <i data-lucide="paperclip" class="w-4 h-4 text-emerald-500"></i>
                                        <span x-text="fileName || 'Attach Files'"></span>
                                    </label>
                                </div>

                                <button type="submit" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white px-10 py-3.5 rounded-xl font-black text-sm transition-all flex items-center justify-center gap-2 shadow-sm">
                                    Publish to Terminal <i data-lucide="send" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="space-y-8">
                    <?php foreach ($notices as $notice): ?>
                        <?php 
                            $is_mentioned = strpos($notice['content'], "@$user_id") !== false;
                            $is_pinned = isset($notice['is_pinned']) && $notice['is_pinned'];
                            $is_owner_post = ($notice['author_role_id'] == 1);
                            
                            $highlight_class = 'bg-white border-slate-200 shadow-sm'; 
                            if ($is_mentioned) {
                                $highlight_class = 'bg-emerald-50/50 border-emerald-200 shadow-[0_8px_30px_-6px_rgba(16,185,129,0.15)]';
                            } elseif ($is_pinned) {
                                $highlight_class = 'bg-slate-50/50 border-slate-200 shadow-sm';
                            } elseif ($is_owner_post) {
                                $highlight_class = 'bg-indigo-50/30 border-indigo-100 shadow-sm';
                            }
                            
                            // Category Color
                            $cat_id = $notice['category_id'];
                            $cat_name = "Notice";
                            $cat_color = $defaultCategoryColor;
                            foreach ($categories as $c) {
                                if ($c['id'] == $cat_id) { 
                                    $cat_name = $c['category_name']; 
                                    $cat_color = isset($categoryColors[$cat_id]) ? $categoryColors[$cat_id] : $defaultCategoryColor;
                                    break; 
                                }
                            }
                        ?>
                        <div class="hover-card rounded-[2rem] border transition-all flex flex-col relative overflow-hidden group <?php echo $highlight_class; ?>" x-data="{ editing: false, showReplies: false }">
                            
                            <?php if ($is_pinned): ?>
                                <div class="absolute top-0 left-0 w-full h-1 bg-emerald-400"></div>
                            <?php elseif ($is_owner_post): ?>
                                <div class="absolute top-0 left-0 w-full h-1 bg-indigo-400"></div>
                            <?php else: ?>
                                <div class="absolute top-0 left-0 w-1 h-full bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                            <?php endif; ?>

                            <?php if ($is_mentioned): ?>
                                <div class="absolute top-0 right-8 px-4 py-1.5 bg-emerald-500 text-white text-[9px] font-black uppercase tracking-[0.2em] rounded-b-lg shadow-sm">
                                    Personal Alert
                                </div>
                            <?php elseif ($is_pinned): ?>
                                <div class="absolute top-5 right-5 p-2 bg-emerald-50 rounded-lg border border-emerald-100">
                                    <i data-lucide="pin" class="w-4 h-4 text-emerald-500 fill-emerald-500"></i>
                                </div>
                            <?php endif; ?>

                            <div class="p-8" x-show="!editing">
                                <div class="flex items-start justify-between mb-6">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center font-black text-slate-400 border border-slate-200 shadow-sm text-lg">
                                            <?php echo strtoupper(substr($notice['author_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 mb-0.5">
                                                <h4 class="text-base font-black text-slate-900 group-hover:text-emerald-600 transition-colors"><?php echo htmlspecialchars($notice['author_name']); ?></h4>
                                                <?php if ($notice['user_id'] != $user_id): ?>
                                                    <a href="messages/index.php?user_id=<?php echo $notice['user_id']; ?>" class="bg-emerald-50 text-emerald-600 border border-emerald-100 p-1 rounded-md hover:bg-emerald-100 transition-colors" title="Message User"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i></a>
                                                <?php endif; ?>
                                                <?php if ($is_owner_post): ?>
                                                    <span class="bg-indigo-50 text-indigo-600 border border-indigo-100 p-1 rounded-md" title="Building Admin"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i></span>
                                                <?php endif; ?>
                                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded border shadow-sm <?php echo $cat_color; ?>">
                                                    <?php echo htmlspecialchars($cat_name); ?>
                                                </span>
                                            </div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                                <i data-lucide="clock" class="w-3 h-3"></i> <?php echo date('M d, Y • g:i A', strtotime($notice['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 opacity-40 group-hover:opacity-100 transition-opacity">
                                        <?php if ($notice['user_id'] == $user_id): ?>
                                            <button @click="editing = true" class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 border border-slate-200 hover:text-blue-600 hover:bg-blue-50 hover:border-blue-200 rounded-lg transition-all shadow-sm">
                                                <i data-lucide="edit-2" class="w-4 h-4"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($notice['user_id'] == $user_id || $role_id == 1): ?>
                                            <form action="community_hub.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this notice?');" class="inline m-0">
                                                <input type="hidden" name="action" value="delete_notice">
                                                <input type="hidden" name="post_id" value="<?php echo $notice['id']; ?>">
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center bg-white text-rose-400 border border-slate-200 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 rounded-lg transition-all shadow-sm">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div>
                                    <h2 class="text-xl font-black text-slate-800 mb-3"><?php echo htmlspecialchars($notice['title']); ?></h2>
                                    <p class="text-sm text-slate-600 leading-relaxed font-medium bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
                                        <?php echo nl2br(resolveMentions(htmlspecialchars($notice['content']), $conn)); ?>
                                    </p>
                                    
                                    <?php 
                                    $images = isset($post_images[$notice['id']]) ? $post_images[$notice['id']] : [];
                                    if (!empty($images)): ?>
                                        <div class="mt-4 flex flex-wrap gap-3">
                                            <?php foreach ($images as $img): ?>
                                                <a href="<?php echo htmlspecialchars($img); ?>" target="_blank" class="block w-24 h-24 rounded-xl overflow-hidden border border-slate-200 shadow-sm hover:scale-105 transition-transform">
                                                    <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover">
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            
                            <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                                <button @click="showReplies = !showReplies" class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 hover:border-emerald-200 rounded-xl transition-colors text-[10px] font-black uppercase tracking-widest shadow-sm">
                                    <i data-lucide="message-square" class="w-4 h-4"></i>
                                    <span><?php echo isset($comments[$notice['id']]) ? count($comments[$notice['id']]) : 0; ?> Replies</span>
                                </button>
                            </div>
                            
                            <div x-show="showReplies" x-collapse x-cloak class="mt-6 border-t border-slate-100 pt-6">
                                <div class="space-y-4 mb-6">
                                    <?php if (isset($comments[$notice['id']])): ?>
                                        <?php foreach ($comments[$notice['id']] as $comment): ?>
                                            <div class="flex gap-4">
                                                <div class="w-10 h-10 shrink-0 bg-slate-100 rounded-full flex items-center justify-center font-black text-slate-400 border border-slate-200 shadow-sm text-sm">
                                                    <?php echo strtoupper(substr($comment['author_name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-1 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                                                    <div class="absolute left-0 top-0 w-1 h-full bg-slate-200"></div>
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <h5 class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($comment['author_name']); ?></h5>
                                                        <?php if ($comment['user_id'] != $user_id): ?>
                                                            <a href="messages/index.php?user_id=<?php echo $comment['user_id']; ?>" class="bg-emerald-50 text-emerald-600 border border-emerald-100 p-0.5 rounded-md hover:bg-emerald-100 transition-colors" title="Message User"><i data-lucide="message-circle" class="w-3 h-3"></i></a>
                                                        <?php endif; ?>
                                                        <?php if ($comment['author_role_id'] == 1): ?>
                                                            <span class="bg-indigo-50 text-indigo-600 border border-indigo-100 p-0.5 rounded-md" title="Building Admin"><i data-lucide="shield-check" class="w-3 h-3"></i></span>
                                                        <?php endif; ?>
                                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest ml-auto flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> <?php echo date('M d, g:i A', strtotime($comment['created_at'])); ?></span>
                                                    </div>
                                                    <p class="text-sm text-slate-600 leading-relaxed font-medium">
                                                        <?php echo nl2br(resolveMentions(htmlspecialchars($comment['content']), $conn)); ?>
                                                    </p>
                                                    <?php if (!empty($comment['image_path'])): ?>
                                                        <div class="mt-3">
                                                            <a href="<?php echo htmlspecialchars($comment['image_path']); ?>" target="_blank" class="block w-20 h-20 rounded-xl overflow-hidden border border-slate-200 shadow-sm hover:scale-105 transition-transform">
                                                                <img src="<?php echo htmlspecialchars($comment['image_path']); ?>" class="w-full h-full object-cover">
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="py-4 text-center bg-slate-50 border border-slate-100 rounded-xl">
                                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">No replies yet. Be the first!</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div x-data="mentionableEditor(1)" class="relative flex gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                    <div class="w-10 h-10 shrink-0 bg-emerald-50 rounded-full flex items-center justify-center font-black text-emerald-500 border border-emerald-100 shadow-sm text-sm">
                                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                    </div>
                                    <form action="community_hub.php" method="POST" enctype="multipart/form-data" class="flex-1 relative m-0">
                                        <input type="hidden" name="action" value="post_reply">
                                        <input type="hidden" name="post_id" value="<?php echo $notice['id']; ?>">
                                        
                                        <div class="bg-white border border-slate-200 rounded-xl focus-within:border-emerald-400 focus-within:ring-4 focus-within:ring-emerald-500/10 transition-all shadow-sm">
                                            <textarea name="content" x-ref="contentInput" @input="handleInput($event)" placeholder="Write a reply... Type @ to mention" required class="w-full min-h-[80px] p-4 bg-transparent outline-none resize-none text-sm font-medium text-slate-700"></textarea>
                                            
                                            <div class="flex items-center justify-between px-3 pb-3 border-t border-slate-50 pt-3">
                                                <div class="relative">
                                                    <input type="file" name="file" :id="'reply-file-'+<?php echo $notice['id']; ?>" class="hidden" @change="fileName = $event.target.files.length > 0 ? $event.target.files[0].name : ''">
                                                    <label :for="'reply-file-'+<?php echo $notice['id']; ?>" class="flex items-center gap-1.5 px-3 py-1.5 hover:bg-slate-50 border border-transparent hover:border-slate-200 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer transition-all text-[10px] font-black uppercase tracking-widest">
                                                        <i data-lucide="paperclip" class="w-4 h-4"></i>
                                                        <span x-text="fileName || 'Attach Image'"></span>
                                                    </label>
                                                </div>
                                                <button type="submit" class="p-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors shadow-sm">
                                                    <i data-lucide="send" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                            
                                            <div x-show="showMentions" x-ref="mentionDropdown" class="mention-dropdown" :style="`top: ${cursorCoords.y}px; left: ${cursorCoords.x}px;`" @click.outside="showMentions = false" style="display: none;">
                                                <template x-for="(user, index) in filteredUsers" :key="user.id">
                                                    <div class="mention-item flex flex-col" :class="{'active': index === focusedIndex}" @click="insertMention(user)">
                                                        <span class="text-sm font-bold text-slate-800" x-text="user.name"></span>
                                                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400" x-text="'Apt: ' + user.apt"></span>
                                                    </div>
                                                </template>
                                                <div x-show="filteredUsers.length === 0" class="p-3 text-[10px] font-black uppercase tracking-widest text-slate-400">No residents found.</div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            </div> <?php if ($notice['user_id'] == $user_id): ?>
                            <div x-show="editing" x-cloak class="p-8 bg-white">
                                <form action="community_hub.php" method="POST" class="space-y-6 bg-slate-50 p-6 rounded-[1.5rem] border border-slate-200">
                                    <input type="hidden" name="action" value="edit_notice">
                                    <input type="hidden" name="post_id" value="<?php echo $notice['id']; ?>">
                                    
                                    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                                        <h4 class="font-black text-slate-800 text-lg flex items-center gap-2"><i data-lucide="edit-3" class="w-5 h-5 text-emerald-500"></i> Edit Notice</h4>
                                        <button type="button" @click="editing = false" class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 border border-slate-200 hover:text-rose-500 hover:bg-rose-50 hover:border-rose-200 rounded-lg transition-all shadow-sm">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                        </button>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Title</label>
                                        <input type="text" name="title" value="<?php echo htmlspecialchars($notice['title']); ?>" required class="w-full px-5 py-3.5 bg-white border border-slate-200 rounded-xl outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all text-sm font-bold shadow-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Message</label>
                                        <textarea name="content" required class="w-full h-32 px-5 py-4 bg-white border border-slate-200 rounded-xl outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all text-sm font-medium resize-none shadow-sm"><?php echo htmlspecialchars($notice['content']); ?></textarea>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
                                        <div class="relative w-full sm:w-64">
                                            <select name="category_id" class="w-full px-5 py-3.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 appearance-none shadow-sm cursor-pointer">
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $notice['category_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-3">
                                            <?php if ($role_id == 1): ?>
                                            <label class="flex items-center gap-2 cursor-pointer bg-white px-4 py-3 border border-slate-200 rounded-xl shadow-sm">
                                                <input type="checkbox" name="is_pinned" <?php echo $is_pinned ? 'checked' : ''; ?> class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-600">Pinned</span>
                                            </label>
                                            <?php endif; ?>
                                            
                                            <button type="submit" class="w-full sm:w-auto px-8 py-3.5 text-sm font-black text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                                                <i data-lucide="save" class="w-4 h-4"></i> Save
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($notices)): ?>
                        <div class="py-20 text-center flex flex-col items-center gap-4 bg-white rounded-[2rem] border-2 border-dashed border-slate-300 shadow-sm">
                            <div class="w-20 h-20 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center text-slate-300 shadow-sm">
                                <i data-lucide="inbox" class="w-10 h-10"></i>
                            </div>
                            <p class="text-slate-900 font-black text-xl">No announcements yet.</p>
                            <p class="text-sm text-slate-500 font-medium max-w-sm">Be the first to post something to your building's community stream!</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        window.buildingUsers = [];
        fetch('api/users.php?action=get_building_users')
            .then(res => res.json())
            .then(data => {
                if(Array.isArray(data)) {
                    window.buildingUsers = data;
                }
            })
            .catch(err => console.error("Error fetching users", err));
            
        lucide.createIcons();

        function mentionableEditor(initialCategory = 1) {
            return {
                selectedCategory: initialCategory,
                fileName: '',
                filteredUsers: [],
                showMentions: false,
                mentionQuery: '',
                mentionStartIndex: -1,
                cursorCoords: { x: 0, y: 0 },
                focusedIndex: 0,
                
                get allUsers() {
                    return window.buildingUsers || [];
                },
                
                handleInput(e) {
                    const el = this.$refs.contentInput;
                    const text = el.value;
                    const cursorPos = el.selectionStart;
                    
                    const textBeforeCursor = text.substring(0, cursorPos);
                    const lastAtIndex = textBeforeCursor.lastIndexOf('@');
                    
                    if (lastAtIndex !== -1) {
                        if (lastAtIndex === 0 || /\s/.test(textBeforeCursor.charAt(lastAtIndex - 1))) {
                            const query = textBeforeCursor.substring(lastAtIndex + 1);
                            if (!/\s/.test(query)) {
                                this.mentionQuery = query.toLowerCase();
                                this.mentionStartIndex = lastAtIndex;
                                
                                this.filteredUsers = this.allUsers.filter(u => 
                                    u.name.toLowerCase().includes(this.mentionQuery) || 
                                    (u.apt && u.apt.toLowerCase().includes(this.mentionQuery))
                                );
                                
                                this.focusedIndex = 0;
                                
                                if (this.filteredUsers.length > 0) {
                                    const lines = textBeforeCursor.split('\n');
                                    const currentLine = lines.length;
                                    const charWidth = 8;
                                    const lineHeight = 20;
                                    const charsInCurrentLine = lines[lines.length - 1].length;
                                    
                                    this.cursorCoords = {
                                        x: 20 + (charsInCurrentLine * charWidth),
                                        y: 20 + (currentLine * lineHeight) + 10
                                    };
                                    
                                    this.showMentions = true;
                                    return;
                                }
                            }
                        }
                    }
                    this.showMentions = false;
                },
                
                insertMention(user) {
                    const el = this.$refs.contentInput;
                    const text = el.value;
                    const before = text.substring(0, this.mentionStartIndex);
                    const after = text.substring(el.selectionStart);
                    
                    const formattedName = user.name.replace(/\s+/g, '_');
                    const mentionText = `@${formattedName} `;
                    
                    el.value = before + mentionText + after;
                    
                    const newCursorPos = this.mentionStartIndex + mentionText.length;
                    el.focus();
                    el.setSelectionRange(newCursorPos, newCursorPos);
                    
                    this.showMentions = false;
                }
            }
        }
    </script>
    <script src="js/toast.js"></script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>