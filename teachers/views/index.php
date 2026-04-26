<?php
// ============================================================
// /florescer/teachers/views/index.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';

startTeacherSession();
if (isTeacherLoggedIn()) {
    header('Location: ' . TEACHER_VIEWS . '/dashboard.php');
    exit;
}

$erro = htmlspecialchars($_GET['erro'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Área do Professor — florescer</title>
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
  --gold:#c9a84c;--gold-l:#fdf3d8;
  --red:#d94040;--red-l:#fdeaea;
  --fd:'Fraunces',Georgia,serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:16px;--rs:10px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh2:0 8px 24px rgba(0,0,0,.1);--sh3:0 20px 60px rgba(0,0,0,.15);
}
html,body{min-height:100%;font-family:var(--fb);-webkit-font-smoothing:antialiased}
body{background:linear-gradient(135deg,var(--g900) 0%,var(--g800) 50%,var(--g700) 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1.2rem;position:relative;overflow-x:hidden}

/* Background decorativo */
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 60% at 20% 20%,rgba(61,153,112,.12) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 80% 80%,rgba(85,184,138,.07) 0%,transparent 55%);pointer-events:none}
body::after{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.04) 1px,transparent 1px);background-size:32px 32px;pointer-events:none;opacity:.4}

.wrap{position:relative;z-index:1;width:100%;max-width:480px}

/* Logo */
.logo{display:flex;align-items:center;gap:.55rem;justify-content:center;margin-bottom:2rem;text-decoration:none}
.logo-mark{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--g400),var(--g600));display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 4px 16px rgba(61,153,112,.4)}
.logo-name{font-family:var(--fd);font-size:1.3rem;font-weight:600;color:var(--g100)}
.logo-tag{font-size:.6rem;color:rgba(141,212,176,.35);text-transform:uppercase;letter-spacing:.12em;display:block;margin-top:.04rem}

/* Card */
.card{background:rgba(255,255,255,.97);border-radius:20px;box-shadow:var(--sh3);overflow:hidden}

/* Tabs */
.tabs{display:flex;background:var(--g25);border-bottom:1px solid var(--n100)}
.tab{flex:1;padding:.85rem;border:none;background:transparent;font-family:var(--fb);font-size:.85rem;font-weight:500;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e);position:relative}
.tab.on{color:var(--g600);font-weight:600}
.tab.on::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:var(--g500)}

.card-body{padding:1.6rem}

/* Alert de banimento */
.ban-alert{display:flex;align-items:flex-start;gap:.7rem;padding:.9rem 1rem;background:var(--red-l);border:1px solid rgba(217,64,64,.25);border-radius:var(--rs);margin-bottom:1rem}
.ban-ico{font-size:1.2rem;flex-shrink:0}
.ban-text strong{display:block;font-size:.82rem;font-weight:700;color:var(--red);margin-bottom:.18rem}
.ban-text p{font-size:.76rem;color:#b91c1c;line-height:1.55}

/* Alert geral */
.alert{padding:.7rem .9rem;border-radius:var(--rs);font-size:.8rem;margin-bottom:.9rem;display:none;line-height:1.5}
.alert.show{display:block}
.alert.err{background:var(--red-l);border:1px solid rgba(217,64,64,.2);color:var(--red)}
.alert.ok{background:var(--g50);border:1px solid rgba(45,122,88,.2);color:var(--g600)}
.alert.warn{background:var(--gold-l);border:1px solid rgba(201,168,76,.25);color:#92720c}

/* Form */
.fg{margin-bottom:.85rem}
.fl{display:block;font-size:.76rem;font-weight:600;color:var(--n400);margin-bottom:.28rem;letter-spacing:.01em}
.fc{width:100%;padding:.65rem .88rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.88rem;outline:none;transition:all var(--d) var(--e)}
.fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.1)}
.fc::placeholder{color:#bbb}
textarea.fc{resize:vertical;min-height:88px;line-height:1.65}
.fc-hint{font-size:.68rem;color:var(--n400);margin-top:.2rem}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}

