<?php
$current_page = 'approvals';
$page_title   = 'Pending Approvals';
require_once 'includes/auth.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD']==='POST' && $is_logged_in) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $user_email = $_POST['user_email'] ?? '';
    $user_name = $_POST['user_name'] ?? 'User';

    if ($act==='approve_owner' && $uid>0) {
        mysqli_query($conn,"UPDATE users SET status='active',is_verified=1 WHERE id=$uid AND role_id=1");
        // Auto-enrol on free trial if no subscription exists
        $b_res = mysqli_query($conn, "SELECT building_id FROM building_managers WHERE user_id=$uid LIMIT 1");
        if($b_res && mysqli_num_rows($b_res) > 0) {
            $b_id = mysqli_fetch_assoc($b_res)['building_id'];
            mysqli_query($conn,"INSERT IGNORE INTO subscriptions (subscriber_type,subscriber_id,plan_id,status,expires_at) VALUES ('building',$b_id,1,'trial',DATE_ADD(NOW(),INTERVAL 14 DAY))");
        }
        
        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'suchak9931@gmail.com'; 
            $mail->Password   = 'ubaz ayum yhyy hyis';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('suchak9931@gmail.com', 'Nibash System');
            $mail->addAddress($user_email, $user_name);

            $mail->isHTML(true);
            $mail->Subject = 'Your Owner Account is Active!';
            $mail->Body    = "Hello {$user_name},<br><br>Good news! Your owner account on Nibash has been approved and is now active.<br><br>You can now log in by your ID and password to manage your building.<br><br><a href='http://localhost/Nibash/login.php'>Click here to login</a>";

            $mail->send();
        } catch (Exception $e) {
            // Log or ignore if email fails
        }

        $_SESSION['success_msg'] = "Owner approved successfully.";
    }
    
    if ($act==='reject_owner' && $uid>0) {
        mysqli_query($conn,"DELETE FROM users WHERE id=$uid AND role_id=1 AND status='inactive'");
        $_SESSION['success_msg'] = "Owner request rejected.";
    }

    if ($act==='approve_provider' && $uid>0) {
        mysqli_query($conn,"UPDATE users SET status='active' WHERE id=$uid AND role_id=4");
        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'suchak9931@gmail.com'; 
            $mail->Password   = 'ubaz ayum yhyy hyis';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('suchak9931@gmail.com', 'Nibash System');
            $mail->addAddress($user_email, $user_name);

            $mail->isHTML(true);
            $mail->Subject = 'Your Provider Account is Active!';
            $mail->Body    = "Hello {$user_name},<br><br>Good news! Your provider account on Nibash has been approved and is now active.<br><br>You can now log in by your ID and password to your dashboard and start receiving service requests.<br><br><a href='http://localhost/Nibash/login.php'>Click here to login</a>";

            $mail->send();
        } catch (Exception $e) {
            // Log or ignore if email fails
        }
        $_SESSION['success_msg'] = "Provider approved successfully.";
    }

    if ($act==='reject_provider' && $uid>0) {
        mysqli_query($conn,"DELETE FROM users WHERE id=$uid AND role_id=4 AND status='inactive'");
        $_SESSION['success_msg'] = "Provider request rejected.";
    }

    header('Location: approvals.php'); exit;
}

