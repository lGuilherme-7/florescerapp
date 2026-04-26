<?php
// ============================================================
// /professor/teachers/views/perfil.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireTeacher();
$teacher     = currentTeacher();
$currentPage = 'perfil';

$pendingRed = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_redacoes WHERE teacher_id = ? AND status = "pendente"',
    [(int)$teacher['id']]
)['n'] ?? 0);
$unreadMsgs = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM teacher_messages
     WHERE teacher_id = ? AND sender='student' AND read_at IS NULL",
    [(int)$teacher['id']]
)['n'] ?? 0);

$teacherId = (int)$teacher['id'];

// Matérias
$subjects = dbQuery(
    'SELECT id, name FROM teacher_subjects WHERE teacher_id = ? ORDER BY name',
    [$teacherId]
);

// Pacotes
$packages = dbQuery(
    'SELECT id, name, quantity, price, is_active FROM teacher_packages
     WHERE teacher_id = ? ORDER BY price',
    [$teacherId]
);

// Avaliações recentes
$ratings = dbQuery(
    'SELECT r.stars, r.comment, r.created_at
     FROM teacher_ratings r
     WHERE r.teacher_id = ?
     ORDER BY r.created_at DESC LIMIT 5',
    [$teacherId]
);

// Estatísticas
$totalRedacoes = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_redacoes WHERE teacher_id = ? AND status = "corrigida"',
    [$teacherId]
)['n'] ?? 0);
$totalAulas = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_orders WHERE teacher_id = ? AND type = "aula" AND status = "pago"',
    [$teacherId]
)['n'] ?? 0);
$totalAlunos = (int)(dbRow(
    'SELECT COUNT(DISTINCT student_id) AS n FROM teacher_orders WHERE teacher_id = ? AND status = "pago"',
    [$teacherId]
)['n'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Meu Perfil — Professor</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,900;1,9..144,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#0d2618;--g800:#14382a;--g700:#1a4a37;--g600:#225c44;
  --g500:#2d7a58;--g400:#3d9970;--g300:#55b88a;--g200:#8dd4b0;
  --g100:#c2ead6;--g50:#eaf6f0;--g25:#f4fbf7;
  --white:#fff;--n800:#111c16;--n400:#5a7a68;
  --n200:#b8d0c4;--n100:#daeae1;--n50:#f2f8f5;
  --gold:#c9a84c;--gold-l:#fdf3d8;--red:#d94040;--red-l:#fdeaea;
  --sw:248px;--hh:60px;
  --fd:'Fraunces',Georgia,serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:14px;--rs:9px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 4px 12px rgba(0,0,0,.07);--sh3:0 20px 48px rgba(0,0,0,.12);
}
html{height:100%}
body{font-family:var(--fb);background:var(--g25);color:var(--n800);display:flex;min-height:100%;-webkit-font-smoothing:antialiased;overflow-x:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:2px}
.main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{height:var(--hh);position:sticky;top:0;z-index:40;background:rgba(244,251,247,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;flex-shrink:0}
.tb-title{font-family:var(--fd);font-size:1.05rem;font-weight:600;color:var(--n800)}
.tb-right{display:flex;align-items:center;gap:.7rem}
.btn-save{padding:.46rem 1.1rem;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;border:none;border-radius:50px;font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(45,122,88,.22)}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(45,122,88,.32)}
.page{padding:1.5rem 1.8rem;flex:1;display:flex;flex-direction:column;gap:1.1rem}

/* Grid */
.perfil-grid{display:grid;grid-template-columns:340px 1fr;gap:1.1rem;align-items:start}

/* Card base */
.card{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden}
.card-head{padding:.85rem 1.2rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:var(--fd);font-size:.92rem;font-weight:700;color:var(--n800)}
.card-sub{font-size:.68rem;color:var(--n400)}
.card-body{padding:1.2rem}

/* Avatar */
.avatar-wrap{position:relative;width:110px;height:110px;margin:0 auto 1rem}
.avatar-img{width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--g200);box-shadow:0 4px 16px rgba(45,122,88,.18)}
.avatar-ini{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:2.4rem;font-weight:700;color:#fff;border:3px solid var(--g200);box-shadow:0 4px 16px rgba(45,122,88,.18)}
.avatar-btn{position:absolute;bottom:4px;right:4px;width:28px;height:28px;border-radius:50%;background:var(--g600);border:2px solid var(--white);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.75rem;color:#fff;transition:all var(--d) var(--e)}
.avatar-btn:hover{background:var(--g500);transform:scale(1.1)}

/* Stats rápidos */
.quick-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:1rem}
.qs{text-align:center;padding:.6rem .4rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs)}
.qs-val{font-family:var(--fd);font-size:1.3rem;font-weight:900;color:var(--g600);line-height:1}
.qs-lbl{font-size:.6rem;color:var(--n400);text-transform:uppercase;letter-spacing:.05em;margin-top:.15rem}