/* Upload currículo */
.upload-zone{border:2px dashed var(--n100);border-radius:var(--rs);padding:.9rem;text-align:center;cursor:pointer;transition:all var(--d) var(--e);position:relative;background:var(--g25)}
.upload-zone:hover{border-color:var(--g300);background:var(--g50)}
.upload-zone.has{border-color:var(--g300);background:var(--g50);border-style:solid}
.upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-ico{font-size:1.4rem;opacity:.4;display:block;margin-bottom:.3rem}
.upload-lbl{font-size:.76rem;color:var(--n400);line-height:1.5}
.upload-lbl strong{color:var(--g600)}
.upload-file{display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;background:var(--white);border:1px solid var(--g100);border-radius:6px;margin-top:.5rem;font-size:.74rem;color:var(--n800)}
.upload-file-del{margin-left:auto;background:none;border:none;cursor:pointer;color:var(--n400);font-size:.75rem;transition:color var(--d) var(--e)}
.upload-file-del:hover{color:var(--red)}

/* Termos */
.terms-box{display:flex;align-items:flex-start;gap:.55rem;padding:.8rem .9rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);cursor:pointer;transition:border-color var(--d) var(--e)}
.terms-box:hover{border-color:var(--g300)}
.terms-box input[type=checkbox]{width:17px;height:17px;accent-color:var(--g500);cursor:pointer;flex-shrink:0;margin-top:1px}
.terms-txt{font-size:.78rem;color:var(--n400);line-height:1.55}
.terms-txt a{color:var(--g500);text-decoration:underline;text-underline-offset:2px;cursor:pointer;background:none;border:none;font-family:inherit;font-size:inherit;padding:0}
.terms-txt a:hover{color:var(--g700)}

/* Botão */
.btn-submit{width:100%;padding:.78rem;border-radius:50px;border:none;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;font-family:var(--fb);font-size:.9rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 4px 16px rgba(45,122,88,.28);margin-top:.2rem}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(45,122,88,.38)}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}

.forgot{font-size:.76rem;color:var(--g500);text-decoration:none;display:block;text-align:right;margin-top:.3rem;cursor:pointer;background:none;border:none;font-family:inherit}
.forgot:hover{color:var(--g700)}

/* Comissão info */
.commission-box{display:flex;gap:.65rem;margin-bottom:.85rem}
.comm-item{flex:1;text-align:center;padding:.6rem .4rem;background:var(--g25);border:1px solid var(--g100);border-radius:var(--rs)}
.comm-val{font-family:var(--fd);font-size:1.2rem;font-weight:900;color:var(--g600);line-height:1}
.comm-lbl{font-size:.62rem;color:var(--n400);text-transform:uppercase;letter-spacing:.05em;margin-top:.15rem}

/* Info card */
.info-strip{display:flex;align-items:center;gap:.5rem;padding:.6rem .8rem;background:var(--gold-l);border:1px solid rgba(201,168,76,.2);border-radius:var(--rs);font-size:.74rem;color:#92720c;margin-bottom:.85rem;line-height:1.5}

/* Modal termos */
.modal-overlay{position:fixed;inset:0;z-index:300;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);display:flex;align-items:flex-start;justify-content:center;padding:1.5rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e);overflow-y:auto}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--white);border-radius:18px;width:100%;max-width:580px;box-shadow:var(--sh3);transform:translateY(12px) scale(.97);transition:transform var(--d) var(--e);margin:auto}
.modal-overlay.open .modal{transform:none}
.modal-head{padding:.9rem 1.3rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1;border-radius:18px 18px 0 0}
.modal-title{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--n800)}
.modal-x{width:28px;height:28px;border-radius:50%;background:var(--n50);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:var(--n400);transition:all var(--d) var(--e)}
.modal-x:hover{background:var(--red-l);color:var(--red)}
.modal-body{padding:1.4rem;font-size:.83rem;color:var(--n400);line-height:1.85;display:flex;flex-direction:column;gap:1rem}
.modal-body h3{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800);margin-bottom:.2rem}
.modal-body p{margin:0}
.modal-foot{padding:.85rem 1.3rem;border-top:1px solid var(--n100);display:flex;justify-content:flex-end}
.btn-aceitar{padding:.55rem 1.3rem;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.82rem;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(45,122,88,.22);transition:all var(--d) var(--e)}
.btn-aceitar:hover{filter:brightness(1.08)}