// Fetch Owner Approvals (Role 1)
$owner_rows=[];
$r_own=mysqli_query($conn,"SELECT u.id,u.username,u.email,u.status,u.created_at,up.full_name,up.phone,up.nid,up.profile_image
    FROM users u LEFT JOIN user_profiles up ON u.id=up.user_id
    WHERE u.role_id=1 AND (u.status='inactive' OR u.is_verified=0)
    ORDER BY u.created_at ASC");
if($r_own) while($row=mysqli_fetch_assoc($r_own)) $owner_rows[]=$row;

// Fetch Provider Approvals (Role 4)
// Note: Providers are 'is_verified'=1 if they did OTP, but 'status'='inactive' pending admin
$prov_rows=[];
$r_prov=mysqli_query($conn,"SELECT u.id, u.username, u.email, u.status, u.created_at, 
    sp.name as full_name, sp.phone, sp.nid_number as nid, sp.image_path as profile_image, sc.category_name
    FROM users u 
    LEFT JOIN service_providers sp ON u.id = sp.user_id
    LEFT JOIN service_categories sc ON sp.category_id = sc.id
    WHERE u.role_id=4 AND u.status='inactive'
    ORDER BY u.created_at ASC");
if($r_prov) while($row=mysqli_fetch_assoc($r_prov)) $prov_rows[]=$row;

include 'includes/layout_header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Pending Approvals</h1>
    <p class="text-slate-500 mt-2 text-sm md:text-base">Review and authorize new accounts before they access the platform.</p>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl flex shadow-sm mb-6 relative">
        <div class="flex-shrink-0 bg-emerald-100 rounded-full p-1.5 border border-emerald-200">
            <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600"></i>
        </div>
        <div class="ml-3 flex items-center">
            <p class="text-sm text-emerald-700 font-bold tracking-wide"><?= htmlspecialchars($_SESSION['success_msg']) ?></p>
        </div>
    </div>
    <?php unset($_SESSION['success_msg']); ?>
<?php endif; ?>

<!-- Tabs -->
<div class="flex space-x-2 mb-6 p-1 bg-slate-100 rounded-xl inline-flex">
    <button onclick="switchTab('owners')" id="tab-owners" class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all <?= (count($owner_rows) >= count($prov_rows)) ? 'bg-white text-emerald-600 shadow-sm border border-slate-200/60' : 'text-slate-500 hover:text-slate-700' ?>">
        Owner Approvals <?php if(count($owner_rows)>0): ?><span class="ml-2 bg-amber-100 text-amber-700 py-0.5 px-2 rounded-full text-xs"><?=count($owner_rows)?></span><?php endif; ?>
    </button>
    <button onclick="switchTab('providers')" id="tab-providers" class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all <?= (count($prov_rows) > count($owner_rows)) ? 'bg-white text-emerald-600 shadow-sm border border-slate-200/60' : 'text-slate-500 hover:text-slate-700' ?>">
        Provider Approvals <?php if(count($prov_rows)>0): ?><span class="ml-2 bg-amber-100 text-amber-700 py-0.5 px-2 rounded-full text-xs"><?=count($prov_rows)?></span><?php endif; ?>
    </button>
</div>

<!-- Owners Tab Content -->
<div id="content-owners" class="<?= (count($owner_rows) >= count($prov_rows)) ? 'block' : 'hidden' ?>">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden group/card relative transition-all duration-300">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Building Owners</h2>
                <p class="text-sm text-slate-500 mt-0.5">Approve new owners so they can manage their buildings.</p>
            </div>
        </div>
        
        <?php if(empty($owner_rows)): ?>
        <div class="py-20 text-center">
            <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                <i data-lucide="shield-check" class="w-8 h-8 text-emerald-400"></i>
            </div>
            <h3 class="text-slate-800 font-bold mb-1">All Caught Up!</h3>
            <p class="text-slate-500 text-sm font-medium">No pending owner approvals.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 font-semibold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">Owner Profile</th>
                        <th class="px-6 py-4">Contact Details</th>
                        <th class="px-6 py-4">NID Number</th>
                        <th class="px-6 py-4">Registered Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach($owner_rows as $u): ?>
                <tr class="hover:bg-slate-50/80 transition-colors group">
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-4">
                            <?php if(!empty($u['profile_image']) && $u['profile_image']!='default_avatar.jpg'): ?>
                            <img src="../assets/uploads/profiles/<?=htmlspecialchars($u['profile_image'])?>" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-sm">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-100 to-teal-100 border border-emerald-200 text-emerald-700 flex items-center justify-center text-sm font-black uppercase shadow-sm">
                                <?=substr($u['full_name']??'O',0,2)?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-bold text-slate-900"><?=htmlspecialchars($u['full_name']??'N/A')?></p>
                                <p class="text-xs font-medium text-slate-500 mt-0.5">@<?=htmlspecialchars($u['username']??'')?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5">
                        <p class="text-sm font-medium text-slate-800"><?=htmlspecialchars($u['email'])?></p>
                        <p class="text-xs text-slate-500 mt-1"><?=htmlspecialchars($u['phone']??'—')?></p>
                    </td>
                    <td class="px-6 py-5 text-sm font-mono font-medium text-slate-600 bg-slate-50/30">
                        <?=htmlspecialchars($u['nid']??'—')?>
                    </td>
                    <td class="px-6 py-5 text-sm font-medium text-slate-500">
                        <?=date('M d, Y',strtotime($u['created_at']))?>
                    </td>
                    <td class="px-6 py-5 text-right">
                        <div class="flex items-center gap-2 justify-end opacity-70 group-hover:opacity-100 transition-opacity">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="approve_owner">
                                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                                <input type="hidden" name="user_email" value="<?=$u['email']?>">
                                <input type="hidden" name="user_name" value="<?=$u['full_name']?>">
                                <button class="px-4 py-2 bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 hover:border-emerald-600 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                    <i data-lucide="check" class="w-4 h-4"></i> Approve
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Reject and delete this account?')" class="inline">
                                <input type="hidden" name="action" value="reject_owner">
                                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                                <button class="px-3 py-2 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-200 hover:border-red-600 rounded-xl text-xs font-bold transition-all shadow-sm">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Providers Tab Content -->
<div id="content-providers" class="<?= (count($prov_rows) > count($owner_rows)) ? 'block' : 'hidden' ?>">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden group/card relative transition-all duration-300">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Service Providers</h2>
                <p class="text-sm text-slate-500 mt-0.5">Approve vetted professionals before they can accept jobs.</p>
            </div>
        </div>
        
        <?php if(empty($prov_rows)): ?>
        <div class="py-20 text-center">
            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-blue-100">
                <i data-lucide="check-square" class="w-8 h-8 text-blue-400"></i>
            </div>
            <h3 class="text-slate-800 font-bold mb-1">All Clear!</h3>
            <p class="text-slate-500 text-sm font-medium">No pending provider approvals.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 font-semibold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">Provider Profile</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Contact</th>
                        <th class="px-6 py-4">NID Number</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach($prov_rows as $u): ?>
                <tr class="hover:bg-slate-50/80 transition-colors group">
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-4">
                            <?php if(!empty($u['profile_image']) && $u['profile_image']!='default_avatar.jpg'): ?>
                            <img src="../assets/uploads/profiles/<?=htmlspecialchars($u['profile_image'])?>" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-sm">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100 border border-blue-200 text-blue-700 flex items-center justify-center text-sm font-black uppercase shadow-sm">
                                <?=substr($u['full_name']??'P',0,2)?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-bold text-slate-900"><?=htmlspecialchars($u['full_name']??'N/A')?></p>
                                <p class="text-xs font-medium text-slate-500 mt-0.5">@<?=htmlspecialchars($u['username']??'')?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-5">
                        <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md text-xs font-bold border border-slate-200">
                            <?=htmlspecialchars($u['category_name']??'General')?>
                        </span>
                    </td>
                    <td class="px-6 py-5">
                        <p class="text-sm font-medium text-slate-800"><?=htmlspecialchars($u['email'])?></p>
                        <p class="text-xs text-slate-500 mt-1"><?=htmlspecialchars($u['phone']??'—')?></p>
                    </td>
                    <td class="px-6 py-5 text-sm font-mono font-medium text-slate-600 bg-slate-50/30">
                        <?=htmlspecialchars($u['nid']??'—')?>
                    </td>
                    <td class="px-6 py-5 text-right">
                        <div class="flex items-center gap-2 justify-end opacity-70 group-hover:opacity-100 transition-opacity">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="approve_provider">
                                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                                <input type="hidden" name="user_email" value="<?=$u['email']?>">
                                <input type="hidden" name="user_name" value="<?=$u['full_name']?>">
                                <button class="px-4 py-2 bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 hover:border-emerald-600 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                    <i data-lucide="check" class="w-4 h-4"></i> Approve
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Reject and delete this provider?')" class="inline">
                                <input type="hidden" name="action" value="reject_provider">
                                <input type="hidden" name="user_id" value="<?=$u['id']?>">
                                <button class="px-3 py-2 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-200 hover:border-red-600 rounded-xl text-xs font-bold transition-all shadow-sm">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tab) {
    // Hide all contents
    document.getElementById('content-owners').classList.add('hidden');
    document.getElementById('content-providers').classList.add('hidden');
    
    // Reset all buttons
    document.getElementById('tab-owners').className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700';
    document.getElementById('tab-providers').className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700';
    
    // Show selected content and style button
    document.getElementById('content-' + tab).classList.remove('hidden');
    document.getElementById('tab-' + tab).className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all bg-white text-emerald-600 shadow-sm border border-slate-200/60';
}
</script>

<?php include 'includes/layout_footer.php'; ?>
