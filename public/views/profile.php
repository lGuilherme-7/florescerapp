  <?php
  // ============================================================
  // /public/views/profile.php — florescer v2.0
  // Usa APENAS tabela users — sem user_profile separada
  // ============================================================
  require_once __DIR__ . '/../../includes/session.php';
  require_once __DIR__ . '/../../includes/auth.php';
  require_once __DIR__ . '/../../config/db.php';

  startSession();
authGuard();

  $user   = currentUser();
  $userId = (int)$user['id'];
  $currentPage = 'profile';

  // ── Garante colunas extras em users ───────────────────────────
  $cols = array_column(dbQuery("SHOW COLUMNS FROM users"), 'Field');
  $alters = [];
  if (!in_array('nickname',     $cols)) $alters[] = "ADD COLUMN nickname     VARCHAR(60)  DEFAULT NULL";
  if (!in_array('school',       $cols)) $alters[] = "ADD COLUMN school       VARCHAR(120) DEFAULT NULL";
  if (!in_array('city',         $cols)) $alters[] = "ADD COLUMN city         VARCHAR(80)  DEFAULT NULL";
  if (!in_array('bio',          $cols)) $alters[] = "ADD COLUMN bio          VARCHAR(280) DEFAULT NULL";
  if (!in_array('class_grade',  $cols)) $alters[] = "ADD COLUMN class_grade  VARCHAR(60)  DEFAULT NULL";
  if (!in_array('avatar_emoji', $cols)) $alters[] = "ADD COLUMN avatar_emoji VARCHAR(10)  DEFAULT NULL";
  if (!in_array('avatar_url',   $cols)) $alters[] = "ADD COLUMN avatar_url   VARCHAR(500) DEFAULT NULL";
  if (!in_array('avatar_type',  $cols)) $alters[] = "ADD COLUMN avatar_type  VARCHAR(20)  DEFAULT 'initial'";
  if ($alters) dbExec("ALTER TABLE users " . implode(', ', $alters));

  // ── Lê dados ──────────────────────────────────────────────────
  $u = dbRow('SELECT * FROM users WHERE id=?', [$userId]);
  if (!$u) $u = $user;

  $name        = htmlspecialchars($u['name']         ?? 'Estudante', ENT_QUOTES, 'UTF-8');
  $userInitial = strtoupper(mb_substr($u['name'] ?? 'E', 0, 1, 'UTF-8'));
  $email       = htmlspecialchars($u['email']        ?? '', ENT_QUOTES, 'UTF-8');
  $goalMin     = (int)($u['daily_goal_min']          ?? 30);
  $xp          = (int)($u['xp']                     ?? 0);
  $level       = (int)($u['level']                   ?? 1);
  $streak      = (int)($u['streak']                  ?? 0);
  $nickname    = htmlspecialchars($u['nickname']      ?? '', ENT_QUOTES, 'UTF-8');
  $school      = htmlspecialchars($u['school']        ?? '', ENT_QUOTES, 'UTF-8');
  $city        = htmlspecialchars($u['city']          ?? '', ENT_QUOTES, 'UTF-8');
  $bio         = htmlspecialchars($u['bio']           ?? '', ENT_QUOTES, 'UTF-8');
  $classGrade  = htmlspecialchars($u['class_grade']   ?? '', ENT_QUOTES, 'UTF-8');
  $avatarEmoji = htmlspecialchars($u['avatar_emoji']  ?? '', ENT_QUOTES, 'UTF-8');
  $avatarUrl   = $u['avatar_url'] ?? '';   // URL relativa ex: /uploads/avatars/x.jpg
  $avatarType  = $u['avatar_type'] ?? 'initial';

  // URL pública para exibir no browser
  $avatarPublicUrl = $avatarUrl ? '/florescer/public' . $avatarUrl : '';

  // ── Stats ──────────────────────────────────────────────────────
  $totalLessons = (int)(dbRow(
      'SELECT COUNT(*) AS n FROM lessons l
      JOIN topics t ON t.id=l.topic_id
      JOIN subjects s ON s.id=t.subject_id
      JOIN objectives o ON o.id=s.objective_id
      WHERE o.user_id=? AND l.is_completed=1', [$userId]
  )['n'] ?? 0);

  $totalMin = 0;
  try {
      $totalMin = (int)(dbRow(
          'SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries WHERE user_id=?', [$userId]
      )['n'] ?? 0);
  } catch (\Throwable $e) {}

  $totalHours = $totalMin >= 60 ? round($totalMin/60,1).'h' : $totalMin.'min';

  $totalWorks = 0; $worksDelivered = 0;
  try {
      $totalWorks     = (int)(dbRow('SELECT COUNT(*) AS n FROM works WHERE user_id=?',[$userId])['n']??0);
      $worksDelivered = (int)(dbRow("SELECT COUNT(*) AS n FROM works WHERE user_id=? AND status='entregue'",[$userId])['n']??0);
  } catch (\Throwable $e) {}

  // ── Sidebar vars ───────────────────────────────────────────────
  $stages = [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
            [30,59,'🌲','Planta Forte'],[60,99,'🌳','Árvore Crescendo'],[100,149,'🌴','Árvore Robusta'],
            [150,199,'🎋','Árvore Antiga'],[200,299,'✨','Árvore Gigante'],[300,PHP_INT_MAX,'🏆','Árvore Lendária']];
  $plant=['emoji'=>'🌱','name'=>'Semente','pct'=>0];
  $plantEmoji='🌱'; $plantName='Semente';
  foreach ($stages as [$mn,$mx,$em,$nm]) {
      if ($streak>=$mn && $streak<=$mx) {
          $r2=$mx<PHP_INT_MAX?$mx-$mn+1:1;
          $plant=['emoji'=>$em,'name'=>$nm,'pct'=>$mx<PHP_INT_MAX?min(100,round(($streak-$mn)/$r2*100)):100];
          $plantEmoji=$em; $plantName=$nm; break;
      }
  }
  $lvN=['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
  $lvName = $lvN[min($level,count($lvN)-1)] ?? 'Lendário';
  $userName = $name;
  $allObjs = dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC',[$userId]);
  if (!isset($_SESSION['active_objective'])) {
      $ao=dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1',[$userId]);
      if(!$ao)$ao=dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1',[$userId]);
      $_SESSION['active_objective']=$ao['id']??null;
  }
  $activeObjId=$_SESSION['active_objective'];
  ?>
  <!DOCTYPE html>
  <html lang="pt-BR">
  <head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>florescer — Perfil</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,700;0,9..144,900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --g950:#0d1f16;--g900:#132a1e;--g800:#1a3a2a;--g700:#1e4d35;
      --g600:#2d6a4f;--g500:#40916c;--g400:#52b788;--g300:#74c69d;
      --g200:#b7e4c7;--g100:#d8f3dc;--g50:#f0faf4;
      --n800:#1c1c1a;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
      --gold:#c9a84c;--red:#dc2626;--red-l:#fee2e2;
      --sw:240px;--hh:58px;
      --fd:'Fraunces',Georgia,serif;--fb:'Inter',system-ui,sans-serif;
      --r:12px;--rs:7px;--d:.22s;--e:cubic-bezier(.4,0,.2,1);
      --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 2px 8px rgba(0,0,0,.07);
      --sh2:0 4px 16px rgba(0,0,0,.09);--sh3:0 12px 32px rgba(0,0,0,.14);
    }
    html,body{height:100%}
    body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;overflow-x:hidden;-webkit-font-smoothing:antialiased}
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}

    /* Sidebar */
    .sidebar{width:var(--sw);height:100vh;position:fixed;top:0;left:0;background:var(--g800);display:flex;flex-direction:column;z-index:50;overflow:hidden;transition:transform var(--d) var(--e);border-right:1px solid rgba(116,198,157,.08)}
    .sb-logo{padding:.95rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);display:flex;align-items:center;gap:.5rem;flex-shrink:0}
    .sb-logo-icon{width:28px;height:28px;background:linear-gradient(135deg,var(--g500),var(--g700));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;box-shadow:0 2px 8px rgba(64,145,108,.22)}
    .sb-logo-name{font-family:var(--fd);font-size:1.05rem;font-weight:700;color:var(--g200);letter-spacing:-.02em}
    .sb-logo-sub{font-size:.56rem;color:rgba(116,198,157,.3);text-transform:uppercase;letter-spacing:.1em;margin-top:.08rem}
    .sb-profile{padding:.72rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);display:flex;align-items:center;gap:.6rem;text-decoration:none;flex-shrink:0;transition:background var(--d) var(--e)}
    .sb-profile:hover{background:rgba(116,198,157,.04)}
    .sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.86rem;font-weight:700;color:var(--white);flex-shrink:0;overflow:hidden;box-shadow:0 0 0 2px rgba(116,198,157,.18)}
    .sb-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
    .sb-pname{font-size:.82rem;font-weight:500;color:var(--g100);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sb-plevel{font-size:.68rem;color:var(--g300);margin-top:.06rem;opacity:.7}
    .sb-plant{padding:.6rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);flex-shrink:0}
    .sb-plant-row{display:flex;align-items:center;gap:.5rem}
    .sb-pemoji{font-size:1.25rem;animation:breathe 4s ease-in-out infinite;flex-shrink:0}
    @keyframes breathe{0%,100%{transform:scale(1)}50%{transform:scale(1.07) translateY(-1px)}}
    .sb-pname2{font-size:.7rem;font-weight:600;color:var(--g300)}
    .sb-pstreak{font-size:.64rem;color:rgba(116,198,157,.4);margin-top:.06rem}
    .sb-pbar{height:2px;background:rgba(116,198,157,.1);border-radius:1px;margin-top:.28rem;overflow:hidden}
    .sb-pbar-fill{height:100%;background:linear-gradient(90deg,var(--g400),var(--g200));transition:width .6s var(--e)}
    .sb-obj{padding:.5rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);flex-shrink:0}
    .sb-obj-lbl{font-size:.58rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(116,198,157,.28);display:block;margin-bottom:.22rem}
    .sb-obj-sel{width:100%;background:none;border:none;color:var(--g300);font-family:var(--fb);font-size:.78rem;font-weight:500;cursor:pointer;padding:0;outline:none;appearance:none}
    .sb-obj-sel option{background:var(--g800)}
    .sb-nav{flex:1;overflow-y:auto;padding:.45rem 0}
    .sb-nav-grp{font-size:.57rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(116,198,157,.25);padding:.7rem 1.1rem .25rem;display:block}
    .sb-nav-a{display:flex;align-items:center;gap:.55rem;padding:.48rem 1.1rem;color:rgba(183,228,199,.48);font-size:.81rem;text-decoration:none;transition:all var(--d) var(--e);border-left:2px solid transparent}
    .sb-nav-a:hover{color:var(--g300);background:rgba(116,198,157,.04)}
    .sb-nav-a.active{color:var(--g300);background:rgba(116,198,157,.07);border-left-color:var(--g400);font-weight:500}
    .sb-nav-ico{font-size:.85rem;min-width:.95rem;text-align:center}
    .sb-footer{padding:.75rem 1rem;border-top:1px solid rgba(116,198,157,.08);flex-shrink:0}
    .sb-logout{display:flex;align-items:center;gap:.4rem;width:100%;padding:.44rem .7rem;background:none;border:1px solid rgba(220,100,100,.13);border-radius:var(--rs);color:rgba(220,100,100,.52);font-family:var(--fb);font-size:.77rem;cursor:pointer;transition:all var(--d) var(--e)}
    .sb-logout:hover{background:rgba(220,38,38,.07);color:#e07070}
    .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:49;opacity:0;transition:opacity var(--d) var(--e)}
    .sb-overlay.show{opacity:1}

    /* Main */
    .main{margin-left:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column;min-width:0}
    .topbar{height:var(--hh);background:rgba(250,248,245,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
    .tb-left{display:flex;align-items:center;gap:.8rem}
    .hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px}
    .hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
    .hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
    .hamburger.open span:nth-child(2){opacity:0}
    .hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
    .tb-title{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--n800)}
    .xp-pill{display:flex;align-items:center;gap:.28rem;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:50px;padding:.26rem .75rem;font-size:.75rem;font-weight:600;color:var(--g500);box-shadow:var(--sh0)}

    /* Layout */
    .content{flex:1;padding:1.8rem 2rem;display:flex;flex-direction:column;gap:1.5rem}
    .page-header h1{font-family:var(--fd);font-size:1.55rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
    .page-header p{font-size:.82rem;color:#aaa;margin-top:.22rem}
    .profile-grid{display:grid;grid-template-columns:300px 1fr;gap:1.5rem;align-items:start}

    /* Card do perfil */
    .wrapped-card{background:linear-gradient(160deg,var(--g800) 0%,var(--g950) 55%,#0a1a10 100%);border-radius:20px;border:1px solid rgba(116,198,157,.1);box-shadow:var(--sh3);overflow:hidden;position:relative;padding:2rem 1.6rem 1.6rem;display:flex;flex-direction:column;align-items:center;text-align:center}
    .wrapped-card::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(116,198,157,.06) 0%,transparent 70%);pointer-events:none}
    .wc-deco{position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--g500),var(--g300),var(--gold));opacity:.7}
    .wc-avatar-wrap{position:relative;margin-bottom:2rem;cursor:pointer}
    .wc-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--g700));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:2rem;font-weight:700;color:var(--white);overflow:hidden;box-shadow:0 0 0 3px rgba(116,198,157,.18),0 0 0 6px rgba(116,198,157,.06),0 8px 20px rgba(0,0,0,.2);transition:transform var(--d) var(--e)}
    .wc-avatar:hover{transform:scale(1.04)}
    .wc-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
    .wc-avatar-emoji{font-size:2.4rem;line-height:1}
    .wc-edit-badge{position:absolute;bottom:2px;right:2px;width:22px;height:22px;background:var(--g500);border:2px solid var(--g800);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;z-index:2}
    .wc-level-badge{position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--g600),var(--g700));color:var(--g200);font-size:.53rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;padding:.2rem .55rem;border-radius:20px;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.22);border:1px solid rgba(116,198,157,.18)}
    .wc-name{font-family:var(--fd);font-size:1.25rem;font-weight:700;color:var(--g100);letter-spacing:-.025em;margin-bottom:.2rem;position:relative;z-index:1}
    .wc-nick{font-size:.74rem;color:rgba(116,198,157,.35);margin-bottom:.4rem;z-index:1;position:relative}
    .wc-meta{font-size:.72rem;color:rgba(116,198,157,.4);margin-bottom:1.1rem;z-index:1;position:relative;line-height:1.6}
    .wc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;width:100%;margin-bottom:.8rem;z-index:1;position:relative}
    .wc-stat{background:rgba(255,255,255,.04);border:1px solid rgba(116,198,157,.08);border-radius:10px;padding:.65rem .4rem}
    .wc-stat-val{font-family:var(--fd);font-size:1.25rem;font-weight:700;color:var(--g200);line-height:1}
    .wc-stat-lbl{font-size:.58rem;color:rgba(116,198,157,.38);text-transform:uppercase;letter-spacing:.06em;margin-top:.12rem}
    .wc-stats-2{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;width:100%;margin-bottom:1rem;z-index:1;position:relative}
    .wc-plant{background:rgba(255,255,255,.03);border:1px solid rgba(116,198,157,.08);border-radius:12px;padding:.75rem 1rem;width:100%;display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;position:relative;z-index:1;text-align:left}
    .wc-plant-emoji{font-size:1.9rem;animation:breathe 4s ease-in-out infinite}
    .wc-plant-name{font-family:var(--fd);font-size:.85rem;font-weight:600;color:var(--g300)}
    .wc-plant-sub{font-size:.65rem;color:rgba(116,198,157,.35);margin-top:.08rem}
    .btn-share{width:100%;padding:.72rem;background:linear-gradient(135deg,var(--g500),var(--g600));border:none;border-radius:50px;color:var(--white);font-family:var(--fb);font-size:.83rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);display:flex;align-items:center;justify-content:center;gap:.45rem;box-shadow:0 4px 16px rgba(64,145,108,.3);position:relative;z-index:1}
    .btn-share:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(64,145,108,.4)}

    /* Painel direito */
    .right-panel{display:flex;flex-direction:column;gap:1rem}
    .sec-card{background:var(--white);border:1px solid rgba(0,0,0,.055);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden}
    .sec-head{padding:.85rem 1.3rem;border-bottom:1px solid rgba(0,0,0,.05);display:flex;align-items:center;gap:.5rem;background:var(--n50)}
    .sec-head-ico{font-size:1rem}
    .sec-head-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800)}
    .sec-body{padding:1.2rem 1.3rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
    .form-grid.cols1{grid-template-columns:1fr}
    .fg{display:flex;flex-direction:column;gap:.28rem}
    .fg.span2{grid-column:1/-1}
    .lbl{font-size:.74rem;font-weight:500;color:#999}
    .inp{padding:.62rem .85rem;background:var(--n50);border:1px solid rgba(0,0,0,.08);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.86rem;transition:all var(--d) var(--e);outline:none;width:100%}
    .inp:focus{border-color:var(--g400);background:var(--g50);box-shadow:0 0 0 3px rgba(64,145,108,.1)}
    .inp.err{border-color:var(--red);box-shadow:0 0 0 3px rgba(220,38,38,.08)}
    textarea.inp{resize:vertical;min-height:78px}
    .inp-hint{font-size:.69rem;color:#bbb}
    .ferr{font-size:.71rem;color:var(--red);display:none}
    .ferr.on{display:block}
    .inp-wrap{position:relative}
    .inp-wrap .inp{padding-right:2.5rem}
    .eye-btn{position:absolute;right:.7rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#ccc;padding:.15rem;transition:color var(--d) var(--e)}
    .eye-btn:hover{color:var(--g500)}
    .eye-btn svg{width:16px;height:16px}
    .str-bar{height:3px;border-radius:2px;background:rgba(0,0,0,.07);overflow:hidden;margin-top:.3rem}
    .str-fill{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
    .str-txt{font-size:.69rem;color:#bbb;margin-top:.18rem;min-height:1em}
    .btn-save{margin-top:.85rem;padding:.62rem 1.4rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white);border:none;border-radius:50px;font-family:var(--fb);font-size:.83rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 12px rgba(64,145,108,.25)}
    .btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(64,145,108,.35)}
    .btn-save:disabled{opacity:.6;cursor:not-allowed;transform:none}

    /* Avatar section */
    .avatar-section{display:grid;grid-template-columns:1fr 1fr;gap:1.1rem}
    .upload-zone{border:1.5px dashed rgba(0,0,0,.1);border-radius:var(--r);padding:1.2rem;text-align:center;cursor:pointer;transition:all var(--d) var(--e);background:var(--n50);position:relative}
    .upload-zone:hover,.upload-zone.drag{border-color:var(--g400);background:var(--g50)}
    .upload-zone input[type=file]{display:none}
    .upload-ico{font-size:1.4rem;opacity:.4;margin-bottom:.3rem}
    .upload-lbl{font-size:.78rem;font-weight:500;color:#888}
    .upload-sub{font-size:.68rem;color:#bbb;margin-top:.12rem}
    .upload-prev-img{width:60px;height:60px;border-radius:50%;object-fit:cover;margin:0 auto .4rem;display:block;box-shadow:var(--sh1)}
    .btn-upload-save{width:100%;margin-top:.6rem;padding:.52rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:#fff;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;display:none;transition:all var(--d) var(--e)}
    .btn-upload-save:hover{filter:brightness(1.08)}
    .current-photo-box{display:flex;align-items:center;gap:.6rem;padding:.6rem .85rem;background:var(--n50);border:1px solid rgba(0,0,0,.07);border-radius:var(--rs);margin-top:.6rem}
    .current-photo-img{width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0}
    .current-photo-info{flex:1;min-width:0}
    .current-photo-lbl{font-size:.74rem;font-weight:500;color:var(--n800)}
    .current-photo-sub{font-size:.67rem;color:#aaa}
    .btn-remove{font-size:.71rem;color:var(--red);background:none;border:none;cursor:pointer;flex-shrink:0}
    .av-sect-lbl{font-size:.73rem;font-weight:600;color:#888;display:block;margin-bottom:.4rem}
    .avatar-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.3rem}
    .av-btn{width:36px;height:36px;border-radius:8px;border:1.5px solid rgba(0,0,0,.07);background:var(--n50);font-size:1.15rem;cursor:pointer;transition:all var(--d) var(--e);display:flex;align-items:center;justify-content:center}
    .av-btn:hover{border-color:var(--g400);transform:scale(1.08)}
    .av-btn.sel{border-color:var(--g500);background:var(--g50);box-shadow:0 0 0 2px rgba(64,145,108,.18)}

    /* Zona de perigo */
    .danger-zone{background:rgba(220,38,38,.03);border:1px solid rgba(220,38,38,.15);border-radius:var(--r);overflow:hidden}
    .danger-head{padding:.85rem 1.3rem;border-bottom:1px solid rgba(220,38,38,.12);display:flex;align-items:center;gap:.5rem;background:rgba(220,38,38,.04)}
    .danger-head-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--red)}
    .danger-body{padding:1.2rem 1.3rem}
    .danger-desc{font-size:.8rem;color:#888;line-height:1.6;margin-bottom:.9rem}
    .btn-danger-open{padding:.55rem 1.1rem;background:transparent;border:1.5px solid rgba(220,38,38,.3);border-radius:50px;color:var(--red);font-family:var(--fb);font-size:.79rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e)}
    .btn-danger-open:hover{background:var(--red-l)}

    /* Modal */
    .modal-overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.45);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e)}
    .modal-overlay.open{opacity:1;pointer-events:all}
    .modal{background:var(--white);border:1px solid rgba(0,0,0,.08);border-radius:var(--r);width:100%;max-width:420px;box-shadow:var(--sh3);transform:translateY(14px) scale(.97);transition:transform var(--d) var(--e)}
    .modal-overlay.open .modal{transform:translateY(0) scale(1)}
    .modal-head{padding:.95rem 1.2rem;border-bottom:1px solid rgba(220,38,38,.15);display:flex;align-items:center;justify-content:space-between;background:rgba(220,38,38,.03)}
    .modal-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--red)}
    .modal-x{width:26px;height:26px;border-radius:50%;background:var(--n100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:#aaa;transition:all var(--d) var(--e)}
    .modal-x:hover{background:var(--red-l);color:var(--red)}
    .modal-body{padding:1.2rem}
    .modal-warn{font-size:.81rem;color:#666;line-height:1.65;margin-bottom:.9rem;padding:.7rem;background:rgba(220,38,38,.04);border-radius:var(--rs);border-left:3px solid rgba(220,38,38,.3)}
    .modal-foot{padding:.85rem 1.2rem;border-top:1px solid rgba(0,0,0,.06);display:flex;gap:.5rem;justify-content:flex-end}
    .btn-cancel{padding:.52rem 1rem;background:transparent;border:1px solid rgba(0,0,0,.1);border-radius:50px;font-family:var(--fb);font-size:.79rem;color:#888;cursor:pointer;transition:all var(--d) var(--e)}
    .btn-cancel:hover{background:var(--n100)}
    .btn-confirm-del{padding:.52rem 1rem;background:var(--red);border:none;border-radius:50px;color:#fff;font-family:var(--fb);font-size:.79rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e)}
    .btn-confirm-del:hover{background:#b91c1c}
    .btn-confirm-del:disabled{opacity:.5;cursor:not-allowed}
    .del-err{font-size:.71rem;color:var(--red);margin-top:.4rem;min-height:1em}

    /* Toast */
    .toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
    .toast{background:var(--n800);color:#eee;padding:.62rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .24s var(--e) both;max-width:280px;box-shadow:var(--sh3)}
    .toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}
    @keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

    @media(max-width:1100px){.profile-grid{grid-template-columns:270px 1fr}}
    @media(max-width:900px){.profile-grid{grid-template-columns:1fr}.avatar-section{grid-template-columns:1fr}}
    @media(max-width:768px){.main{margin-left:0}.hamburger{display:flex}.topbar{padding:0 1.1rem}.content{padding:1.2rem 1rem}}
    @media(max-width:520px){.form-grid{grid-template-columns:1fr}.avatar-grid{grid-template-columns:repeat(5,1fr)}}
    </style>
  </head>
    <!-- Favicon básico -->
  <link rel="icon" href="/florescer/public/img/fav/favicon.ico">

  <!-- PNG moderno -->
  <link rel="icon" type="image/png" sizes="32x32" href="/florescer/public/img/fav/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/florescer/public/img/fav/favicon-16.png">

  <!-- Apple (iOS) -->
  <link rel="apple-touch-icon" sizes="180x180" href="/florescer/public/img/fav/favicon-180.png">

  <!-- Android / PWA -->
  <link rel="manifest" href="/florescer/public/img/fav/site.webmanifest">

  <!-- Windows (tiles) -->
  <meta name="msapplication-TileImage" content="/florescer/public/img/fav/mstile-150x150.png">
  <meta name="msapplication-TileColor" content="#ffffff">

  <!-- Cor da barra do navegador (mobile) -->
  <meta name="theme-color" content="#ffffff">
  <body>

  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="main">
    <header class="topbar">
      <div class="tb-left">
        <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
          <span></span><span></span><span></span>
        </button>
        <span class="tb-title">Perfil</span>
      </div>
      <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
    </header>

    <main class="content">
      <div class="page-header">
        <h1>Meu Perfil</h1>
        <p>Personalize sua conta e acompanhe seu progresso</p>
      </div>

      <div class="profile-grid">

        <!-- ══ CARD ESQUERDA ══ -->
        <div class="wrapped-card" id="wrappedCard">
          <div class="wc-deco"></div>

          <div class="wc-avatar-wrap" onclick="document.getElementById('fileInput').click()" title="Alterar foto">
            <div class="wc-avatar" id="wcAvatar">
              <?php if ($avatarType === 'upload' && $avatarPublicUrl): ?>
                <img src="<?= htmlspecialchars($avatarPublicUrl,ENT_QUOTES) ?>" alt="Avatar"/>
              <?php elseif ($avatarType === 'emoji' && $avatarEmoji): ?>
                <span class="wc-avatar-emoji"><?= $avatarEmoji ?></span>
              <?php else: ?>
                <span><?= $userInitial ?></span>
              <?php endif; ?>
            </div>
            <div class="wc-edit-badge">✏️</div>
            <div class="wc-level-badge">Nível <?= $level ?> — <?= $lvName ?></div>
          </div>

          <div class="wc-name" id="wcName"><?= $name ?></div>
          <div class="wc-nick" id="wcNick"><?= $nickname ? '@'.$nickname : '@estudante' ?></div>
          <?php if ($school || $city): ?>
          <div class="wc-meta">
            <?= $school ? '🏫 '.htmlspecialchars($school,ENT_QUOTES) : '' ?>
            <?= ($school && $city) ? ' · ' : '' ?>
            <?= $city ? '📍 '.htmlspecialchars($city,ENT_QUOTES) : '' ?>
          </div>
          <?php endif; ?>

          <div class="wc-stats">
            <div class="wc-stat"><div class="wc-stat-val"><?= $streak ?></div><div class="wc-stat-lbl">Dias</div></div>
            <div class="wc-stat"><div class="wc-stat-val"><?= number_format($xp) ?></div><div class="wc-stat-lbl">XP</div></div>
            <div class="wc-stat"><div class="wc-stat-val"><?= $totalLessons ?></div><div class="wc-stat-lbl">Aulas</div></div>
          </div>
          <div class="wc-stats-2">
            <div class="wc-stat"><div class="wc-stat-val"><?= $totalHours ?></div><div class="wc-stat-lbl">Estudado</div></div>
            <div class="wc-stat"><div class="wc-stat-val"><?= $worksDelivered ?>/<?= $totalWorks ?></div><div class="wc-stat-lbl">Trabalhos</div></div>
          </div>
          <div class="wc-plant">
            <span class="wc-plant-emoji"><?= $plantEmoji ?></span>
            <div>
              <div class="wc-plant-name"><?= htmlspecialchars($plantName,ENT_QUOTES) ?></div>
              <div class="wc-plant-sub"><?= $streak ?> dias consecutivos</div>
            </div>
          </div>
          <button class="btn-share" onclick="generateStory()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
            Compartilhar Story
          </button>
        </div>

        <!-- ══ PAINEL DIREITO ══ -->
        <div class="right-panel">

          <!-- 1. Dados pessoais -->
          <div class="sec-card">
            <div class="sec-head"><span class="sec-head-ico">👤</span><span class="sec-head-title">Dados pessoais</span></div>
            <div class="sec-body">
              <form id="fPersonal" novalidate>
                <div class="form-grid">
                  <div class="fg">
                    <label class="lbl">Nome completo *</label>
                    <input class="inp" type="text" id="pName" value="<?= $name ?>" placeholder="Seu nome" required maxlength="150"/>
                    <span class="ferr" id="pNameE">Informe seu nome</span>
                  </div>
                  <div class="fg">
                    <label class="lbl">Apelido</label>
                    <input class="inp" type="text" id="pNick" value="<?= $nickname ?>" placeholder="@apelido" maxlength="60"/>
                  </div>
                  <div class="fg span2">
                    <label class="lbl">E-mail</label>
                    <input class="inp" type="email" value="<?= $email ?>" readonly style="opacity:.5;cursor:default"/>
                    <span class="inp-hint">O e-mail não pode ser alterado</span>
                  </div>
                  <div class="fg">
                    <label class="lbl">Escola / Instituição</label>
                    <input class="inp" type="text" id="pSchool" value="<?= $school ?>" placeholder="Nome da escola" maxlength="120"/>
                  </div>
                  <div class="fg">
                    <label class="lbl">Cidade</label>
                    <input class="inp" type="text" id="pCity" value="<?= $city ?>" placeholder="Sua cidade" maxlength="80"/>
                  </div>
                  <div class="fg span2">
                    <label class="lbl">Bio</label>
                    <textarea class="inp" id="pBio" maxlength="280" placeholder="Uma frase sobre você…"><?= $bio ?></textarea>
                    <span class="inp-hint" id="bioCount"><?= mb_strlen($u['bio']??'','UTF-8') ?>/280</span>
                  </div>
                </div>
                <button type="submit" class="btn-save">Salvar dados pessoais</button>
              </form>
            </div>
          </div>

          <!-- 2. Foto e Avatar -->
          <div class="sec-card">
            <div class="sec-head"><span class="sec-head-ico">🖼️</span><span class="sec-head-title">Foto e Avatar</span></div>
            <div class="sec-body">
              <!-- Input de arquivo oculto -->
              <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewFile(this)"/>

              <div class="avatar-section">
                <!-- Upload foto -->
                <div>
                  <span class="av-sect-lbl">📷 Foto de perfil</span>
                  <div class="upload-zone" id="uploadZone"
                      onclick="document.getElementById('fileInput').click()"
                      ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFile(event)">
                    <div id="uploadDefault">
                      <div class="upload-ico">📷</div>
                      <div class="upload-lbl">Clique ou arraste</div>
                      <div class="upload-sub">JPG, PNG ou WEBP · Máx. 2MB</div>
                    </div>
                    <div id="uploadPreview" style="display:none">
                      <img id="previewImg" class="upload-prev-img" src="" alt=""/>
                      <div class="upload-lbl" id="previewName" style="font-size:.7rem"></div>
                    </div>
                  </div>
                  <button class="btn-upload-save" id="btnUpload" onclick="uploadPhoto()">💾 Salvar foto</button>

                  <?php if ($avatarType === 'upload' && $avatarPublicUrl): ?>
                  <div class="current-photo-box" id="currentPhotoBox">
                    <img src="<?= htmlspecialchars($avatarPublicUrl,ENT_QUOTES) ?>" class="current-photo-img" alt=""/>
                    <div class="current-photo-info">
                      <div class="current-photo-lbl">Foto atual</div>
                      <div class="current-photo-sub">Enviada por você</div>
                    </div>
                    <button class="btn-remove" onclick="removePhoto()">Remover</button>
                  </div>
                  <?php endif; ?>
                </div>

                <!-- Avatar emoji -->
                <div>
                  <span class="av-sect-lbl">🎭 Avatar emoji</span>
                  <div class="avatar-grid">
                    <?php
                    $avatars=['🎃','🌿','👻','🎓','🦋','🌸','⭐','🦉','🦊','🌻',
                              '🐯','🐬','🌲','🏕️','🌺','🎯','🦅','🍀','🌙','🔮',
                              '🎪','🏆','💎','🌌','🦁','🐉','🥇','🎭','🚀','🦄'];
                    foreach ($avatars as $av): ?>
                    <button class="av-btn <?= ($avatarType==='emoji'&&$avatarEmoji===$av)?'sel':'' ?>"
                            onclick="selectAvatar('<?= htmlspecialchars($av,ENT_QUOTES) ?>',this)"><?= $av ?></button>
                    <?php endforeach; ?>
                  </div>
                  <p style="font-size:.69rem;color:#bbb;margin-top:.5rem;line-height:1.5">Clique para usar como avatar</p>
                </div>
              </div>
            </div>
          </div>

          <!-- 3. Meta -->
          <div class="sec-card">
            <div class="sec-head"><span class="sec-head-ico">⏱️</span><span class="sec-head-title">Metas de estudo</span></div>
            <div class="sec-body">
              <form id="fGoal" novalidate>
                <div class="form-grid">
                  <div class="fg">
                    <label class="lbl">Meta diária (minutos)</label>
                    <input class="inp" type="number" id="gMin" value="<?= $goalMin ?>" min="30" max="480"/>
                    <span class="inp-hint">Entre 30 e 480 minutos</span>
                    <span class="ferr" id="gMinE">Valor inválido</span>
                  </div>
                  <div class="fg">
                    <label class="lbl">Turma / Série</label>
                    <input class="inp" type="text" id="gClass" value="<?= $classGrade ?>" placeholder="Ex: 3º Ano EM" maxlength="60"/>
                  </div>
                </div>
                <button type="submit" class="btn-save">Salvar configurações</button>
              </form>
            </div>
          </div>

          <!-- 4. Senha -->
          <div class="sec-card">
            <div class="sec-head"><span class="sec-head-ico">🔒</span><span class="sec-head-title">Alterar senha</span></div>
            <div class="sec-body">
              <form id="fPass" novalidate>
                <div class="form-grid cols1">
                  <div class="fg">
                    <label class="lbl">Senha atual</label>
                    <div class="inp-wrap">
                      <input class="inp" type="password" id="pCurr" placeholder="••••••••" required/>
                      <button type="button" class="eye-btn" onclick="toggleEye('pCurr',this)">
                        <svg id="eye-pCurr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      </button>
                    </div>
                    <span class="ferr" id="pCurrE">Informe a senha atual</span>
                  </div>
                  <div class="fg">
                    <label class="lbl">Nova senha</label>
                    <div class="inp-wrap">
                      <input class="inp" type="password" id="pNew" placeholder="Mín. 6 chars + 1 número" required oninput="checkStr(this.value)"/>
                      <button type="button" class="eye-btn" onclick="toggleEye('pNew',this)">
                        <svg id="eye-pNew" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      </button>
                    </div>
                    <div class="str-bar"><div class="str-fill" id="sFill"></div></div>
                    <div class="str-txt" id="sTxt"></div>
                    <span class="ferr" id="pNewE">Mín. 6 caracteres e 1 número</span>
                  </div>
                  <div class="fg">
                    <label class="lbl">Confirmar nova senha</label>
                    <div class="inp-wrap">
                      <input class="inp" type="password" id="pConf" placeholder="Repita a nova senha" required/>
                      <button type="button" class="eye-btn" onclick="toggleEye('pConf',this)">
                        <svg id="eye-pConf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      </button>
                    </div>
                    <span class="ferr" id="pConfE">As senhas não coincidem</span>
                  </div>
                </div>
                <button type="submit" class="btn-save">Alterar senha</button>
              </form>
            </div>
          </div>

          <!-- 5. Zona de perigo -->
          <div class="danger-zone">
            <div class="danger-head"><span>⚠️</span><span class="danger-head-title">Zona de perigo</span></div>
            <div class="danger-body">
              <p class="danger-desc">Excluir sua conta apagará <strong>permanentemente</strong> todos os seus dados: matérias, aulas, notas, trabalhos e progresso. Esta ação é <strong>irreversível</strong>.</p>
              <button class="btn-danger-open" onclick="openDelModal()">🗑 Excluir minha conta</button>
            </div>
          </div>

        </div>
      </div>
    </main>
  </div>

  <!-- Modal excluir conta -->
  <div class="modal-overlay" id="delModal" onclick="if(event.target===this)closeDelModal()">
    <div class="modal">
      <div class="modal-head">
        <span class="modal-title">⚠️ Excluir conta</span>
        <button class="modal-x" onclick="closeDelModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-warn">Todos os seus dados serão apagados permanentemente e não poderão ser recuperados.</div>
        <div class="fg">
          <label class="lbl">Digite sua senha para confirmar</label>
          <div class="inp-wrap">
            <input class="inp" type="password" id="delPass" placeholder="Sua senha atual"/>
            <button type="button" class="eye-btn" onclick="toggleEye('delPass',this)">
              <svg id="eye-delPass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="del-err" id="delErr"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn-cancel" onclick="closeDelModal()">Cancelar</button>
        <button class="btn-confirm-del" id="btnConfirmDel" onclick="deleteAccount()">Excluir permanentemente</button>
      </div>
    </div>
  </div>

  <canvas id="shareCanvas" width="1080" height="1920" style="position:fixed;left:-9999px;top:-9999px;pointer-events:none"></canvas>
  <div class="toast-wrap" id="toastWrap"></div>

  <script>
  const P = <?= json_encode([
    'name'        => $u['name'] ?? 'Estudante',
    'initial'     => $userInitial,
    'level'       => $level,
    'lvName'      => $lvName,
    'xp'          => $xp,
    'streak'      => $streak,
    'lessons'     => $totalLessons,
    'hours'       => $totalHours,
    'works'       => $totalWorks,
    'worksOk'     => $worksDelivered,
    'goalMin'     => $goalMin,
    'plant'       => $plantEmoji,
    'plantName'   => $plantName,
    'avatarEmoji' => $avatarEmoji,
    'avatarUrl'   => $avatarPublicUrl,
    'avatarType'  => $avatarType,
    'school'      => $school,
    'city'        => $city,
    'bio'         => $bio,
  ], JSON_UNESCAPED_UNICODE) ?>;

  /* ── Sidebar ── */
  function toggleSidebar(){
    const sb=document.querySelector('.sidebar'),hb=document.getElementById('hamburger'),ov=document.getElementById('sbOverlay');
    if(!sb)return;const open=sb.classList.toggle('open');
    if(hb)hb.classList.toggle('open',open);if(ov)ov.classList.toggle('show',open);
    document.body.style.overflow=open?'hidden':'';
  }

  /* ── Toast ── */
  function toast(msg,type='ok'){
    const w=document.getElementById('toastWrap'),t=document.createElement('div');
    t.className=`toast ${type}`;t.innerHTML=`<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
    w.appendChild(t);setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300);},3500);
  }

  /* ── API JSON ── */
  async function api(body){
    const r=await fetch('/florescer/api/profile.php',{
      method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)
    });
    const data=await r.json();return data;
  }

  /* ── Helpers formulário ── */
  function ferr(id,show){
    document.getElementById(id)?.classList.toggle('err',show);
    document.getElementById(id+'E')?.classList.toggle('on',show);
  }
  function clearErr(...ids){ids.forEach(id=>{document.getElementById(id)?.classList.remove('err');document.getElementById(id+'E')?.classList.remove('on');});}
  function setLoad(btn,on){btn.disabled=on;}

  const eyeO=`<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  const eyeC=`<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
  function toggleEye(id,btn){
    const inp=document.getElementById(id),svg=document.getElementById('eye-'+id);
    const show=inp.type==='password';inp.type=show?'text':'password';
    svg.innerHTML=show?eyeC:eyeO;btn.style.color=show?'var(--g500)':'#ccc';
  }

  function checkStr(v){
    const f=document.getElementById('sFill'),t=document.getElementById('sTxt');if(!f)return;
    let s=0;if(v.length>=6)s++;if(v.length>=10)s++;if(/\d/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;if(/[A-Z]/.test(v))s++;
    const m=[[0,'0%','transparent',''],[1,'20%','#f87171','Muito fraca'],[2,'45%','#f59e0b','Fraca'],
            [3,'70%','#40916c','Boa'],[4,'90%','#40916c','Forte'],[5,'100%','#40916c','Muito forte']];
    const[,w,c,l]=m[Math.min(s,5)];f.style.width=w;f.style.background=c;t.textContent=l;t.style.color=c;
  }

  document.getElementById('pBio')?.addEventListener('input',function(){
    document.getElementById('bioCount').textContent=this.value.length+'/280';
  });

  /* ── Dados pessoais ── */
  document.getElementById('fPersonal').addEventListener('submit',async function(e){
    e.preventDefault();clearErr('pName');
    const name=document.getElementById('pName').value.trim();
    if(!name){ferr('pName',true);return;}
    const btn=this.querySelector('.btn-save');setLoad(btn,true);btn.textContent='Salvando…';
    const r=await api({
      action:'update_personal',name,
      nickname:document.getElementById('pNick').value.trim(),
      school:document.getElementById('pSchool').value.trim(),
      city:document.getElementById('pCity').value.trim(),
      bio:document.getElementById('pBio').value.trim(),
    });
    setLoad(btn,false);btn.textContent='Salvar dados pessoais';
    if(r.success){
      toast('Dados salvos! ✅');
      document.getElementById('wcName').textContent=name;
      const nick=document.getElementById('pNick').value.trim();
      document.getElementById('wcNick').textContent=nick?'@'+nick:'@estudante';
    } else toast(r.message||'Erro.','err');
  });

  /* ── Meta ── */
  document.getElementById('fGoal').addEventListener('submit',async function(e){
    e.preventDefault();clearErr('gMin');
    const min=parseInt(document.getElementById('gMin').value);
    if(!min||min<30||min>480){ferr('gMin',true);return;}
    const btn=this.querySelector('.btn-save');setLoad(btn,true);btn.textContent='Salvando…';
    const r=await api({action:'update_goal',daily_goal_min:min,class_grade:document.getElementById('gClass').value.trim()});
    setLoad(btn,false);btn.textContent='Salvar configurações';
    if(r.success)toast('Configurações salvas! ✅');else toast(r.message||'Erro.','err');
  });

  /* ── Senha ── */
  document.getElementById('fPass').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErr('pCurr', 'pNew', 'pConf');

  const curr = document.getElementById('pCurr').value;
  const nw   = document.getElementById('pNew').value;
  const conf = document.getElementById('pConf').value;

  let ok = true;
  if (!curr)                          { ferr('pCurr', true); ok = false; }
  if (nw.length < 6 || !/\d/.test(nw)){ ferr('pNew',  true); ok = false; }
  if (nw !== conf)                    { ferr('pConf', true); ok = false; }
  if (!ok) return;

  const btn = this.querySelector('.btn-save');
  setLoad(btn, true); btn.textContent = 'Salvando…';

  // Envia como JSON — NÃO como FormData
  const r = await fetch('/florescer/api/profile.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'change_password',
      current_password: curr,
      new_password: nw
    })
  });

  let data;
  try { data = await r.json(); }
  catch(e) { data = { success: false, message: 'Resposta inválida do servidor.' }; }

  setLoad(btn, false); btn.textContent = 'Alterar senha';

  if (data.success) {
    toast('Senha alterada! 🔒');
    this.reset();
    document.getElementById('sFill').style.width = '0';
    document.getElementById('sTxt').textContent  = '';
  } else {
    toast(data.message || 'Senha incorreta.', 'err');
  }
});

  /* ── Upload ── */
  function dragOver(e){e.preventDefault();document.getElementById('uploadZone').classList.add('drag');}
  function dragLeave(){document.getElementById('uploadZone').classList.remove('drag');}
  function dropFile(e){
    e.preventDefault();dragLeave();
    const f=e.dataTransfer.files[0];if(f)processFile(f);
  }
  function previewFile(inp){if(inp.files[0])processFile(inp.files[0]);}

  let _file=null;
  function processFile(file){
    const types=['image/jpeg','image/png','image/webp'];
    if(!types.includes(file.type)){toast('Use JPG, PNG ou WEBP.','err');return;}
    if(file.size>2*1024*1024){toast('Arquivo muito grande. Máx 2MB.','err');return;}
    _file=file;
    const rd=new FileReader();
    rd.onload=ev=>{
      document.getElementById('previewImg').src=ev.target.result;
      document.getElementById('previewName').textContent=file.name;
      document.getElementById('uploadDefault').style.display='none';
      document.getElementById('uploadPreview').style.display='block';
      document.getElementById('btnUpload').style.display='block';
    };
    rd.readAsDataURL(file);
  }

  async function uploadPhoto(){
    if(!_file){toast('Selecione uma foto primeiro.','err');return;}
    const btn=document.getElementById('btnUpload');
    btn.textContent='Enviando…';btn.disabled=true;
    const fd=new FormData();
    fd.append('action','upload_photo');
    fd.append('photo',_file);
    try{
      const r=await fetch('/florescer/api/profile.php',{method:'POST',body:fd});
      const data=await r.json();
      if(data.success){
        toast('Foto atualizada! 📷');
        setAvatarUI('upload',null,data.url);
        _file=null;btn.style.display='none';
        // Atualiza box de foto atual
        let box=document.getElementById('currentPhotoBox');
        if(box){box.querySelector('img').src=data.url;}
        else{
          box=document.createElement('div');box.id='currentPhotoBox';box.className='current-photo-box';
          box.innerHTML=`<img src="${data.url}" class="current-photo-img"/><div class="current-photo-info"><div class="current-photo-lbl">Foto atual</div><div class="current-photo-sub">Enviada por você</div></div><button class="btn-remove" onclick="removePhoto()">Remover</button>`;
          btn.parentNode.appendChild(box);
        }
        // Reset preview
        document.getElementById('uploadDefault').style.display='block';
        document.getElementById('uploadPreview').style.display='none';
      } else toast(data.message||'Erro ao enviar.','err');
    }catch(e){toast('Erro de conexão: '+e.message,'err');}
    finally{btn.textContent='💾 Salvar foto';btn.disabled=false;}
  }

  async function removePhoto(){
    const r=await api({action:'remove_photo'});
    if(r.success){
      toast('Foto removida.');
      setAvatarUI('initial',null,null);
      document.getElementById('currentPhotoBox')?.remove();
    } else toast(r.message||'Erro.','err');
  }

  /* ── Emoji ── */
  async function selectAvatar(emoji,btn){
    document.querySelectorAll('.av-btn').forEach(b=>b.classList.remove('sel'));
    btn.classList.add('sel');
    const r=await api({action:'set_avatar_emoji',emoji});
    if(r.success){toast('Avatar atualizado! 🎭');setAvatarUI('emoji',emoji,null);}
    else toast(r.message||'Erro.','err');
  }

  function setAvatarUI(type,emoji,url){
    const el=document.getElementById('wcAvatar');
    if(type==='upload'&&url){
      el.innerHTML=`<img src="${url}" alt="Avatar"/>`;
      P.avatarType='upload';P.avatarUrl=url;P.avatarEmoji='';
    } else if(type==='emoji'&&emoji){
      el.innerHTML=`<span class="wc-avatar-emoji">${emoji}</span>`;
      P.avatarType='emoji';P.avatarEmoji=emoji;P.avatarUrl='';
    } else {
      el.innerHTML=`<span>${P.initial}</span>`;
      P.avatarType='initial';P.avatarEmoji='';P.avatarUrl='';
    }
  }

  /* ── Excluir conta ── */
  function openDelModal(){
    document.getElementById('delPass').value='';
    document.getElementById('delErr').textContent='';
    document.getElementById('delModal').classList.add('open');
    document.body.style.overflow='hidden';
    setTimeout(()=>document.getElementById('delPass').focus(),200);
  }
  function closeDelModal(){
    document.getElementById('delModal').classList.remove('open');
    document.body.style.overflow='';
  }
  async function deleteAccount(){
    const pass=document.getElementById('delPass').value;
    const err=document.getElementById('delErr');
    if(!pass){err.textContent='Informe sua senha.';return;}
    const btn=document.getElementById('btnConfirmDel');
    btn.disabled=true;btn.textContent='Excluindo…';
    const r=await api({action:'delete_account',password:pass});
    if(r.success){
      toast('Conta excluída. Até mais! 👋');
      setTimeout(()=>window.location.href='/florescer/public/',1500);
    } else {
      err.textContent=r.message||'Senha incorreta.';
      btn.disabled=false;btn.textContent='Excluir permanentemente';
    }
  }
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDelModal();});

  /* ══ STORY PNG ══════════════════════════════════════════════ */
  function drawRR(ctx,x,y,w,h,r){
    ctx.beginPath();ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.quadraticCurveTo(x+w,y,x+w,y+r);
    ctx.lineTo(x+w,y+h-r);ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);
    ctx.lineTo(x+r,y+h);ctx.quadraticCurveTo(x,y+h,x,y+h-r);
    ctx.lineTo(x,y+r);ctx.quadraticCurveTo(x,y,x+r,y);ctx.closePath();
  }
  function generateStory(){
    const canvas=document.getElementById('shareCanvas');
    const ctx=canvas.getContext('2d');
    const W=1080,H=1920;

    ctx.fillStyle='#0a1a0f';ctx.fillRect(0,0,W,H);
    const bgG=ctx.createLinearGradient(0,0,0,H);
    bgG.addColorStop(0,'#132a1e');bgG.addColorStop(.55,'#0d1f16');bgG.addColorStop(1,'#080f0a');
    ctx.fillStyle=bgG;ctx.fillRect(0,0,W,H);

    const radG=ctx.createRadialGradient(W/2,0,0,W/2,0,W*.9);
    radG.addColorStop(0,'rgba(64,145,108,.08)');radG.addColorStop(1,'rgba(0,0,0,0)');
    ctx.fillStyle=radG;ctx.fillRect(0,0,W,H);

    const topG=ctx.createLinearGradient(0,0,W,0);
    topG.addColorStop(0,'#40916c');topG.addColorStop(.5,'#74c69d');topG.addColorStop(1,'#c9a84c');
    ctx.fillStyle=topG;ctx.fillRect(0,0,W,5);

    ctx.fillStyle='rgba(255,255,255,.9)';
    ctx.font='bold 32px Georgia,serif';
    ctx.textAlign='left';ctx.textBaseline='top';
    ctx.fillText('🌱 florescer',72,68);
    ctx.fillStyle='rgba(116,198,157,.35)';
    ctx.font='22px Arial,sans-serif';ctx.textAlign='right';
    ctx.fillText(new Date().toLocaleDateString('pt-BR',{day:'2-digit',month:'long',year:'numeric'}),W-72,74);

    const AX=W/2, AY=370, AR=130;

    function drawRR(ctx,x,y,w,h,r){
      ctx.beginPath();ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);
      ctx.quadraticCurveTo(x+w,y,x+w,y+r);ctx.lineTo(x+w,y+h-r);
      ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);ctx.lineTo(x+r,y+h);
      ctx.quadraticCurveTo(x,y+h,x,y+h-r);ctx.lineTo(x,y+r);
      ctx.quadraticCurveTo(x,y,x+r,y);ctx.closePath();
    }

    function finish(){
      // Anel avatar
      const ringG=ctx.createLinearGradient(AX-AR,AY-AR,AX+AR,AY+AR);
      ringG.addColorStop(0,'rgba(116,198,157,.6)');ringG.addColorStop(1,'rgba(201,168,76,.5)');
      ctx.beginPath();ctx.arc(AX,AY,AR+6,0,Math.PI*2);
      ctx.strokeStyle=ringG;ctx.lineWidth=5;ctx.stroke();

      // ── Nome ──
      const nameY = AY + AR + 48;
      ctx.textAlign = 'center'; ctx.textBaseline = 'top';
      ctx.fillStyle = 'rgba(255,255,255,.96)';
      ctx.font = '900 72px Georgia,serif';
      ctx.fillText(P.name, W / 2, nameY);

      // Badge nível
      const badgeY = nameY + 88;
      drawRR(ctx, W / 2 - 150, badgeY, 300, 48, 24);
      const badgeG = ctx.createLinearGradient(W / 2 - 150, badgeY, W / 2 + 150, badgeY + 48);
      badgeG.addColorStop(0, 'rgba(64,145,108,.3)'); badgeG.addColorStop(1, 'rgba(40,80,60,.3)');
      ctx.fillStyle = badgeG; ctx.fill();
      ctx.strokeStyle = 'rgba(116,198,157,.35)'; ctx.lineWidth = 1.5; ctx.stroke();
      ctx.fillStyle = 'rgba(116,198,157,.95)'; ctx.font = 'bold 24px Arial,sans-serif';
      ctx.textBaseline = 'top';
      ctx.fillText(`Nível ${P.level}  ·  ${P.lvName}`, W / 2, badgeY + 13);

      // ── Escola | Cidade em pills lado a lado ──
      const metaY = badgeY + 68;
      const metaParts = [];
      if (P.school) metaParts.push({ ico: '🏫', txt: P.school });
      if (P.city)   metaParts.push({ ico: '📍', txt: P.city });
      if (metaParts.length) {
        ctx.font = '26px Arial,sans-serif';
        const pillH = 52, pillR = 26, pillPadX = 28, gap = 20;
        const widths = metaParts.map(p => ctx.measureText(p.ico + '  ' + p.txt).width + pillPadX * 2);
        const totalW = widths.reduce((a, b) => a + b, 0) + gap * (metaParts.length - 1);
        let px = W / 2 - totalW / 2;
        metaParts.forEach((p, i) => {
          const pw = widths[i];
          drawRR(ctx, px, metaY, pw, pillH, pillR);
          ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fill();
          ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1.2; ctx.stroke();
          ctx.fillStyle = 'rgba(255,255,255,.55)';
          ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
          ctx.fillText(p.ico + '  ' + p.txt, px + pw / 2, metaY + pillH / 2);
          px += pw + gap;
        });
      }

      // ── Bio ──
      const bioY = metaY + (metaParts.length ? 70 : 0);
      if (P.bio) {
        ctx.fillStyle = 'rgba(255,255,255,.3)';
        ctx.font = 'italic 27px Georgia,serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        const bioText = P.bio.length > 72 ? P.bio.substring(0, 69) + '…' : P.bio;
        ctx.fillText('"' + bioText + '"', W / 2, bioY);
      }

      // ── Divisor ──
      const divY = bioY + (P.bio ? 58 : 0) + 18;
      const divG = ctx.createLinearGradient(100, divY, W - 100, divY);
      divG.addColorStop(0, 'rgba(255,255,255,0)'); divG.addColorStop(.5, 'rgba(255,255,255,.12)'); divG.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.strokeStyle = divG; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.moveTo(100, divY); ctx.lineTo(W - 100, divY); ctx.stroke();

      // ── Stats grid 3×3 (9 cards) ──
      const stats = [
        [P.plant,  P.streak + ' dias',              'Sequência atual'],
        ['⭐',     P.xp.toLocaleString('pt-BR'),    'XP acumulado'],
        ['📖',     String(P.lessons),               'Aulas concluídas'],
        ['⏱',     P.hours,                          'Tempo estudado'],
        ['💼',     P.worksOk + '/' + P.works,       'Trabalhos entregues'],
        ['🎯',     P.goalMin + ' min',              'Meta diária'],
        ['📊',     (P.simAvg != null ? P.simAvg + '%' : '—'),  'Média simulados'],
        ['✅',     (P.simDone != null ? String(P.simDone) : '—'), 'Simulados feitos'],
        ['🏅',     'Nível ' + P.level,              P.lvName],
      ];
      const panY = divY + 42;
      const cols = 3, panW = 290, panH = 188, gapX = Math.floor((W - 128 - cols * panW) / (cols - 1)), gapY = 18;
      stats.forEach(([ico, val, lbl], i) => {
        const col = i % cols, row = Math.floor(i / cols);
        const px = 64 + col * (panW + gapX), py = panY + row * (panH + gapY);
        drawRR(ctx, px, py, panW, panH, 18);
        ctx.fillStyle = 'rgba(255,255,255,.04)'; ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,.07)'; ctx.lineWidth = 1.5; ctx.stroke();
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.font = '32px serif'; ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.fillText(ico, px + panW / 2, py + 20);
        ctx.font = 'bold 46px Georgia,serif'; ctx.fillStyle = 'rgba(255,255,255,.95)'; ctx.fillText(val, px + panW / 2, py + 62);
        ctx.font = '21px Arial,sans-serif'; ctx.fillStyle = 'rgba(255,255,255,.3)'; ctx.fillText(lbl, px + panW / 2, py + 120);
      });

      // ── Card da planta ──
      const plantY = panY + 3 * (panH + gapY) + 28;
      drawRR(ctx, 64, plantY, W - 128, 118, 18);
      const plantBg = ctx.createLinearGradient(64, plantY, W - 64, plantY + 118);
      plantBg.addColorStop(0, 'rgba(64,145,108,.1)'); plantBg.addColorStop(1, 'rgba(30,77,53,.08)');
      ctx.fillStyle = plantBg; ctx.fill();
      ctx.strokeStyle = 'rgba(116,198,157,.15)'; ctx.lineWidth = 1.5; ctx.stroke();
      ctx.font = '52px serif'; ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
      ctx.fillText(P.plant, 108, plantY + 59);
      ctx.font = 'bold 36px Georgia,serif'; ctx.fillStyle = 'rgba(116,198,157,.9)';
      ctx.textBaseline = 'top'; ctx.fillText(P.plantName, 180, plantY + 22);
      ctx.font = '24px Arial,sans-serif'; ctx.fillStyle = 'rgba(255,255,255,.3)';
      ctx.fillText(P.streak + ' dias consecutivos', 180, plantY + 68);

      // ── Rodapé ──
      const footDivY=H-110;
      const fdG=ctx.createLinearGradient(0,footDivY,W,footDivY);
      fdG.addColorStop(0,'rgba(255,255,255,0)');fdG.addColorStop(.5,'rgba(255,255,255,.07)');fdG.addColorStop(1,'rgba(255,255,255,0)');
      ctx.strokeStyle=fdG;ctx.lineWidth=1;
      ctx.beginPath();ctx.moveTo(72,footDivY);ctx.lineTo(W-72,footDivY);ctx.stroke();
      ctx.fillStyle='rgba(255,255,255,.18)';ctx.font='22px Arial,sans-serif';
      ctx.textAlign='center';ctx.textBaseline='middle';
      ctx.fillText('florescer.app  ·  #FloresçaComEstudo',W/2,footDivY+42);

      const a=document.createElement('a');
      a.href=canvas.toDataURL('image/png',1.0);
      a.download=`florescer-${P.name.replace(/\s+/g,'-').toLowerCase()}.png`;
      a.click();
      toast('Story gerado! 🌱');
    }

    // Fundo do avatar
    ctx.save();ctx.beginPath();ctx.arc(AX,AY,AR,0,Math.PI*2);ctx.clip();
    const avBg=ctx.createLinearGradient(AX-AR,AY-AR,AX+AR,AY+AR);
    avBg.addColorStop(0,'#40916c');avBg.addColorStop(1,'#1e4d35');
    ctx.fillStyle=avBg;ctx.fillRect(AX-AR,AY-AR,AR*2,AR*2);
    ctx.restore();

    if(P.avatarType==='upload'&&P.avatarUrl){
      const img=new Image();img.crossOrigin='anonymous';
      img.onload=()=>{
        ctx.save();ctx.beginPath();ctx.arc(AX,AY,AR,0,Math.PI*2);ctx.clip();
        ctx.drawImage(img,AX-AR,AY-AR,AR*2,AR*2);ctx.restore();finish();
      };
      img.onerror=()=>{
        ctx.fillStyle='#fff';ctx.font=`bold ${AR}px Georgia,serif`;
        ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(P.initial,AX,AY+6);finish();
      };
      img.src=P.avatarUrl;
    } else {
      const content=P.avatarType==='emoji'&&P.avatarEmoji?P.avatarEmoji:P.initial;
      ctx.fillStyle='#fff';
      ctx.font=`bold ${P.avatarType==='emoji'?AR:Math.floor(AR*.85)}px Georgia,serif`;
      ctx.textAlign='center';ctx.textBaseline='middle';
      ctx.fillText(content,AX,AY+(P.avatarType==='emoji'?8:5));
      finish();
    }
  }
  </script>
  </body>
  </html>