@media(max-width:520px){.frow{grid-template-columns:1fr}.commission-box{flex-direction:column}}
</style>
</head>
<body>

<div class="wrap">

  <a class="logo" href="/florescer/public/index.php">
    <div class="logo-mark">🌱</div>
    <div>
      <span class="logo-name">florescer</span>
      <span class="logo-tag">área do professor</span>
    </div>
  </a>

  <div class="card">

    <div class="tabs">
      <button class="tab on" id="t1" onclick="switchTab('login')">Entrar</button>
      <button class="tab"    id="t2" onclick="switchTab('candidatura')">Quero ser professor</button>
    </div>

    <div class="card-body">

      <!-- Alert de conta suspensa/banida -->
      <?php if($erro === 'conta_suspensa'): ?>
      <div class="ban-alert">
        <span class="ban-ico">🚫</span>
        <div class="ban-text">
          <strong>Conta suspensa</strong>
          <p>Sua conta foi suspensa por violação dos termos de uso da plataforma florescer.
             Se acredita que isso foi um engano, entre em contato:
             <a href="mailto:florescer.appcontato@gmail.com" style="color:var(--red)">
               florescer.appcontato@gmail.com
             </a>
          </p>
        </div>
      </div>
      <?php endif; ?>

      <div class="alert" id="alert"></div>

      <!-- ── LOGIN ────────────────────────────────────────── -->
      <div id="fLogin">
        <div class="fg">
          <label class="fl">E-mail</label>
          <input class="fc" type="email" id="lEmail"
                 placeholder="seu@email.com" autocomplete="email"/>
        </div>
        <div class="fg">
          <label class="fl">Senha</label>
          <input class="fc" type="password" id="lPass"
                 placeholder="••••••••" autocomplete="current-password"/>
          <button class="forgot" onclick="showForgot()">Esqueci minha senha</button>
        </div>
        <button class="btn-submit" id="btnLogin" onclick="doLogin()">
          Entrar na plataforma
        </button>
      </div>

      <!-- ── CANDIDATURA ──────────────────────────────────── -->
      <div id="fCandidatura" style="display:none">

        <div class="info-strip">
          ⏱ Após envio, analisamos sua candidatura em até <strong>48h</strong> e você recebe um e-mail com a resposta.
        </div>

        <div class="commission-box">
          <div class="comm-item">
            <div class="comm-val">90%</div>
            <div class="comm-lbl">Você recebe<br>por redação</div>
          </div>
          <div class="comm-item">
            <div class="comm-val">80%</div>
            <div class="comm-lbl">Você recebe<br>por aula</div>
          </div>
          <div class="comm-item">
            <div class="comm-val">100%</div>
            <div class="comm-lbl">Você define<br>o preço</div>
          </div>
        </div>

        <div class="frow">
          <div class="fg">
            <label class="fl">Nome completo *</label>
            <input class="fc" type="text" id="cName"
                   placeholder="Seu nome" maxlength="100"/>
          </div>
          <div class="fg">
            <label class="fl">E-mail *</label>
            <input class="fc" type="email" id="cEmail"
                   placeholder="seu@email.com"/>
          </div>
        </div>

        <div class="fg">
          <label class="fl">Matérias que leciona *</label>
          <input class="fc" type="text" id="cSubjects"
                 placeholder="Ex: Matemática, Redação ENEM, Inglês, Física…"
                 maxlength="200"/>
          <div class="fc-hint">Separe por vírgula</div>
        </div>

        <div class="fg">
          <label class="fl">Formação acadêmica *</label>
          <input class="fc" type="text" id="cFormacao"
                 placeholder="Ex: Licenciatura em Matemática — UNICAMP 2018"
                 maxlength="150"/>
        </div>

        <div class="fg">
          <label class="fl">Tempo de experiência *</label>
          <select class="fc" id="cExp">
            <option value="">Selecionar…</option>
            <option value="menos1">Menos de 1 ano</option>
            <option value="1a3">1 a 3 anos</option>
            <option value="3a5">3 a 5 anos</option>
            <option value="5a10">5 a 10 anos</option>
            <option value="mais10">Mais de 10 anos</option>
          </select>
        </div>

        <div class="fg">
          <label class="fl">Sobre você *</label>
          <textarea class="fc" id="cBio"
                    placeholder="Conte sua experiência, metodologia e diferenciais como professor(a). Mínimo 100 caracteres."
                    maxlength="1000"></textarea>
          <div class="fc-hint"><span id="bioCount">0</span>/1000 caracteres</div>
        </div>

        <div class="fg">
          <label class="fl">Diploma ou certificado *</label>
          <div class="upload-zone" id="uploadZone">
            <span class="upload-ico">📄</span>
            <div class="upload-lbl">
              <strong>Clique para enviar</strong> seu diploma ou certificado<br>
              JPG, PNG ou PDF · Máx. 5 MB
            </div>
            <input type="file" id="cDiploma"
                   accept="image/*,.pdf"
                   onchange="handleFile(this)"/>
          </div>
          <div id="filePreview"></div>
          <div class="fc-hint">Aumenta muito suas chances de aprovação</div>
        </div>

        <div class="fg">
          <label class="fl">LinkedIn ou Lattes (opcional)</label>
          <input class="fc" type="url" id="cLink"
                 placeholder="https://linkedin.com/in/seu-perfil"/>
        </div>

        <div class="fg">
          <label class="terms-box" id="termsLabel">
            <input type="checkbox" id="cTerms"/>
            <span class="terms-txt">
              Li e concordo com os
              <a onclick="openTermos();return false">Termos de Uso para Professores</a>
              e entendo que a plataforma retém 10% sobre redações e 20% sobre aulas como comissão de serviço.
              Sei que em caso de banimento por violação dos termos, o acesso será encerrado permanentemente.
            </span>
          </label>
        </div>

        <button class="btn-submit" id="btnCandidatura" onclick="doCandidatura()">
          Enviar candidatura
        </button>
      </div>

      <!-- ── RECUPERAR SENHA ──────────────────────────────── -->
      <div id="fForgot" style="display:none">
        <div class="fg">
          <label class="fl">E-mail da conta</label>
          <input class="fc" type="email" id="fEmail"
                 placeholder="seu@email.com"/>
        </div>
        <button class="btn-submit" onclick="doForgot()">
          Enviar instruções de recuperação
        </button>
        <button class="forgot" onclick="switchTab('login')" style="text-align:center;width:100%;margin-top:.6rem">
          ← Voltar ao login
        </button>
      </div>

    </div>
  </div>

  <div style="text-align:center;margin-top:1.2rem;font-size:.72rem;color:rgba(194,234,214,.3);line-height:1.7">
    🌱 florescer · Plataforma de estudos<br>
    <a href="/florescer/public/index.php" style="color:rgba(141,212,176,.4);text-decoration:none">
      ← Voltar para a plataforma
    </a>
  </div>