/* Rating visual */
.rating-wrap{text-align:center;margin-bottom:1rem;padding:.75rem;background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs)}
.rating-val{font-family:var(--fd);font-size:2rem;font-weight:900;color:var(--g600);line-height:1}
.rating-stars{color:var(--gold);font-size:1rem;letter-spacing:.1em;margin:.2rem 0}
.rating-count{font-size:.7rem;color:var(--n400)}

/* Verificação */
.verify-list{display:flex;flex-direction:column;gap:.5rem}
.verify-item{display:flex;align-items:center;gap:.65rem;padding:.6rem .8rem;border-radius:var(--rs);border:1px solid var(--n100);background:var(--white)}
.verify-item.ok{border-color:rgba(45,122,88,.2);background:var(--g25)}
.verify-item.pending{border-color:rgba(201,168,76,.2);background:var(--gold-l)}
.verify-ico{font-size:.9rem;flex-shrink:0}
.verify-info{flex:1}
.verify-name{font-size:.78rem;font-weight:500;color:var(--n800)}
.verify-sub{font-size:.65rem;color:var(--n400);margin-top:.04rem}
.verify-badge{font-size:.62rem;font-weight:600;padding:.1rem .4rem;border-radius:20px;flex-shrink:0}
.verify-badge.ok{background:var(--g50);color:var(--g600);border:1px solid var(--g100)}
.verify-badge.pending{background:var(--gold-l);color:#92720c;border:1px solid rgba(201,168,76,.3)}
.verify-upload{font-size:.7rem;color:var(--g500);cursor:pointer;text-decoration:underline;flex-shrink:0}

/* Form */
.fg{margin-bottom:.85rem}
.fl{display:block;font-size:.75rem;font-weight:500;color:var(--n400);margin-bottom:.28rem;letter-spacing:.01em}
.fc{width:100%;padding:.6rem .85rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.85rem;outline:none;transition:all var(--d) var(--e)}
.fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.08)}
.fc::placeholder{color:#bbb}
textarea.fc{resize:vertical;min-height:100px;line-height:1.7}
.fc-hint{font-size:.68rem;color:var(--n400);margin-top:.2rem}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}

