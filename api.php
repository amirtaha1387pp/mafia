<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
$user = require_login();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = db();

function ok($data=[]){ echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }
function fail($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

switch($action) {
    case 'create_lobby':
        $name = trim($_POST['name'] ?? '');
        if ($name==='') fail('نام لابی الزامی است');
        $stmt = $pdo->prepare('INSERT INTO lobbies(name,type,scenario_id,password,max_players,owner_id,age_group,is_ranked,entry_coin,created_at) VALUES(?,?,?,?,30,?,?,?,?,?)');
        $type = $_POST['type']==='ranked'?'ranked':'friendly';
        $stmt->execute([$name,$type,(int)$_POST['scenario_id'],($_POST['password']?:null),($_POST['age_group']??'18+'),$type==='ranked'?1:0,0,now()]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO lobby_members(lobby_id,user_id) VALUES(?,?)')->execute([$id,$user['id']]);
        ok(['lobby_id'=>$id]);
    case 'list_lobbies':
        $rows = $pdo->query('SELECT l.*, s.name scenario_name, (SELECT COUNT(*) FROM lobby_members m WHERE m.lobby_id=l.id AND m.is_kicked=0) member_count FROM lobbies l JOIN scenarios s ON s.id=l.scenario_id ORDER BY l.id DESC LIMIT 30')->fetchAll();
        ok($rows);
    case 'join_lobby':
        $lid = (int)$_POST['lobby_id'];
        $isObs = (int)($_POST['is_observer'] ?? 0);
        $lob = $pdo->prepare('SELECT * FROM lobbies WHERE id=?'); $lob->execute([$lid]); $l = $lob->fetch();
        if (!$l) fail('لابی پیدا نشد');
        if (!empty($l['password']) && ($l['password'] !== ($_POST['password']??''))) fail('رمز اشتباه است',403);
        $count = (int)$pdo->query("SELECT COUNT(*) c FROM lobby_members WHERE lobby_id={$lid} AND is_kicked=0")->fetch()['c'];
        if ($count >= (int)$l['max_players'] && !$isObs) fail('ظرفیت پر است');
        $pdo->prepare('INSERT OR IGNORE INTO lobby_members(lobby_id,user_id,is_observer) VALUES(?,?,?)')->execute([$lid,$user['id'],$isObs]);
        ok(['joined'=>true]);
    case 'chat_send':
        $lid=(int)$_POST['lobby_id']; $msg=trim($_POST['message']??''); if(!$msg) fail('پیام خالی');
        $pdo->prepare('INSERT INTO lobby_chat(lobby_id,user_id,message,created_at) VALUES(?,?,?,?)')->execute([$lid,$user['id'],$msg,now()]); ok();
    case 'chat_fetch':
        $lid=(int)$_GET['lobby_id'];
        $stmt=$pdo->prepare('SELECT c.id,c.message,c.created_at,u.display_name FROM lobby_chat c JOIN users u ON u.id=c.user_id WHERE c.lobby_id=? ORDER BY c.id DESC LIMIT 40');
        $stmt->execute([$lid]); ok(array_reverse($stmt->fetchAll()));
    case 'vote_react':
        $lid=(int)$_POST['lobby_id']; $target=(int)$_POST['target_user_id']; $type=$_POST['type'];
        if(!in_array($type,['like','dislike'],true)) fail('نوع نامعتبر');
        $field = $type==='like'?'likes':'dislikes';
        $pdo->prepare("UPDATE lobby_members SET {$field} = {$field}+1 WHERE lobby_id=? AND user_id=?")->execute([$lid,$target]);
        ok();
    case 'challenge':
        $lid=(int)$_POST['lobby_id']; $target=(int)$_POST['target_user_id'];
        $pdo->prepare('UPDATE lobby_members SET extra_challenges = extra_challenges+1 WHERE lobby_id=? AND user_id=?')->execute([$lid,$target]); ok();
    case 'voice_signal_push':
        $pdo->prepare('INSERT INTO voice_signals(lobby_id,from_user,to_user,signal_type,payload,created_at) VALUES(?,?,?,?,?,?)')->execute([(int)$_POST['lobby_id'],$user['id'],(int)$_POST['to_user'],$_POST['signal_type'],$_POST['payload'],now()]);
        ok();
    case 'voice_signal_pull':
        $lid=(int)$_GET['lobby_id'];
        $stmt=$pdo->prepare('SELECT * FROM voice_signals WHERE lobby_id=? AND to_user=? ORDER BY id ASC LIMIT 100');
        $stmt->execute([$lid,$user['id']]);
        $signals=$stmt->fetchAll();
        if ($signals) {
            $ids = implode(',', array_map(fn($x)=>(int)$x['id'], $signals));
            $pdo->exec("DELETE FROM voice_signals WHERE id IN ($ids)");
        }
        ok($signals);
    case 'buy_avatar':
        $aid=(int)$_POST['avatar_id']; $av=$pdo->prepare('SELECT * FROM avatars WHERE id=?'); $av->execute([$aid]); $a=$av->fetch(); if(!$a) fail('آواتار نیست');
        $me=current_user();
        if($me['gems']<$a['price_gem']||$me['coins']<$a['price_coin']) fail('موجودی کافی نیست');
        $pdo->prepare('UPDATE users SET gems=gems-?, coins=coins-?, avatar_id=? WHERE id=?')->execute([$a['price_gem'],$a['price_coin'],$aid,$user['id']]);
        $pdo->prepare('INSERT OR IGNORE INTO user_avatars(user_id,avatar_id) VALUES(?,?)')->execute([$user['id'],$aid]);
        ok(['avatar'=>$a['name']]);
    case 'buy_item':
        $id=(int)$_POST['item_id'];$st=$pdo->prepare('SELECT * FROM shop_items WHERE id=?');$st->execute([$id]);$it=$st->fetch();if(!$it)fail('آیتم نیست');
        $me=current_user(); if($me['gems']<$it['price_gem']||$me['coins']<$it['price_coin']) fail('موجودی کافی نیست');
        $pdo->prepare('UPDATE users SET gems=gems-?, coins=coins-? WHERE id=?')->execute([$it['price_gem'],$it['price_coin'],$user['id']]); ok();
    case 'submit_receipt':
        $pdo->prepare('INSERT INTO gem_receipts(user_id,amount_rial,requested_gems,receipt_path,created_at) VALUES(?,?,?,?,?)')->execute([$user['id'],(int)$_POST['amount_rial'],(int)$_POST['requested_gems'],trim($_POST['receipt_path']),now()]); ok();
    case 'profile_update':
        $pdo->prepare('UPDATE users SET display_name=?, instagram=?, rubika=? WHERE id=?')->execute([trim($_POST['display_name']),trim($_POST['instagram']),trim($_POST['rubika']),$user['id']]); ok();
    case 'report_player':
        $pdo->prepare('INSERT INTO reports(reporter_id,against_user_id,reason,details,created_at) VALUES(?,?,?,?,?)')->execute([$user['id'],(int)$_POST['against_user_id'],trim($_POST['reason']),trim($_POST['details']),now()]); ok();
    case 'admin_reports':
        if (!can_access_admin($user)) fail('عدم دسترسی',403);
        ok($pdo->query('SELECT r.*, u.display_name reporter, a.display_name against_name FROM reports r JOIN users u ON u.id=r.reporter_id JOIN users a ON a.id=r.against_user_id ORDER BY r.id DESC LIMIT 100')->fetchAll());
    case 'admin_set_balance':
        if (!can_access_admin($user)) fail('عدم دسترسی',403);
        $pdo->prepare('UPDATE users SET coins=coins+?, gems=gems+? WHERE id=?')->execute([(int)$_POST['coins'],(int)$_POST['gems'],(int)$_POST['user_id']]); ok();
    case 'admin_set_role':
        if (!can_access_admin($user)) fail('عدم دسترسی',403);
        $target=(int)$_POST['user_id']; $role=$_POST['role'];
        if (!in_array($role,['player','admin','senior_admin','owner'],true)) fail('نقش نامعتبر');
        if ($role==='owner' && $user['role']!=='owner') fail('فقط مالک');
        if ($user['role']==='admin' && in_array($role,['senior_admin','owner'],true)) fail('ادمین مجاز نیست');
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$target]); ok();
    case 'admin_add_avatar':
        if (!can_access_admin($user)) fail('عدم دسترسی',403);
        $pdo->prepare('INSERT INTO avatars(name,price_gem,price_coin,image_url,rarity,animated) VALUES(?,?,?,?,?,?)')->execute([trim($_POST['name']),(int)$_POST['price_gem'],(int)$_POST['price_coin'],trim($_POST['image_url']),'special',1]); ok();
    case 'admin_add_scenario_role':
        if (!can_access_admin($user)) fail('عدم دسترسی',403);
        $pdo->prepare('INSERT OR IGNORE INTO scenarios(name,description) VALUES(?,?)')->execute([trim($_POST['name']),trim($_POST['description'])]);
        $sid = (int)$pdo->query("SELECT id FROM scenarios WHERE name=".$pdo->quote(trim($_POST['name'])))->fetch()['id'];
        $pdo->prepare('INSERT INTO roles(scenario_id,name,side,power_description) VALUES(?,?,?,?)')->execute([$sid,trim($_POST['role_name']),trim($_POST['side']),'نقش سفارشی افزوده‌شده']); ok();
    default:
        fail('اکشن نامعتبر',404);
}