</div>

<!-- Modal: Termos de Uso para Professores -->
<div class="modal-overlay" id="modalTermos">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">📋 Termos de Uso — Professores</span>
      <button class="modal-x" onclick="closeTermos()">✕</button>
    </div>
    <div class="modal-body">
      <div>
        <h3>1. Relação com a plataforma</h3>
        <p>O professor cadastrado na plataforma florescer atua de forma autônoma e independente. Não há vínculo empregatício entre o professor e a florescer. O professor é responsável por declarar seus rendimentos conforme a legislação vigente.</p>
      </div>
      <div>
        <h3>2. Comissão da plataforma</h3>
        <p>A florescer retém 10% sobre o valor de cada correção de redação e 20% sobre o valor de cada aula realizada. Esses percentuais são descontados automaticamente antes do crédito ao saldo do professor. O professor define livremente seus preços.</p>
      </div>
      <div>
        <h3>3. Proibição de contato externo</h3>
        <p>É expressamente proibido compartilhar dados de contato externo (telefone, e-mail, redes sociais, WhatsApp, Telegram etc.) com alunos dentro da plataforma. O objetivo é garantir a segurança de ambas as partes e a continuidade da relação comercial pela plataforma.</p>
      </div>
      <div>
        <h3>4. Banimento e suspensão</h3>
        <p>O descumprimento dos termos pode resultar em suspensão temporária ou banimento permanente. Em caso de banimento: o acesso é encerrado imediatamente; o saldo disponível pode ser retido por até 30 dias para análise; transações em andamento são canceladas e valores estornados aos alunos.</p>
      </div>
      <div>
        <h3>5. Qualidade e responsabilidade</h3>
        <p>O professor compromete-se a realizar correções e aulas com qualidade e dentro dos prazos acordados. Reclamações recorrentes de alunos podem resultar em suspensão. A florescer não se responsabiliza pelo conteúdo das aulas, apenas pela intermediação.</p>
      </div>
      <div>
        <h3>6. Pagamentos e saques</h3>
        <p>Os pagamentos dos alunos passam pela plataforma. O professor pode solicitar saque do saldo disponível a qualquer momento, com processamento em até 1 dia útil. O saldo pendente fica disponível após 7 dias da transação, para proteção contra chargebacks.</p>
      </div>
      <div>
        <h3>7. Aprovação e dados</h3>
        <p>O cadastro fica pendente de aprovação pela equipe florescer. A aprovação é discricionária. Os dados fornecidos na candidatura podem ser usados para verificação de identidade e exibição no perfil público.</p>
      </div>
      <div style="padding:.75rem;background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs);font-size:.8rem;color:var(--g600)">
        📅 Última atualização: <?= date('d/m/Y') ?>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-aceitar" onclick="aceitarTermos()">
        ✓ Li e aceito os termos
      </button>
    </div>
  </div>