/* Matérias */
.subjects-wrap{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.7rem}
.subj-tag{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .65rem;background:var(--g50);border:1px solid var(--g100);border-radius:20px;font-size:.76rem;color:var(--g700);font-weight:500}
.subj-tag button{background:none;border:none;cursor:pointer;color:var(--n400);font-size:.72rem;line-height:1;transition:color var(--d) var(--e);padding:0}
.subj-tag button:hover{color:var(--red)}
.subj-add{display:flex;gap:.5rem;margin-top:.4rem}
.subj-input{flex:1;padding:.48rem .75rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.82rem;outline:none;transition:all var(--d) var(--e)}
.subj-input:focus{border-color:var(--g400);background:var(--white)}
.subj-input::placeholder{color:#bbb}
.btn-add-sm{padding:.46rem .85rem;background:var(--g600);color:#fff;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.76rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);white-space:nowrap}
.btn-add-sm:hover{background:var(--g500)}

/* Pacotes */
.package-list{display:flex;flex-direction:column;gap:.5rem;margin-bottom:.7rem}
.package-item{display:flex;align-items:center;gap:.7rem;padding:.6rem .85rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs)}
.pkg-info{flex:1}
.pkg-name{font-size:.8rem;font-weight:600;color:var(--n800)}
.pkg-qty{font-size:.68rem;color:var(--n400);margin-top:.04rem}
.pkg-price{font-family:var(--fd);font-size:.92rem;font-weight:700;color:var(--g600);flex-shrink:0}
.pkg-del{width:24px;height:24px;border-radius:50%;background:none;border:1px solid rgba(217,64,64,.15);color:rgba(217,64,64,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.7rem;transition:all var(--d) var(--e);flex-shrink:0}
.pkg-del:hover{background:var(--red-l);color:var(--red);border-color:rgba(217,64,64,.3)}
.pkg-add-row{display:grid;grid-template-columns:1fr auto auto auto;gap:.4rem;align-items:center}
.pkg-add-row .fc{padding:.46rem .65rem;font-size:.8rem}

/* Avaliações */
.rating-list{display:flex;flex-direction:column;gap:.6rem}
.rating-item{padding:.75rem .9rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs)}
.ri-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.35rem}
.ri-name{font-size:.8rem;font-weight:600;color:var(--n800)}
.ri-stars{color:var(--gold);font-size:.8rem;letter-spacing:.08em}
.ri-date{font-size:.62rem;color:var(--n400)}
.ri-comment{font-size:.78rem;color:var(--n400);line-height:1.6;font-style:italic}

