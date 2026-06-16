<?php
$current_page = 'users';
$page_title   = 'User Management';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && $is_logged_in) {
    $uid = (int)($_POST['user_id'] ?? 0);
    $act = $_POST['action'] ?? '';
    
    // role_id!=3 ensures we never suspend another Admin
    if ($act==='ban_user'  && $uid>0) {
        mysqli_query($conn,"UPDATE users SET status='suspended' WHERE id=$uid AND role_id!=3");
        $_SESSION['msg'] = "User suspended successfully.";
    }
    if ($act==='lift_ban'  && $uid>0) {
        mysqli_query($conn,"UPDATE users SET status='active' WHERE id=$uid AND role_id!=3");
        $_SESSION['msg'] = "User ban lifted. Account is now active.";
    }
    header('Location: users.php'); exit;
}

$search = trim($_GET['q'] ?? '');
$role_f = (int)($_GET['role'] ?? 0);
$where  = "u.role_id != 3";

if ($search) {
    $sq = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.email LIKE '%$sq%' OR up.full_name LIKE '%$sq%' OR sp.name LIKE '%$sq%')";
}
if ($role_f) $where .= " AND u.role_id=$role_f";

$users=[];
// Include service_providers in the query
$r=mysqli_query($conn,"SELECT u.id,u.username,u.email,u.role_id,u.status,u.created_at,
    COALESCE(up.full_name, sp.name) as full_name,
    COALESCE(up.profile_image, sp.image_path) as profile_image,
    COALESCE(up.phone, sp.phone) as phone,
    MAX(COALESCE(os.status,'none')) as sub_status, MAX(COALESCE(splan.plan_name,'—')) as plan_name
    FROM users u
    LEFT JOIN user_profiles up ON u.id=up.user_id
    LEFT JOIN service_providers sp ON u.id=sp.user_id
    LEFT JOIN building_managers bm ON u.id=bm.user_id
    LEFT JOIN subscriptions os ON bm.building_id=os.subscriber_id AND os.subscriber_type='building'
    LEFT JOIN subscription_plans splan ON os.plan_id=splan.id
    WHERE $where GROUP BY u.id ORDER BY u.created_at DESC");
if($r) while($row=mysqli_fetch_assoc($r)) $users[]=$row;

include 'includes/layout_header.php';
?>

<div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">User Management</h1>
        <p class="text-slate-500 mt-2 text-sm md:text-base">View, filter, and manage all users across the platform.</p>
    </div>
</div>

<?php if(isset($_SESSION['msg'])): ?>
<div class="mb-6 flex items-center gap-3 p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-700 text-sm font-medium shadow-sm">
    <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
    <?=htmlspecialchars($_SESSION['msg'])?>
</div>
<?php unset($_SESSION['msg']); endif; ?>

<!-- Filters -->
<div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-3">
        <div class="relative flex-1">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
            <input type="text" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Search name, email, or provider..." class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl pl-9 pr-4 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none font-medium text-slate-700 placeholder-slate-400 transition-all focus:bg-white">
        </div>
        
        <select name="role" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-sm font-medium rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none text-slate-700 focus:bg-white transition-all w-full md:w-auto cursor-pointer">
            <option value="0">All User Roles</option>
            <option value="1" <?=$role_f==1?'selected':''?>>Building Owners</option>
            <option value="2" <?=$role_f==2?'selected':''?>>Residents</option>
            <option value="4" <?=$role_f==4?'selected':''?>>Service Providers</option>
        </select>
        
        <button class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition shadow-sm shadow-emerald-500/20 flex items-center justify-center gap-2 border border-emerald-600">
            <i data-lucide="filter" class="w-4 h-4"></i> Filter
        </button>
        
        <?php if($search||$role_f): ?>
        <a href="users.php" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 text-sm font-bold rounded-xl hover:bg-slate-50 hover:text-slate-900 transition flex items-center justify-center shadow-sm">
            Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Users Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden group/card relative transition-all duration-300">
    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Platform Users</h2>
        </div>
        <span class="bg-slate-200 text-slate-700 text-xs font-bold px-3 py-1 rounded-full border border-slate-300">
            <?=count($users)?> Total
        </span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                <tr>
                    <th class="px-6 py-4">User Profile</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Contact</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Joined</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach($users as $u): ?>
            <?php
                $status_class = match($u['status']) {
                    'active'    => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                    'suspended' => 'bg-red-100 text-red-700 border border-red-200',
                    'inactive'  => 'bg-amber-100 text-amber-700 border border-amber-200',
                    default     => 'bg-slate-100 text-slate-500 border border-slate-200',
                };
                
                $role_badge = match((int)$u['role_id']) {
                    1 => ['bg'=>'bg-blue-50 border-blue-200 text-blue-700', 'lbl'=>'Owner'],
                    2 => ['bg'=>'bg-cyan-50 border-cyan-200 text-cyan-700', 'lbl'=>'Resident'],
                    4 => ['bg'=>'bg-violet-50 border-violet-200 text-violet-700', 'lbl'=>'Provider'],
                    default => ['bg'=>'bg-slate-50 border-slate-200 text-slate-700', 'lbl'=>'User']
                };
            ?>
            <tr class="hover:bg-slate-50/80 transition-colors group">
                <td class="px-6 py-5">
                    <div class="flex items-center gap-4">
                        <?php if(!empty($u['profile_image']) && $u['profile_image']!='default_avatar.jpg'): ?>
                        <img src="../assets/uploads/profiles/<?=htmlspecialchars($u['profile_image'])?>" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-sm">
                        <?php else: ?>
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 border border-slate-300 text-slate-600 flex items-center justify-center text-sm font-black uppercase shadow-sm">
                            <?=strtoupper(substr($u['full_name']??$u['email'],0,2))?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="font-bold text-slate-900 text-sm"><?=htmlspecialchars($u['full_name']??'No Name')?></p>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">@<?=htmlspecialchars($u['username'])?></p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-5">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border <?= $role_badge['bg'] ?>">
                        <?= $role_badge['lbl'] ?>
                    </span>
                    <?php if($u['role_id']==1 && $u['plan_name']!='—'): ?>
                    <p class="text-xs text-slate-400 mt-1.5 font-medium flex items-center gap-1"><i data-lucide="box" class="w-3 h-3"></i> <?=htmlspecialchars($u['plan_name'])?></p>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-5">
                    <p class="text-sm font-medium text-slate-800"><?=htmlspecialchars($u['email'])?></p>
                    <p class="text-xs text-slate-500 mt-1"><?=htmlspecialchars($u['phone']??'—')?></p>
                </td>
                <td class="px-6 py-5">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-md <?=$status_class?> shadow-sm">
                        <?php if($u['status']=='active'): ?> <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                        <?php elseif($u['status']=='suspended'): ?> <i data-lucide="slash" class="w-3.5 h-3.5"></i>
                        <?php else: ?> <i data-lucide="clock" class="w-3.5 h-3.5"></i> <?php endif; ?>
                        <?=ucfirst($u['status'])?>
                    </span>
                </td>
                <td class="px-6 py-5 text-sm font-medium text-slate-500">
                    <?=date('M d, Y',strtotime($u['created_at']))?>
                </td>
                <td class="px-6 py-5 text-right">
                    <div class="flex items-center gap-2 justify-end opacity-70 group-hover:opacity-100 transition-opacity">
                        <?php if($u['status']==='suspended'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="lift_ban">
                            <input type="hidden" name="user_id" value="<?=$u['id']?>">
                            <button class="px-4 py-2 bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-200 hover:border-emerald-600 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="unlock" class="w-4 h-4"></i> Lift Ban
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to suspend this user? They will not be able to log in.')">
                            <input type="hidden" name="action" value="ban_user">
                            <input type="hidden" name="user_id" value="<?=$u['id']?>">
                            <button class="px-4 py-2 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-200 hover:border-red-600 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="ban" class="w-4 h-4"></i> Suspend
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(empty($users)): ?>
            <tr>
                <td colspan="6" class="px-6 py-16 text-center">
                    <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i>
                    </div>
                    <h3 class="text-slate-800 font-bold mb-1">No users found</h3>
                    <p class="text-slate-500 text-sm font-medium">Try adjusting your filters or search query.</p>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/layout_footer.php'; ?>
