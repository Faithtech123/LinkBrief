<?php
require 'config.php';
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if($action==='register'){
    $email=trim($input['email']??''); $pwd=$input['password']??'';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pwd)<6) exit(json_encode(['error'=>'Invalid data']));
    $hash=password_hash($pwd,PASSWORD_DEFAULT);
    try{ $pdo->prepare("INSERT INTO users (email,password_hash) VALUES (?,?)")->execute([$email,$hash]); echo json_encode(['success'=>true]); }
    catch(Exception $e){ echo json_encode(['error'=>'Email exists']); } exit;
}
if($action==='login'){
    $stmt=$pdo->prepare("SELECT * FROM users WHERE email=?"); $stmt->execute([$input['email']??'']);
    $u=$stmt->fetch(); if($u && password_verify($input['password']??'', $u['password_hash'])){
        $_SESSION['uid']=$u['id']; $_SESSION['email']=$u['email']; echo json_encode(['success'=>true,'email'=>$u['email']]);
    }else echo json_encode(['error'=>'Invalid']); exit;
}
if($action==='logout'){ session_destroy(); echo json_encode(['success'=>true]); exit; }
if($action==='me'){ echo json_encode(['user'=>$_SESSION['email']??null]); exit; }
function requireAuth(){ if(!isset($_SESSION['uid'])){ http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; } }

if($action==='create'){
    $long=trim($input['longUrl']??'');
    if(!filter_var($long,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($long, PHP_URL_SCHEME) ?: ''), ['http','https'], true)) {
        http_response_code(400); exit(json_encode(['error'=>'Enter a valid http or https URL']));
    }
    $authenticated=isset($_SESSION['uid']);
    if(!$authenticated){
        $now=time();
        $recent=array_values(array_filter($_SESSION['guest_create_times']??[], function($createdAt) use ($now) { return $createdAt > $now-3600; }));
        if(count($recent)>=10){
            http_response_code(429); exit(json_encode(['error'=>'Guest limit reached. Try again in an hour or sign in for more links.']));
        }
        $_SESSION['guest_create_times']=$recent;
    }
    $slug=$authenticated?trim($input['customSlug']??''):'';
    if(!$slug) $slug=bin2hex(random_bytes(4));
    $slug=preg_replace('/[^a-zA-Z0-9-_]/','',$slug);
    $expiry=$authenticated?($input['expiry']?:null):null;
    $pass=$authenticated?($input['password']?:null):null;
    $passHash=$pass?password_hash($pass,PASSWORD_DEFAULT):null;
    try{
        $pdo->prepare("INSERT INTO links (user_id,long_url,slug,password_hash,expiry_date) VALUES (?,?,?,?,?)")->execute([$authenticated?$_SESSION['uid']:null,$long,$slug,$passHash,$expiry]);
        if(!$authenticated) {
            $_SESSION['guest_create_times'][]=time();
        }
        echo json_encode(['success'=>true,'slug'=>$slug]);
    } catch(PDOException $e) {
        if($e->getCode()==='23000') {
            http_response_code(409); echo json_encode(['error'=>'That custom slug is already taken']);
        } else {
            http_response_code(500); echo json_encode(['error'=>'Could not create your link']);
        }
    }
    exit;
}
if($action==='my-links'){
    requireAuth(); $stmt=$pdo->prepare("SELECT id,long_url,slug,clicks,expiry_date,password_hash,created_at FROM links WHERE user_id=? ORDER BY id DESC"); $stmt->execute([$_SESSION['uid']]); $links=$stmt->fetchAll();
    foreach($links as &$l){ $isExpired=$l['expiry_date'] && strtotime($l['expiry_date']) < time(); $l['status']=$isExpired?'expired':($l['password_hash']?'protected':'active'); unset($l['password_hash']); }
    $total=$pdo->prepare("SELECT COALESCE(SUM(clicks),0) as c, COUNT(*) as t FROM links WHERE user_id=?"); $total->execute([$_SESSION['uid']]); $agg=$total->fetch();
    $chart=$pdo->prepare("SELECT DATE(clicked_at) as d, COUNT(*) as c FROM clicks_log WHERE link_id IN (SELECT id FROM links WHERE user_id=?) AND clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(clicked_at) ORDER BY d"); $chart->execute([$_SESSION['uid']]); echo json_encode(['links'=>$links,'totalClicks'=>$agg['c'],'totalLinks'=>$agg['t'],'chart'=>$chart->fetchAll()]); exit;
}
if($action==='delete'){ requireAuth(); $pdo->prepare("DELETE FROM links WHERE id=? AND user_id=?")->execute([$input['id']??0, $_SESSION['uid']]); echo json_encode(['success'=>true]); exit; }
if($action==='analytics'){ requireAuth(); $stmt=$pdo->prepare("SELECT cl.*, l.slug FROM clicks_log cl JOIN links l ON cl.link_id=l.id WHERE l.user_id=? ORDER BY cl.clicked_at DESC LIMIT 100"); $stmt->execute([$_SESSION['uid']]); echo json_encode(['logs'=>$stmt->fetchAll()]); exit; }
if($action==='check-slug'){ $slug=preg_replace('/[^a-zA-Z0-9-_]/','', $_GET['slug']??''); $stmt=$pdo->prepare("SELECT id FROM links WHERE slug=?"); $stmt->execute([$slug]); echo json_encode(['available'=>!$stmt->fetch()]); exit; }
if(isset($_GET['s'])){
    $slug=preg_replace('/[^a-zA-Z0-9-_]/','', $_GET['s']); $stmt=$pdo->prepare("SELECT * FROM links WHERE slug=?"); $stmt->execute([$slug]); $link=$stmt->fetch();
    if(!$link) die("<h1>404 Not Found</h1>"); if($link['expiry_date'] && strtotime($link['expiry_date']) < time()) die("<h1>Link expired</h1>");
    if($link['password_hash']){ $p=$_GET['p']??''; if(!$p || !password_verify($p, $link['password_hash'])) die('<form style="max-width:300px;margin:100px auto;text-align:center"><h2>🔒 Protected</h2><input type="password" name="p" placeholder="Password" style="padding:10px;width:100%"><br><button style="padding:10px 20px;background:#000;color:#fff">Unlock</button></form>'); }
    $pdo->prepare("UPDATE links SET clicks=clicks+1 WHERE id=?")->execute([$link['id']]); $pdo->prepare("INSERT INTO clicks_log (link_id,ip_address,user_agent,referrer) VALUES (?,?,?,?)")->execute([$link['id'], $_SERVER['REMOTE_ADDR']??'', $_SERVER['HTTP_USER_AGENT']??'', $_SERVER['HTTP_REFERER']??'']); header("Location: ".$link['long_url']); exit;
}