</div>

<script>
const API = '<?= TEACHER_API ?>/auth.php';
let diplomaFile = null;

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(tab){
  document.getElementById('t1').classList.toggle('on', tab==='login');
  document.getElementById('t2').classList.toggle('on', tab==='candidatura');
  document.getElementById('fLogin').style.display       = tab==='login'        ? 'block':'none';
  document.getElementById('fCandidatura').style.display = tab==='candidatura'  ? 'block':'none';
  document.getElementById('fForgot').style.display      = 'none';
  clearAlert();
}

function showForgot(){
  document.getElementById('fLogin').style.display  = 'none';
  document.getElementById('fForgot').style.display = 'block';
  document.getElementById('t1').classList.remove('on');
  clearAlert();
}

// ── Alert ─────────────────────────────────────────────────────
function showAlert(msg, type='err'){
  const el=document.getElementById('alert');
  el.innerHTML=msg; el.className=`alert ${type} show`;
  el.scrollIntoView({behavior:'smooth',block:'nearest'});
}
function clearAlert(){ document.getElementById('alert').className='alert'; }

// ── Login ─────────────────────────────────────────────────────
async function doLogin(){
  const email = document.getElementById('lEmail').value.trim();
  const pass  = document.getElementById('lPass').value;
  if(!email||!pass){showAlert('Preencha e-mail e senha.');return;}
  const btn=document.getElementById('btnLogin');
  btn.disabled=true; btn.textContent='Entrando…';
  try{
    const d=await post({action:'login',email,password:pass});
    if(d.success){
      window.location.href='<?= TEACHER_VIEWS ?>/dashboard.php';
    } else {
      // Mensagem de banimento mais clara
      if(d.message?.includes('suspensa')||d.message?.includes('suspens')){
        showAlert('🚫 <strong>Conta suspensa.</strong> Sua conta foi banida por violação dos termos. Entre em contato: <a href="mailto:florescer.appcontato@gmail.com">florescer.appcontato@gmail.com</a>','err');
      } else {
        showAlert(d.message||'E-mail ou senha incorretos.');
      }
    }
  }catch{showAlert('Erro de conexão. Tente novamente.');}
  finally{btn.disabled=false;btn.textContent='Entrar na plataforma';}
}

// ── Candidatura ───────────────────────────────────────────────
// Contador bio
document.getElementById('cBio').addEventListener('input',function(){
  document.getElementById('bioCount').textContent=this.value.length;
});

function handleFile(input){
  const f=input.files[0];
  if(!f) return;
  if(f.size>5*1024*1024){alert('Arquivo maior que 5MB.');return;}
  diplomaFile=f;
  const zone=document.getElementById('uploadZone');
  zone.classList.add('has');
  document.getElementById('filePreview').innerHTML=`
    <div class="upload-file">
      <span>${f.type.includes('pdf')?'📄':'🖼️'}</span>
      <span>${f.name}</span>
      <button class="upload-file-del" onclick="removeFile()">✕</button>
    </div>`;
}