/* Diploma upload */
.diploma-zone{border:2px dashed var(--n100);border-radius:var(--rs);padding:1.2rem;text-align:center;cursor:pointer;transition:all var(--d) var(--e);position:relative}
.diploma-zone:hover{border-color:var(--g300);background:var(--g25)}
.diploma-zone.has-file{border-color:var(--g300);background:var(--g25);border-style:solid}
.diploma-ico{font-size:1.8rem;opacity:.35;display:block;margin-bottom:.5rem}
.diploma-lbl{font-size:.8rem;color:var(--n400);line-height:1.6}
.diploma-lbl strong{color:var(--g600)}
.diploma-input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.diploma-preview{display:flex;flex-direction:column;gap:.6rem}
.diploma-file-item{display:flex;align-items:center;gap:.6rem;padding:.5rem .7rem;background:var(--white);border:1px solid var(--g100);border-radius:var(--rs)}
.diploma-file-ico{font-size:.9rem;flex-shrink:0}
.diploma-file-name{font-size:.76rem;color:var(--n800);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.diploma-file-del{width:20px;height:20px;border-radius:50%;background:none;border:1px solid rgba(217,64,64,.2);color:rgba(217,64,64,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.65rem;transition:all var(--d) var(--e);flex-shrink:0}
.diploma-file-del:hover{background:var(--red-l);color:var(--red)}

/* Seção senha */
.pass-toggle{font-size:.75rem;color:var(--g500);cursor:pointer;text-decoration:underline;display:inline-block;margin-bottom:.8rem}
.pass-section{display:none}
.pass-section.open{display:block}

/* Toast */
#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:280px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.info{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:1100px){.perfil-grid{grid-template-columns:300px 1fr}}
@media(max-width:900px){.perfil-grid{grid-template-columns:1fr}}
@media(max-width:768px){.main{margin-left:0}.page{padding:1rem}.frow{grid-template-columns:1fr}.pkg-add-row{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <span class="tb-title">👤 Meu Perfil</span>
    <div class="tb-right">
      <span style="font-size:.72rem;color:var(--n400)"><?= date('d/m/Y') ?></span>
      <button class="btn-save" onclick="saveAll()">💾 Salvar tudo</button>
    </div>
  </header>

  <main class="page">
    <div class="perfil-grid">

      <!-- Coluna esquerda -->
      <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Card: identidade -->
        <div class="card">
          <div class="card-head">
            <span class="card-title">👤 Identidade</span>
            <?php if($teacher['rank_position']>0): ?>
              <span style="font-size:.68rem;color:var(--gold)">🏆 #<?= $teacher['rank_position'] ?> ranking</span>
            <?php endif; ?>
          </div>
          <div class="card-body" style="text-align:center">
            <!-- Avatar -->
            <div class="avatar-wrap">
              <?php if(!empty($teacher['avatar_url'])): ?>
                <img class="avatar-img" id="avatarImg"
                     src="<?= htmlspecialchars($teacher['avatar_url'],ENT_QUOTES) ?>"
                     alt="Foto"/>
              <?php else: ?>
                <div class="avatar-ini" id="avatarIni">
                  <?= strtoupper(mb_substr($teacher['name']??'P',0,1,'UTF-8')) ?>
                </div>
              <?php endif; ?>
              <label class="avatar-btn" title="Alterar foto">
                📷
                <input type="file" accept="image/*" style="display:none" onchange="previewAvatar(this)"/>
              </label>
            </div>

            <!-- Rating -->
            <?php if((float)($teacher['rating_avg']??0)>0): ?>
            <div class="rating-wrap">
              <div class="rating-val"><?= number_format((float)$teacher['rating_avg'],1) ?></div>
              <div class="rating-stars">
                <?php
                $stars = round((float)$teacher['rating_avg']);
                echo str_repeat('★',$stars).str_repeat('☆',5-$stars);
                ?>
              </div>
              <div class="rating-count"><?= (int)$teacher['rating_count'] ?> avaliação<?= $teacher['rating_count']!=1?'ões':'' ?></div>
            </div>
            <?php endif; ?>

            <!-- Stats rápidos -->
            <div class="quick-stats">
              <div class="qs">
                <div class="qs-val"><?= $totalAlunos ?></div>
                <div class="qs-lbl">Alunos</div>
              </div>
              <div class="qs">
                <div class="qs-val"><?= $totalRedacoes ?></div>
                <div class="qs-lbl">Redações</div>
              </div>
              <div class="qs">
                <div class="qs-val"><?= $totalAulas ?></div>
                <div class="qs-lbl">Aulas</div>
              </div>
            </div>

            <div style="font-size:.72rem;color:var(--n400);text-align:left;padding:.6rem .7rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs)">
              ✉️ <?= htmlspecialchars($teacher['email']??'',ENT_QUOTES) ?>
            </div>
          </div>
        </div>

        <!-- Card: verificação -->
        <div class="card">
          <div class="card-head">
            <span class="card-title">✅ Verificações</span>
            <span class="card-sub">passa confiança ao aluno</span>
          </div>
          <div class="card-body">
            <div class="verify-list" id="verifyList">
              <!-- Gerado pelo JS -->
            </div>
          </div>
        </div>

        <!-- Card: avaliações recentes -->
        <?php if(!empty($ratings)): ?>
        <div class="card">
          <div class="card-head">
            <span class="card-title">⭐ Avaliações recentes</span>
            <span class="card-sub"><?= count($ratings) ?> de <?= (int)$teacher['rating_count'] ?></span>
          </div>
          <div class="card-body">
            <div class="rating-list">
              <?php foreach($ratings as $r): ?>
              <div class="rating-item">
                <div class="ri-top">
                  <span class="ri-name"><?= 'Aluno' ?></span>
                  <span class="ri-stars"><?= str_repeat('★',(int)$r['stars']).str_repeat('☆',5-(int)$r['stars']) ?></span>
                </div>
                <?php if(!empty($r['comment'])): ?>
                  <div class="ri-comment">"<?= htmlspecialchars($r['comment'],ENT_QUOTES) ?>"</div>
                <?php endif; ?>
                <div class="ri-date" style="margin-top:.3rem">
                  <?= date('d/m/Y',strtotime($r['created_at'])) ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <!-- Coluna direita -->
      <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Card: dados pessoais -->
        <div class="card">
          <div class="card-head">
            <span class="card-title">📋 Dados pessoais</span>
          </div>
          <div class="card-body">
            <div class="fg">
              <label class="fl">Nome completo</label>
              <input class="fc" type="text" id="fName"
                     value="<?= htmlspecialchars($teacher['name']??'',ENT_QUOTES) ?>"
                     maxlength="100" placeholder="Seu nome"/>
            </div>
            <div class="fg">
              <label class="fl">Biografia profissional</label>
              <textarea class="fc" id="fBio" maxlength="1000"
                        placeholder="Conte sua experiência, formação e especialidades. Uma boa bio aumenta suas contratações."><?= htmlspecialchars($teacher['bio']??'',ENT_QUOTES) ?></textarea>
              <div class="fc-hint">Máximo 1000 caracteres · <span id="bioCount">0</span> usados</div>
            </div>
            <div class="fg">
              <label class="fl">Chave PIX (para receber pagamentos)</label>
              <input class="fc" type="text" id="fPix"
                     value="<?= htmlspecialchars($teacher['pix_key']??'',ENT_QUOTES) ?>"
                     placeholder="CPF, e-mail, telefone ou chave aleatória"/>
              <div class="fc-hint">Usada para saques. Mantenha sempre atualizada.</div>
            </div>

            <!-- Trocar senha -->
            <span class="pass-toggle" onclick="togglePass()">🔒 Alterar senha</span>
            <div class="pass-section" id="passSection">
              <div class="frow">
                <div class="fg">
                  <label class="fl">Nova senha</label>
                  <input class="fc" type="password" id="fPassNew" placeholder="Mínimo 6 caracteres"/>
                </div>
                <div class="fg">
                  <label class="fl">Confirmar nova senha</label>
                  <input class="fc" type="password" id="fPassConf" placeholder="Repita a senha"/>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card: matérias -->
        <div class="card">
          <div class="card-head">
            <span class="card-title">📚 Matérias que leciona</span>
            <span class="card-sub">visível para os alunos</span>
          </div>
          <div class="card-body">
            <div class="subjects-wrap" id="subjectsWrap">
              <?php foreach($subjects as $s): ?>
                <span class="subj-tag" data-id="<?= $s['id'] ?>">
                  <?= htmlspecialchars($s['name'],ENT_QUOTES) ?>
                  <button onclick="removeSubject(this)" title="Remover">✕</button>
                </span>
              <?php endforeach; ?>
            </div>
            <div class="subj-add">
              <input class="subj-input" type="text" id="subjInput"
                     placeholder="Ex: Matemática, Redação ENEM, Inglês…"
                     maxlength="80"
                     onkeydown="if(event.key==='Enter'){addSubject();event.preventDefault()}"/>
              <button class="btn-add-sm" onclick="addSubject()">+ Adicionar</button>
            </div>
          </div>
        </div>

        <!-- Card: pacotes de correção -->
        <div class="card">
          <div class="card-head">
            <span class="card-title">📦 Pacotes de correção</span>
            <span class="card-sub">você define o preço</span>
          </div>
          <div class="card-body">
            <div class="package-list" id="packageList">
              <?php foreach($packages as $p): ?>
              <div class="package-item" data-id="<?= $p['id'] ?>">
                <div class="pkg-info">
                  <div class="pkg-name"><?= htmlspecialchars($p['name'],ENT_QUOTES) ?></div>
                  <div class="pkg-qty"><?= (int)$p['quantity'] ?> correção<?= $p['quantity']!=1?'ões':'' ?></div>
                </div>
                <div class="pkg-price">R$ <?= number_format((float)$p['price'],2,',','.') ?></div>
                <button class="pkg-del" onclick="removePackage(this)" title="Remover">✕</button>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="pkg-add-row">
              <input class="fc" type="text" id="pkgName" placeholder="Nome do pacote" maxlength="100"/>
              <input class="fc" type="number" id="pkgQty" placeholder="Qtd" min="1" max="20" value="1" style="max-width:70px"/>
              <input class="fc" type="number" id="pkgPrice" placeholder="R$" min="10" step="5" style="max-width:90px"/>
              <button class="btn-add-sm" onclick="addPackage()">+ Pacote</button>
            </div>
            <div class="fc-hint" style="margin-top:.4rem">
              A plataforma retém <?= number_format((float)$teacher['commission_pct'],0) ?>% de comissão sobre cada venda.
            </div>
          </div>
        </div>

        <!-- Card: diploma e certificados -->
        <div class="card">
          <div class="card-head">
            <span class="card-title">🎓 Diploma e certificados</span>
            <span class="card-sub">aumenta sua credibilidade</span>
          </div>
          <div class="card-body">
            <div style="font-size:.78rem;color:var(--n400);line-height:1.7;margin-bottom:.9rem;padding:.65rem .8rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs)">
              📌 Envie fotos ou PDFs do seu diploma, certificados ou carteira de professor.
              Após validação pela equipe Florescer, um selo <strong style="color:var(--g600)">✓ Verificado</strong> aparece no seu perfil público.
            </div>

            <div id="diplomaPreview" class="diploma-preview" style="margin-bottom:.7rem">
              <!-- arquivos enviados -->
            </div>

            <div class="diploma-zone" id="diplomaZone">
              <span class="diploma-ico">📄</span>
              <div class="diploma-lbl">
                <strong>Clique para enviar</strong> ou arraste arquivos aqui<br>
                JPG, PNG ou PDF · Máx. 5 MB por arquivo
              </div>
              <input type="file" class="diploma-input" id="diplomaInput"
                     accept="image/*,.pdf" multiple
                     onchange="handleDiplomaFiles(this.files)"/>
            </div>

            <div style="margin-top:.85rem;display:flex;flex-direction:column;gap:.4rem">
              <div class="fg">
                <label class="fl">Link do currículo (LinkedIn, Lattes etc.)</label>
                <input class="fc" type="url" id="fCurriculo"
                       placeholder="https://linkedin.com/in/seu-perfil"/>
              </div>
              <div class="fg">
                <label class="fl">Formação acadêmica</label>
                <input class="fc" type="text" id="fFormacao"
                       placeholder="Ex: Licenciatura em Matemática — UNICAMP 2018"/>
              </div>
              <div class="fg">
                <label class="fl">Tempo de experiência</label>
                <select class="fc" id="fExperiencia">
                  <option value="">Selecionar…</option>
                  <option value="menos1">Menos de 1 ano</option>
                  <option value="1a3">1 a 3 anos</option>
                  <option value="3a5">3 a 5 anos</option>
                  <option value="5a10">5 a 10 anos</option>
                  <option value="mais10">Mais de 10 anos</option>
                </select>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<div id="toasts"></div>

<script>
const API_PROFILE = '<?= TEACHER_API ?>/profile.php';
const API_AUTH    = '<?= TEACHER_API ?>/auth.php';

// Dados iniciais PHP → JS
let subjects = <?= json_encode(array_column($subjects,'name'), JSON_UNESCAPED_UNICODE) ?>;
let packages = <?= json_encode(array_map(fn($p)=>[
    'name'     => $p['name'],
    'quantity' => (int)$p['quantity'],
    'price'    => (float)$p['price'],
], $packages), JSON_UNESCAPED_UNICODE) ?>;

let diplomaFiles = [];

// ── Toast ─────────────────────────────────────────────────────
function toast(msg,type='ok',ms=3200){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},ms);
}

async function api(endpoint, body){
  const r=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

// ── Bio contador ──────────────────────────────────────────────
const bioEl=document.getElementById('fBio');
const bioCount=document.getElementById('bioCount');
function updateBio(){bioCount.textContent=bioEl.value.length;}
bioEl.addEventListener('input',updateBio);
updateBio();

// ── Avatar preview ────────────────────────────────────────────
function previewAvatar(input){
  if(!input.files[0]) return;
  const url=URL.createObjectURL(input.files[0]);
  const wrap=document.querySelector('.avatar-wrap');
  let img=document.getElementById('avatarImg');
  const ini=document.getElementById('avatarIni');
  if(!img){
    img=document.createElement('img');
    img.id='avatarImg';img.className='avatar-img';
    if(ini) ini.replaceWith(img);
    else wrap.insertBefore(img,wrap.querySelector('.avatar-btn'));
  }
  img.src=url;
  toast('Foto atualizada! Salve para confirmar.','info');
}

// ── Verificação ───────────────────────────────────────────────
const verifications = [
  {key:'email',     ico:'✉️', name:'E-mail verificado',      sub:'Confirmado no cadastro',         ok:true},
  {key:'pix',       ico:'💳', name:'Chave PIX cadastrada',   sub:'Necessária para receber saques',  ok:<?= !empty($teacher['pix_key'])?'true':'false' ?>},
  {key:'subjects',  ico:'📚', name:'Matérias definidas',     sub:'Pelo menos 1 matéria',            ok:<?= count($subjects)>0?'true':'false' ?>},
  {key:'diploma',   ico:'🎓', name:'Diploma enviado',        sub:'Aguardando validação da equipe',  ok:false},
  {key:'bio',       ico:'📝', name:'Biografia completa',     sub:'Mínimo 100 caracteres',           ok:<?= mb_strlen($teacher['bio']??'','UTF-8')>=100?'true':'false' ?>},
  {key:'packages',  ico:'📦', name:'Pacotes configurados',   sub:'Pelo menos 1 pacote de correção', ok:<?= count($packages)>0?'true':'false' ?>},
];

function renderVerifications(){
  const el=document.getElementById('verifyList');
  el.innerHTML=verifications.map(v=>`
    <div class="verify-item ${v.ok?'ok':'pending'}">
      <span class="verify-ico">${v.ico}</span>
      <div class="verify-info">
        <div class="verify-name">${v.name}</div>
        <div class="verify-sub">${v.sub}</div>
      </div>
      <span class="verify-badge ${v.ok?'ok':'pending'}">${v.ok?'✓ OK':'Pendente'}</span>
    </div>`).join('');
}
renderVerifications();

// ── Matérias ──────────────────────────────────────────────────
function renderSubjects(){
  const wrap=document.getElementById('subjectsWrap');
  wrap.innerHTML=subjects.map((s,i)=>`
    <span class="subj-tag">
      ${esc(s)}
      <button onclick="removeSubjectByIdx(${i})" title="Remover">✕</button>
    </span>`).join('');
  // Atualiza verificação
  verifications.find(v=>v.key==='subjects').ok=subjects.length>0;
  renderVerifications();
}

function addSubject(){
  const inp=document.getElementById('subjInput');
  const val=inp.value.trim();
  if(!val){toast('Informe o nome da matéria.','err');return;}
  if(subjects.includes(val)){toast('Matéria já adicionada.','err');return;}
  if(subjects.length>=10){toast('Máximo de 10 matérias.','err');return;}
  subjects.push(val);
  inp.value='';
  renderSubjects();
}

function removeSubject(btn){
  const tag=btn.closest('.subj-tag');
  const name=tag.textContent.trim().replace('✕','').trim();
  subjects=subjects.filter(s=>s!==name);
  renderSubjects();
}

function removeSubjectByIdx(idx){
  subjects.splice(idx,1);
  renderSubjects();
}

// ── Pacotes ───────────────────────────────────────────────────
function renderPackages(){
  const el=document.getElementById('packageList');
  el.innerHTML=packages.map((p,i)=>`
    <div class="package-item">
      <div class="pkg-info">
        <div class="pkg-name">${esc(p.name)}</div>
        <div class="pkg-qty">${p.quantity} correção${p.quantity!=1?'ões':''}</div>
      </div>
      <div class="pkg-price">R$ ${parseFloat(p.price).toFixed(2).replace('.',',')}</div>
      <button class="pkg-del" onclick="removePackageByIdx(${i})" title="Remover">✕</button>
    </div>`).join('');
  verifications.find(v=>v.key==='packages').ok=packages.length>0;
  renderVerifications();
}

function addPackage(){
  const name =document.getElementById('pkgName').value.trim();
  const qty  =parseInt(document.getElementById('pkgQty').value)||1;
  const price=parseFloat(document.getElementById('pkgPrice').value)||0;
  if(!name){toast('Informe o nome do pacote.','err');return;}
  if(price<10){toast('Preço mínimo: R$10.','err');return;}
  if(packages.length>=5){toast('Máximo de 5 pacotes.','err');return;}
  packages.push({name,quantity:Math.min(20,Math.max(1,qty)),price});
  document.getElementById('pkgName').value='';
  document.getElementById('pkgQty').value='1';
  document.getElementById('pkgPrice').value='';
  renderPackages();
}

function removePackage(btn){
  const idx=Array.from(document.getElementById('packageList').children)
    .indexOf(btn.closest('.package-item'));
  packages.splice(idx,1);
  renderPackages();
}
function removePackageByIdx(idx){packages.splice(idx,1);renderPackages();}

// ── Diploma ───────────────────────────────────────────────────
function handleDiplomaFiles(files){
  Array.from(files).forEach(f=>{
    if(f.size>5*1024*1024){toast(`"${f.name}" maior que 5MB.`,'err');return;}
    if(diplomaFiles.find(x=>x.name===f.name)) return;
    diplomaFiles.push(f);
  });
  renderDiplomas();
  document.getElementById('diplomaZone').classList.add('has-file');
}

function renderDiplomas(){
  const el=document.getElementById('diplomaPreview');
  if(!diplomaFiles.length){el.innerHTML='';return;}
  el.innerHTML=diplomaFiles.map((f,i)=>`
    <div class="diploma-file-item">
      <span class="diploma-file-ico">${f.type.includes('pdf')?'📄':'🖼️'}</span>
      <span class="diploma-file-name">${esc(f.name)}</span>
      <button class="diploma-file-del" onclick="removeDiploma(${i})" title="Remover">✕</button>
    </div>`).join('');
}

function removeDiploma(idx){
  diplomaFiles.splice(idx,1);
  renderDiplomas();
  if(!diplomaFiles.length) document.getElementById('diplomaZone').classList.remove('has-file');
}

// Drag and drop
const zone=document.getElementById('diplomaZone');
zone.addEventListener('dragover',e=>{e.preventDefault();zone.style.borderColor='var(--g400)'});
zone.addEventListener('dragleave',()=>zone.style.borderColor='');
zone.addEventListener('drop',e=>{
  e.preventDefault();zone.style.borderColor='';
  handleDiplomaFiles(e.dataTransfer.files);
});

// ── Senha ─────────────────────────────────────────────────────
function togglePass(){
  const s=document.getElementById('passSection');
  s.classList.toggle('open');
}

// ── Salvar tudo ───────────────────────────────────────────────
async function saveAll(){
  const name   = document.getElementById('fName').value.trim();
  const bio    = document.getElementById('fBio').value.trim();
  const pix    = document.getElementById('fPix').value.trim();
  const passN  = document.getElementById('fPassNew').value;
  const passC  = document.getElementById('fPassConf').value;

  if(!name){toast('Informe seu nome.','err');return;}

  let errors=[];

  // Dados pessoais
  const r1=await api(API_PROFILE,{action:'update',name,bio,pix_key:pix});
  if(!r1.success) errors.push('Dados pessoais: '+r1.message);

  // Matérias
  const r2=await api(API_PROFILE,{action:'subjects_save',subjects});
  if(!r2.success) errors.push('Matérias: '+r2.message);

  // Pacotes
  const r3=await api(API_PROFILE,{action:'packages_save',packages});
  if(!r3.success) errors.push('Pacotes: '+r3.message);

  // Senha (opcional)
  if(passN||passC){
    if(passN!==passC){errors.push('Senhas não coincidem.');} 
    else if(passN.length<6){errors.push('Senha muito curta (mín. 6 caracteres).');}
    else {
      const r4=await api(API_AUTH,{action:'change_password',password:passN});
      if(!r4.success) errors.push('Senha: '+r4.message);
      else{document.getElementById('fPassNew').value='';document.getElementById('fPassConf').value='';}
    }
  }

  // Atualiza verificações locais
  verifications.find(v=>v.key==='bio').ok=bio.length>=100;
  verifications.find(v=>v.key==='pix').ok=!!pix;
  renderVerifications();

  if(errors.length){
    errors.forEach(e=>toast(e,'err',5000));
  } else {
    toast('Perfil salvo com sucesso! ✅');
  }
}

// Init
renderPackages();
</script>
</body>
</html>