function removeFile(){
  diplomaFile=null;
  document.getElementById('cDiploma').value='';
  document.getElementById('filePreview').innerHTML='';
  document.getElementById('uploadZone').classList.remove('has');
}

async function doCandidatura(){
  const name     = document.getElementById('cName').value.trim();
  const email    = document.getElementById('cEmail').value.trim();
  const subjects = document.getElementById('cSubjects').value.trim();
  const formacao = document.getElementById('cFormacao').value.trim();
  const exp      = document.getElementById('cExp').value;
  const bio      = document.getElementById('cBio').value.trim();
  const link     = document.getElementById('cLink').value.trim();
  const terms    = document.getElementById('cTerms').checked;

  if(!name)     {showAlert('Informe seu nome.');return;}
  if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){showAlert('Informe um e-mail válido.');return;}
  if(!subjects) {showAlert('Informe as matérias que leciona.');return;}
  if(!formacao) {showAlert('Informe sua formação acadêmica.');return;}
  if(!exp)      {showAlert('Selecione seu tempo de experiência.');return;}
  if(bio.length<100){showAlert('A biografia deve ter pelo menos 100 caracteres.');return;}
  if(!diplomaFile)  {showAlert('Envie seu diploma ou certificado.');return;}
  if(!terms)        {showAlert('Aceite os termos de uso para continuar.');return;}

  const btn=document.getElementById('btnCandidatura');
  btn.disabled=true; btn.textContent='Enviando candidatura…';

  try{
    // Converte diploma para base64
    const base64 = await toBase64(diplomaFile);

    const d=await post({
      action:    'candidatura',
      name, email, subjects, formacao, exp, bio, link,
      diploma:   base64,
      diploma_name: diplomaFile.name,
      diploma_type: diplomaFile.type,
    });

    if(d.success){
      showAlert('✅ <strong>Candidatura enviada!</strong> Analisaremos em até 48h e você receberá um e-mail com a resposta.','ok');
      // Limpa form
      ['cName','cEmail','cSubjects','cFormacao','cBio','cLink'].forEach(id=>{
        document.getElementById(id).value='';
      });
      document.getElementById('cExp').value='';
      document.getElementById('cTerms').checked=false;
      document.getElementById('bioCount').textContent='0';
      removeFile();
    } else {
      showAlert(d.message||'Erro ao enviar candidatura.');
    }
  }catch(e){
    showAlert('Erro de conexão. Tente novamente.');
  }
  finally{btn.disabled=false;btn.textContent='Enviar candidatura';}
}

function toBase64(file){
  return new Promise((res,rej)=>{
    const r=new FileReader();
    r.onload=()=>res(r.result.split(',')[1]);
    r.onerror=rej;
    r.readAsDataURL(file);
  });
}

// ── Recuperar senha ───────────────────────────────────────────
async function doForgot(){
  const email=document.getElementById('fEmail').value.trim();
  if(!email){showAlert('Informe seu e-mail.');return;}
  const d=await post({action:'forgot',email});
  showAlert(d.message, d.success?'ok':'err');
}

// ── Termos modal ──────────────────────────────────────────────
function openTermos(){
  document.getElementById('modalTermos').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeTermos(){
  document.getElementById('modalTermos').classList.remove('open');
  document.body.style.overflow='';
}
function aceitarTermos(){
  document.getElementById('cTerms').checked=true;
  closeTermos();
}
document.getElementById('modalTermos').addEventListener('click',e=>{
  if(e.target===document.getElementById('modalTermos')) closeTermos();
});
document.addEventListener('keydown',e=>{if(e.key==='Escape') closeTermos();});

// ── Helper ────────────────────────────────────────────────────
async function post(body){
  const r=await fetch(API,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(body)
  });
  return r.json();
}

// Enter no login
document.addEventListener('keydown',e=>{
  if(e.key==='Enter' && document.getElementById('fLogin').style.display!=='none'){
    doLogin();
  }
});
</script>
</body>
</html>