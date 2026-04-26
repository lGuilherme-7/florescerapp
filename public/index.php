<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
startSession();
if (isLoggedIn()) { header('Location: /florescer/public/views/dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Florescer</title>
<meta name="description" content="Plataforma de estudos gamificada com streak diário, Pomodoro, aulas do YouTube e uma planta que cresce com seu conhecimento. Gratuita e sem anúncios."/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,900;1,9..144,400;1,9..144,900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --ivory:#fafdf8;
  --ivory2:#f3f9f0;
  --ivory3:#eaf4e5;
  --white:#ffffff;
  --forest:#0d2618;
  --forest2:#162e1f;
  --forest3:#1e3d2a;
  --green:#1a8f55;
  --green2:#22b56d;
  --green3:#2dd48a;
  --green-soft:rgba(26,143,85,.1);
  --green-soft2:rgba(26,143,85,.18);
  --green-border:rgba(26,143,85,.22);
  --green-border2:rgba(26,143,85,.4);
  --gold:#FFD700;
  --gold2:#e0a030;
  --gold3:#f5c060;
  --gold-soft:rgba(200,136,42,.1);
  --gold-border:rgba(200,136,42,.25);
  --t-dark:#0d2618;
  --t-mid:#2a5040;
  --t-muted:#5a8070;
  --t-faint:#8aaa9a;
  --glass:rgba(255,255,255,.72);
  --glass2:rgba(255,255,255,.88);
  --glass-border:rgba(255,255,255,.9);
  --glass-shadow:0 8px 40px rgba(13,38,24,.1);
  --glass-shadow-lg:0 20px 60px rgba(13,38,24,.14);
  --err:#d94040;
  --err-soft:rgba(217,64,64,.1);
  --serif:'Fraunces',Georgia,serif;
  --sans:'DM Sans',system-ui,sans-serif;
  --r:16px;
  --rs:10px;
  --ease:cubic-bezier(.4,0,.2,1);
  --spring:cubic-bezier(.34,1.56,.64,1);
  --bounce:cubic-bezier(.68,-.55,.265,1.55);
  --t:.26s var(--ease);
}

html{scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{
  font-family:var(--sans);
  background:var(--ivory);
  color:var(--t-dark);
  overflow-x:hidden;
  min-height:100vh;
  font-size:16px;
  line-height:1.6;
}

/* ═══════════════════════════════════════
   BACKGROUND
═══════════════════════════════════════ */
.bg-mesh{
  position:fixed;inset:0;z-index:0;pointer-events:none;
  background:
    radial-gradient(ellipse 75% 65% at 5% -5%,rgba(26,143,85,.07) 0%,transparent 55%),
    radial-gradient(ellipse 55% 50% at 95% 108%,rgba(26,143,85,.05) 0%,transparent 50%),
    radial-gradient(ellipse 45% 40% at 50% 55%,rgba(245,192,96,.04) 0%,transparent 60%),
    linear-gradient(180deg,var(--ivory) 0%,var(--ivory2) 50%,var(--ivory3) 100%);
}
.bg-dots{
  position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.35;
  background-image:radial-gradient(circle,rgba(26,143,85,.15) 1px,transparent 1px);
  background-size:32px 32px;
}

/* ═══════════════════════════════════════
   TYPOGRAPHY
═══════════════════════════════════════ */
.serif{font-family:var(--serif)}
.eyebrow{
  font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;
  color:var(--green);font-weight:600;font-family:var(--sans);
  display:flex;align-items:center;justify-content:center;gap:.5rem;
  margin-bottom:.55rem;
}
.eyebrow::before,.eyebrow::after{content:'';height:1px;width:28px;background:var(--green-border2)}
.sec-h{
  font-family:var(--serif);font-size:clamp(1.6rem,5vw,2.8rem);
  font-weight:900;line-height:1.1;color:var(--forest);
  text-align:center;margin-bottom:.7rem;
}
.sec-h em{font-style:italic;color:var(--green);font-weight:400}
.sec-h .gold{color:var(--gold)}
.sec-p{
  text-align:center;color:var(--t-muted);font-size:.96rem;
  max-width:480px;margin:0 auto 2.5rem;line-height:1.85;font-weight:300;
}

/* ═══════════════════════════════════════
   GLASS
═══════════════════════════════════════ */
.glass{
  background:var(--glass);
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  border:1px solid var(--glass-border);
  box-shadow:var(--glass-shadow);
}
.glass-lg{
  background:var(--glass2);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border:1px solid var(--glass-border);
  box-shadow:var(--glass-shadow-lg);
}

/* ═══════════════════════════════════════
   NAV — MOBILE FIRST
═══════════════════════════════════════ */
nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:.9rem 1.2rem;
  display:flex;align-items:center;justify-content:space-between;
  transition:var(--t);
}
nav.scrolled{
  background:rgba(250,253,248,.95);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--green-border);
  box-shadow:0 4px 24px rgba(13,38,24,.07);
}
.logo{display:flex;align-items:center;gap:.5rem;text-decoration:none}
.logo-icon{
  width:32px;height:32px;border-radius:8px;
  background:linear-gradient(135deg,var(--green),var(--green2));
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 12px rgba(26,143,85,.3);flex-shrink:0;
  overflow:hidden;padding:0;
}
.logo-icon img{width:100%;height:100%;object-fit:cover;display:block;border-radius:8px}
.logo-name{
  font-family:var(--serif);font-size:1.2rem;font-weight:900;
  color:var(--forest);letter-spacing:-.03em;
}
.logo-name span{color:var(--green)}
.nav-links{display:none}
.nav-r{display:flex;gap:.5rem;align-items:center}
.nav-r .btn-ghost{display:none}

/* ═══════════════════════════════════════
   BUTTONS
═══════════════════════════════════════ */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:.4rem;
  padding:.65rem 1.3rem;border-radius:50px;
  font-family:var(--sans);font-size:.86rem;font-weight:600;
  cursor:pointer;border:none;text-decoration:none;
  white-space:nowrap;transition:all var(--t);position:relative;overflow:hidden;
  -webkit-tap-highlight-color:transparent;
  min-height:44px;
}
.btn-ghost{
  background:transparent;color:var(--t-muted);
  border:1.5px solid var(--green-border2);
}
.btn-ghost:hover{background:var(--green-soft);color:var(--forest);border-color:var(--green)}
.btn-green{
  background:linear-gradient(135deg,var(--green),var(--green2));
  color:#fff;font-weight:600;
  box-shadow:0 4px 16px rgba(26,143,85,.3);
}
.btn-green:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(26,143,85,.4)}
.btn-gold{
  background:linear-gradient(135deg,var(--gold),var(--gold2));
  color:#fff;font-weight:700;
  box-shadow:0 4px 18px rgba(200,136,42,.3);
}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 10px 32px rgba(200,136,42,.45)}
.btn-outline-gold{
  background:transparent;color:var(--gold);
  border:1.5px solid var(--gold-border);
}
.btn-outline-gold:hover{background:var(--gold-soft);border-color:var(--gold2);color:var(--gold2)}
.btn-full{width:100%;padding:.88rem;font-size:.92rem;border-radius:12px;justify-content:center}
.btn-lg{padding:.85rem 1.8rem;font-size:.95rem}
.btn.loading{pointer-events:none;opacity:.7}
.btn.loading::after{
  content:'';position:absolute;right:1.1rem;top:50%;margin-top:-8px;
  width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;
  border-radius:50%;animation:spin .6s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ═══════════════════════════════════════
   HERO — MOBILE FIRST
═══════════════════════════════════════ */
.hero{
  position:relative;z-index:1;
  display:flex;flex-direction:column;align-items:center;
  padding:6rem 1.2rem 3rem;
  max-width:1240px;margin:0 auto;
  gap:2.5rem;
  text-align:center;
}
.hero-left{width:100%;animation:fadeUp .9s var(--ease) both}
.hero-pill{
  display:inline-flex;align-items:center;gap:.5rem;
  padding:.35rem 1rem .35rem .6rem;
  background:linear-gradient(135deg,rgba(26,143,85,.12),rgba(26,143,85,.06));
  border:1px solid var(--green-border2);border-radius:50px;
  font-size:.72rem;font-weight:600;color:var(--green);letter-spacing:.04em;
  margin-bottom:1.4rem;
}
.hero-pill-dot{
  width:22px;height:22px;border-radius:50%;
  background:linear-gradient(135deg,var(--green),var(--green2));
  display:flex;align-items:center;justify-content:center;font-size:.75rem;
}
h1{
  font-family:var(--serif);
  font-size:clamp(2.2rem,8vw,5rem);
  font-weight:900;line-height:1.05;
  color:var(--forest);margin-bottom:1.2rem;
  letter-spacing:-.03em;
}
h1 em{color:var(--green);font-style:italic;font-weight:400;display:inline-block;position:relative}
h1 .gold{color:var(--gold);font-style:italic;font-weight:400}
.hero-desc{
  font-size:1rem;line-height:1.85;
  color:var(--t-muted);max-width:100%;
  margin-bottom:1.5rem;font-weight:300;
}
.hero-story{
  background:var(--glass);backdrop-filter:blur(12px);
  border:1px solid var(--glass-border);
  border-left:3px solid var(--green);
  border-radius:0 var(--rs) var(--rs) 0;
  padding:1rem 1.2rem;margin-bottom:1.5rem;
  font-size:.86rem;color:var(--t-muted);line-height:1.8;
  text-align:left;
  box-shadow:var(--glass-shadow);
}
.hero-story strong{color:var(--green);font-weight:600}
.hero-story .gold{color:var(--gold);font-weight:600}
.hero-ctas{
  display:flex;gap:.7rem;flex-wrap:wrap;
  justify-content:center;margin-bottom:2rem;
}
.hero-stats{
  display:grid;grid-template-columns:repeat(2,1fr);
  border-radius:var(--r);overflow:hidden;
  border:1px solid var(--green-border);
  background:var(--glass);backdrop-filter:blur(12px);
  max-width:480px;margin:0 auto;
  width:100%;
}
.hero-stat{
  padding:.9rem 1rem;text-align:center;
  border-right:1px solid var(--green-border);
  border-bottom:1px solid var(--green-border);
}
.hero-stat:nth-child(2n){border-right:none}
.hero-stat:nth-last-child(-n+2){border-bottom:none}
.stat-val{
  font-family:var(--serif);font-size:1.6rem;font-weight:900;
  color:var(--green);display:block;line-height:1;
}
.stat-gold{color:var(--gold)!important}
.stat-lbl{font-size:.68rem;color:var(--t-faint);text-transform:uppercase;letter-spacing:.07em;margin-top:.2rem;display:block}

/* ═══════════════════════════════════════
   HERO RIGHT — PLANT SCENE — MOBILE
═══════════════════════════════════════ */
.hero-right{
  width:100%;
  display:flex;align-items:center;justify-content:center;
  animation:fadeIn 1.2s var(--ease) both .4s;
  position:relative;
}
.plant-scene{
  position:relative;
  width:min(340px,90vw);
  height:480px;
}
.plant-aura{
  position:absolute;bottom:40px;left:50%;transform:translateX(-50%);
  width:260px;height:260px;border-radius:50%;pointer-events:none;
  background:radial-gradient(circle,rgba(26,143,85,.09) 0%,transparent 70%);
  animation:aura 5s ease-in-out infinite;
}
@keyframes aura{
  0%,100%{transform:translateX(-50%) scale(1);opacity:.6}
  50%{transform:translateX(-50%) scale(1.18);opacity:1}
}
#plantWrap{
  position:absolute;bottom:0;left:0;right:0;
  display:flex;justify-content:center;align-items:flex-end;
  overflow:visible;
}
#plantWrap svg{transition:all .8s var(--spring)}

.stage-selector{
  position:absolute;top:0;left:50%;transform:translateX(-50%);
  display:flex;align-items:center;gap:.4rem;
  background:var(--glass);backdrop-filter:blur(16px);
  border:1px solid var(--green-border2);border-radius:50px;
  padding:.4rem .75rem;font-size:.72rem;color:var(--t-muted);
  box-shadow:var(--glass-shadow);white-space:nowrap;
}
.stage-selector strong{color:var(--forest)}
.s-btn{
  width:28px;height:28px;border-radius:50%;background:var(--green-soft);
  border:1px solid var(--green-border);cursor:pointer;color:var(--green);
  display:flex;align-items:center;justify-content:center;font-size:.85rem;
  font-weight:700;transition:all var(--t);flex-shrink:0;
  min-width:28px;min-height:28px;-webkit-tap-highlight-color:transparent;
}
.s-btn:hover{background:var(--green);color:#fff}

/* streak & xp badges — reposicionados para não sobrepor a planta */
.streak-badge{
  position:absolute;
  top:60px;
  right:0;
  transform:translateX(0);
  background:var(--glass);backdrop-filter:blur(16px);
  border:1px solid var(--glass-border);border-radius:18px;
  padding:.8rem 1rem;min-width:140px;
  box-shadow:var(--glass-shadow-lg);
  animation:fadeIn 1.5s var(--ease) both .9s;
  z-index:10;
}
.streak-top{display:flex;align-items:center;gap:.55rem;margin-bottom:.5rem}
.fire-ico{
  font-size:1.3rem;animation:flicker 1.8s ease-in-out infinite;
  filter:drop-shadow(0 0 6px rgba(240,140,40,.4));
}
@keyframes flicker{0%,100%{transform:scale(1) rotate(-3deg)}50%{transform:scale(1.1) rotate(3deg)}}
.streak-num{font-family:var(--serif);font-size:1.5rem;font-weight:900;color:var(--gold);line-height:1}
.streak-sub{font-size:.65rem;color:var(--t-faint);text-transform:uppercase;letter-spacing:.06em;margin-top:.05rem}
.streak-divider{height:1px;background:var(--green-border);margin:.45rem 0}
.drops-row{display:flex;align-items:center;gap:.25rem}
.drops-label{font-size:.65rem;color:var(--t-faint);margin-right:.2rem}
.drop-ico{width:16px;height:20px;display:flex;align-items:center;justify-content:center}
.drop-ico svg{width:14px;height:18px}

.xp-badge{
  position:absolute;
  bottom:60px;
  left:0;
  background:var(--glass);backdrop-filter:blur(16px);
  border:1px solid var(--glass-border);border-radius:16px;
  padding:.75rem 1rem;min-width:136px;
  box-shadow:var(--glass-shadow-lg);
  animation:fadeIn 1.5s var(--ease) both 1.1s;
}
.xp-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem}
.xp-label{font-size:.65rem;font-weight:600;color:var(--t-muted);text-transform:uppercase;letter-spacing:.06em}
.xp-val{font-size:.7rem;color:var(--green);font-weight:600}
.xp-track{height:6px;background:var(--green-soft2);border-radius:3px;overflow:hidden;position:relative}
.xp-fill{
  height:100%;border-radius:3px;
  background:linear-gradient(90deg,var(--green),var(--green2));
  position:absolute;top:0;left:0;width:0;
  transition:width 1.5s var(--spring);
}
.xp-stage{font-size:.67rem;color:var(--t-faint);margin-top:.35rem}

/* ═══════════════════════════════════════
   ORIGIN SECTION
═══════════════════════════════════════ */
.origin-section{
  position:relative;z-index:1;
  max-width:1100px;margin:0 auto;padding:4rem 1.2rem;
}
.origin-inner{
  display:flex;flex-direction:column;gap:2.5rem;
}
.origin-eyebrow{
  display:inline-flex;align-items:center;gap:.5rem;
  background:var(--gold-soft);border:1px solid var(--gold-border);
  border-radius:50px;padding:.3rem .85rem;
  font-size:.72rem;font-weight:600;color:var(--gold);
  letter-spacing:.08em;text-transform:uppercase;
  margin-bottom:1.1rem;
}
.origin-h{
  font-family:var(--serif);font-size:clamp(1.7rem,5vw,2.7rem);
  font-weight:900;color:var(--forest);line-height:1.1;margin-bottom:1rem;
}
.origin-h em{color:var(--gold);font-style:italic;font-weight:400}
.origin-p{font-size:.93rem;line-height:1.9;color:var(--t-muted);font-weight:300;margin-bottom:1rem}
.origin-p strong{color:var(--forest);font-weight:600}
.origin-quote{
  background:var(--glass);backdrop-filter:blur(12px);
  border:1px solid var(--glass-border);
  border-left:3px solid var(--gold);
  border-radius:0 var(--rs) var(--rs) 0;
  padding:1rem 1.2rem;
  font-family:var(--serif);font-size:.95rem;
  font-style:italic;color:var(--t-mid);line-height:1.7;
  box-shadow:var(--glass-shadow);margin-top:1.2rem;
}
.origin-quote cite{
  display:block;font-family:var(--sans);font-style:normal;
  font-size:.73rem;color:var(--t-faint);margin-top:.4rem;
}
.origin-cards{display:grid;grid-template-columns:1fr;gap:.9rem}
.origin-card{
  background:var(--glass);backdrop-filter:blur(14px);
  border:1px solid var(--glass-border);border-radius:var(--r);
  padding:1.2rem 1.4rem;box-shadow:var(--glass-shadow);
  transition:transform var(--t),box-shadow var(--t);
}
.origin-card:hover{transform:translateY(-3px);box-shadow:var(--glass-shadow-lg)}
.oc-top{display:flex;align-items:center;gap:.65rem;margin-bottom:.5rem}
.oc-ico{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;
}
.oc-ico.green{background:var(--green-soft2);border:1px solid var(--green-border)}
.oc-ico.gold{background:var(--gold-soft);border:1px solid var(--gold-border)}
.oc-title{font-family:var(--serif);font-size:.95rem;font-weight:600;color:var(--forest)}
.oc-p{font-size:.83rem;color:var(--t-muted);line-height:1.75}

/* ═══════════════════════════════════════
   WHY SECTION
═══════════════════════════════════════ */
.why-section{position:relative;z-index:1;padding:4rem 1.2rem;max-width:1100px;margin:0 auto}
.why-grid{display:grid;grid-template-columns:1fr;gap:1rem}
.why-card{
  background:var(--glass);backdrop-filter:blur(14px);
  border:1px solid var(--glass-border);border-radius:var(--r);
  padding:1.6rem;box-shadow:var(--glass-shadow);
  transition:transform var(--t),box-shadow var(--t);
  position:relative;overflow:hidden;
}
.why-card::before{
  content:'';position:absolute;inset:0;border-radius:var(--r);
  background:linear-gradient(135deg,rgba(26,143,85,.04),transparent);
  opacity:0;transition:opacity var(--t);
}
.why-card:hover::before{opacity:1}
.why-card:hover{transform:translateY(-4px);box-shadow:var(--glass-shadow-lg)}
.why-card.featured{
  background:linear-gradient(135deg,rgba(26,143,85,.1),rgba(26,143,85,.04));
  border-color:var(--green-border2);
}
.why-card.gold-card{
  background:linear-gradient(135deg,rgba(200,136,42,.08),rgba(200,136,42,.02));
  border-color:var(--gold-border);
}
.wc-ico{
  width:42px;height:42px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;margin-bottom:.9rem;flex-shrink:0;
}
.wc-ico.g{background:var(--green-soft2);border:1px solid var(--green-border)}
.wc-ico.au{background:var(--gold-soft);border:1px solid var(--gold-border)}
.wc-ico.r{background:rgba(217,64,64,.08);border:1px solid rgba(217,64,64,.2)}
.wc-ico.b{background:rgba(30,80,200,.06);border:1px solid rgba(30,80,200,.15)}
.wc-title{font-family:var(--serif);font-size:1rem;font-weight:700;color:var(--forest);margin-bottom:.45rem}
.wc-title .gold{color:var(--gold)}
.wc-desc{font-size:.85rem;color:var(--t-muted);line-height:1.8}
.wc-badge{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.22rem .6rem;background:var(--gold-soft);border:1px solid var(--gold-border);
  border-radius:50px;font-size:.68rem;font-weight:600;color:var(--gold);margin-top:.7rem;
}

/* ═══════════════════════════════════════
   STAGES SECTION
═══════════════════════════════════════ */
.stages-section{position:relative;z-index:1;padding:4rem 0}
.stages-intro{max-width:1100px;margin:0 auto;padding:0 1.2rem}
.stages-scroll{
  overflow-x:auto;padding:1.5rem 1.2rem 2rem;
  scrollbar-width:thin;scrollbar-color:var(--green-border) transparent;
  -webkit-overflow-scrolling:touch;
}
.stages-track{
  display:flex;gap:0;min-width:max-content;
  padding:0 .5rem;position:relative;
}
.stages-track::before{
  content:'';position:absolute;top:36px;left:30px;right:30px;height:1.5px;
  background:linear-gradient(90deg,transparent,var(--green-border2) 8%,var(--green-border2) 92%,transparent);
}
.stage-item{
  display:flex;flex-direction:column;align-items:center;
  width:110px;gap:.55rem;cursor:pointer;
  transition:transform .2s var(--spring);
}
.stage-item:hover{transform:translateY(-6px)}
.stage-dot{
  width:66px;height:66px;border-radius:50%;
  background:var(--glass);backdrop-filter:blur(10px);
  border:1.5px solid var(--green-border);
  display:flex;align-items:center;justify-content:center;
  font-size:1.7rem;position:relative;z-index:1;
  transition:all .25s var(--spring);
  box-shadow:0 4px 14px rgba(13,38,24,.08);
}
.stage-item:hover .stage-dot,
.stage-item.active .stage-dot{
  border-color:var(--green2);background:var(--white);
  box-shadow:0 0 0 6px rgba(26,143,85,.08),0 4px 20px rgba(26,143,85,.22);
}
.stage-item.legendary .stage-dot{
  border-color:var(--gold2);
  box-shadow:0 0 0 6px rgba(200,136,42,.1),0 4px 20px rgba(200,136,42,.2);
}
.stage-name{font-size:.7rem;font-weight:600;color:var(--t-muted);text-align:center;line-height:1.3}
.stage-days{font-size:.62rem;color:var(--t-faint);text-align:center}
.stage-item.legendary .stage-name{color:var(--gold)}

/* ═══════════════════════════════════════
   HOW IT WORKS
═══════════════════════════════════════ */
.how-section{position:relative;z-index:1;padding:4rem 1.2rem;max-width:1100px;margin:0 auto}
.steps-grid{display:grid;grid-template-columns:1fr;gap:1.2rem;counter-reset:step}
.step-card{
  background:var(--glass);backdrop-filter:blur(14px);
  border:1px solid var(--glass-border);border-radius:var(--r);
  padding:1.7rem;position:relative;
  box-shadow:var(--glass-shadow);counter-increment:step;
  transition:transform var(--t),box-shadow var(--t);
}
.step-card:hover{transform:translateY(-4px);box-shadow:var(--glass-shadow-lg)}
.step-card::after{
  content:counter(step,'0' counter(step));
  position:absolute;top:1.3rem;right:1.3rem;
  font-family:var(--serif);font-size:2.5rem;font-weight:900;
  color:rgba(26,143,85,.07);line-height:1;
}
.sc-ico{
  width:44px;height:44px;border-radius:12px;
  background:var(--green-soft2);border:1px solid var(--green-border);
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;margin-bottom:.9rem;
}
.sc-title{font-family:var(--serif);font-size:1rem;font-weight:700;color:var(--forest);margin-bottom:.45rem}
.sc-desc{font-size:.85rem;color:var(--t-muted);line-height:1.8}

/* ═══════════════════════════════════════
   FEATURES STRIP
═══════════════════════════════════════ */
.features-strip{
  position:relative;z-index:1;
  padding:1.7rem 0;margin:1rem 0;
  overflow:hidden;
  border-top:1px solid var(--green-border);
  border-bottom:1px solid var(--green-border);
  background:linear-gradient(90deg,rgba(26,143,85,.03),rgba(26,143,85,.06),rgba(26,143,85,.03));
}
.strip-track{
  display:flex;gap:2.5rem;align-items:center;
  animation:stripScroll 30s linear infinite;
  width:max-content;
}
@keyframes stripScroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.strip-item{
  display:flex;align-items:center;gap:.55rem;
  font-size:.8rem;font-weight:500;color:var(--t-muted);white-space:nowrap;
}
.strip-item span{color:var(--green);font-size:1rem}
.strip-sep{color:var(--green-border2);font-size:.5rem}

/* ═══════════════════════════════════════
   FAQ
═══════════════════════════════════════ */
.faq-section{position:relative;z-index:1;padding:4rem 1.2rem;max-width:780px;margin:0 auto}
.faq-list{display:flex;flex-direction:column;gap:.65rem}
.faq-item{
  background:var(--glass);backdrop-filter:blur(14px);
  border:1px solid var(--glass-border);border-radius:var(--r);
  overflow:hidden;box-shadow:var(--glass-shadow);
  transition:border-color var(--t);
}
.faq-item.open{border-color:var(--green-border2)}
.faq-q{
  width:100%;padding:1.1rem 1.2rem;background:transparent;border:none;
  color:var(--forest);font-family:var(--sans);font-size:.9rem;font-weight:500;
  text-align:left;cursor:pointer;
  display:flex;align-items:center;justify-content:space-between;gap:1rem;
  transition:color var(--t);min-height:44px;
}
.faq-q:hover{color:var(--green)}
.faq-ico{
  width:28px;height:28px;border-radius:50%;flex-shrink:0;
  background:var(--green-soft);border:1px solid var(--green-border);
  display:flex;align-items:center;justify-content:center;
  color:var(--green);font-size:.9rem;font-weight:700;
  transition:all .2s var(--spring);
}
.faq-item.open .faq-ico{transform:rotate(45deg);background:var(--green);color:#fff;border-color:var(--green)}
.faq-body{max-height:0;overflow:hidden;transition:max-height .35s var(--ease)}
.faq-item.open .faq-body{max-height:500px}
.faq-body p{
  padding:.3rem 1.2rem 1.2rem;
  font-size:.86rem;color:var(--t-muted);line-height:1.85;
  border-top:1px solid var(--green-border);
}

/* ═══════════════════════════════════════
   CTA
═══════════════════════════════════════ */
.cta-section{position:relative;z-index:1;padding:4rem 1.2rem;max-width:780px;margin:0 auto}
.cta-box{
  background:linear-gradient(135deg,rgba(255,255,255,.95) 0%,rgba(234,244,229,.95) 100%);
  backdrop-filter:blur(20px);
  border:1px solid var(--green-border2);border-radius:20px;
  padding:3rem 1.5rem;text-align:center;
  box-shadow:0 24px 80px rgba(13,38,24,.12);
  position:relative;overflow:hidden;
}
.cta-box::before{
  content:'';position:absolute;top:-60px;right:-60px;
  width:180px;height:180px;border-radius:50%;
  background:radial-gradient(circle,rgba(26,143,85,.08) 0%,transparent 70%);
}
.cta-box::after{
  content:'';position:absolute;bottom:-50px;left:-50px;
  width:160px;height:160px;border-radius:50%;
  background:radial-gradient(circle,rgba(200,136,42,.06) 0%,transparent 70%);
}
.cta-box h2{
  font-family:var(--serif);font-size:clamp(1.6rem,5vw,2.6rem);
  font-weight:900;color:var(--forest);margin-bottom:.8rem;
}
.cta-box p{color:var(--t-muted);font-size:.93rem;margin-bottom:2rem;line-height:1.85}
.cta-btns{display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;position:relative;z-index:1}

/* ═══════════════════════════════════════
   FOOTER
═══════════════════════════════════════ */
footer{
  position:relative;z-index:1;
  border-top:1px solid var(--green-border);
  background:var(--forest);
  color:rgba(255,255,255,.7);
}
.footer-main{
  max-width:1100px;margin:0 auto;
  padding:3rem 1.2rem 2rem;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:2rem;
}
.footer-brand{grid-column:1 / -1}
.footer-brand-name{
  font-family:var(--serif);font-size:1.3rem;font-weight:900;
  color:#fff;margin-bottom:.6rem;
}
.footer-brand-name span{color:var(--green3)}
.footer-brand p{font-size:.82rem;line-height:1.85;max-width:280px;color:rgba(255,255,255,.5)}
.footer-creator{
  margin-top:1.1rem;padding:.75rem 1rem;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
  border-radius:var(--rs);font-size:.78rem;color:rgba(255,255,255,.6);
}
.footer-creator strong{color:rgba(255,255,255,.85)}
.footer-creator span{display:block;font-size:.72rem;margin-top:.15rem;color:rgba(255,255,255,.4)}
.footer-col h4{
  font-size:.74rem;font-weight:700;color:rgba(255,255,255,.9);
  text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;
}
.footer-col a{
  display:block;font-size:.82rem;color:rgba(255,255,255,.45);
  text-decoration:none;margin-bottom:.55rem;transition:color var(--t);
  min-height:28px;display:flex;align-items:center;
}
.footer-col a:hover{color:var(--green3)}
.footer-bottom{
  border-top:1px solid rgba(255,255,255,.08);
  padding:1.2rem 1.2rem;max-width:1100px;margin:0 auto;
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:.7rem;
}
.footer-bottom p{font-size:.75rem;color:rgba(255,255,255,.35)}
.footer-badge{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.3rem .75rem;
  background:rgba(26,143,85,.15);border:1px solid rgba(26,143,85,.3);
  border-radius:50px;font-size:.7rem;color:var(--green3);
}

/* ═══════════════════════════════════════
   SCROLL REVEAL
═══════════════════════════════════════ */
.reveal{opacity:0;transform:translateY(22px);transition:opacity .65s var(--ease),transform .65s var(--ease)}
.reveal.visible{opacity:1;transform:none}
.reveal-delay-1{transition-delay:.1s}
.reveal-delay-2{transition-delay:.2s}
.reveal-delay-3{transition-delay:.3s}

/* ═══════════════════════════════════════
   MODAL
═══════════════════════════════════════ */
.overlay{
  position:fixed;inset:0;z-index:400;
  background:rgba(13,38,24,.6);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  display:flex;align-items:flex-end;justify-content:center;padding:0;
  opacity:0;pointer-events:none;transition:opacity .25s var(--ease);
}
.overlay.on{opacity:1;pointer-events:all}
.modal{
  background:var(--white);
  border-radius:20px 20px 0 0;
  width:100%;max-width:100%;
  max-height:92vh;overflow-y:auto;
  padding:2rem 1.4rem;position:relative;
  transform:translateY(100%);
  transition:transform .3s var(--ease);
  box-shadow:0 -20px 60px rgba(13,38,24,.2);
  border:1px solid var(--green-border);
  border-bottom:none;
}
.overlay.on .modal{transform:translateY(0)}
/* drag handle indicator */
.modal::before{
  content:'';display:block;width:40px;height:4px;border-radius:2px;
  background:var(--green-border2);margin:0 auto 1.5rem;
}
.modal-x{
  position:absolute;top:1.5rem;right:1.2rem;
  width:32px;height:32px;border-radius:50%;
  background:var(--ivory2);border:1px solid var(--green-border);
  cursor:pointer;color:var(--t-muted);
  display:flex;align-items:center;justify-content:center;font-size:.85rem;
  transition:all var(--t);min-width:32px;min-height:32px;
}
.modal-x:hover{background:var(--green);color:#fff;border-color:var(--green)}
.modal-brand{display:flex;align-items:center;gap:.5rem;margin-bottom:1.6rem}
.modal-brand span{font-family:var(--serif);font-size:1.1rem;font-weight:900;color:var(--forest)}
.modal-brand span em{color:var(--green)}
.tabs{
  display:flex;background:var(--ivory2);border:1px solid var(--green-border);
  border-radius:12px;padding:3px;margin-bottom:1.6rem;
}
.tab{
  flex:1;padding:.6rem;border:none;border-radius:9px;
  background:transparent;color:var(--t-muted);
  font-family:var(--sans);font-size:.84rem;font-weight:600;
  cursor:pointer;transition:all .2s var(--ease);min-height:44px;
}
.tab.on{background:var(--green);color:#fff;box-shadow:0 2px 10px rgba(26,143,85,.3)}
.modal-h{font-family:var(--serif);font-size:1.35rem;font-weight:900;color:var(--forest);margin-bottom:.2rem}
.modal-sub{font-size:.83rem;color:var(--t-muted);margin-bottom:1.4rem;line-height:1.6}
.fg{margin-bottom:.95rem}
.lbl{display:block;font-size:.78rem;font-weight:600;color:var(--t-mid);margin-bottom:.35rem;letter-spacing:.02em}
.iw{position:relative}
.inp{
  width:100%;padding:.82rem 1rem;
  background:var(--ivory);border:1.5px solid var(--green-border);
  border-radius:var(--rs);color:var(--t-dark);
  font-family:var(--sans);font-size:1rem;
  transition:all var(--t);outline:none;
  min-height:44px;
}
.inp::placeholder{color:var(--t-faint)}
.inp:focus{border-color:var(--green);background:rgba(26,143,85,.04);box-shadow:0 0 0 3px rgba(26,143,85,.12)}
.inp.err{border-color:var(--err);box-shadow:0 0 0 3px var(--err-soft)}
.inp.has-eye{padding-right:2.8rem}
.eye-btn{
  position:absolute;right:.8rem;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--t-faint);
  display:flex;align-items:center;transition:color var(--t);
  min-width:28px;min-height:28px;justify-content:center;
}
.eye-btn:hover{color:var(--green)}
.eye-btn svg{width:18px;height:18px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.sbar{height:4px;background:var(--ivory3);border-radius:2px;margin-top:.4rem;overflow:hidden}
.sfill{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
.stxt{font-size:.72rem;color:var(--t-faint);margin-top:.2rem;height:1em}
.ferr{font-size:.75rem;color:var(--err);margin-top:.25rem;display:none}
.ferr.on{display:block}
.forgot{font-size:.79rem;color:var(--green);text-decoration:none;display:block;text-align:right;margin-top:.3rem}
.forgot:hover{color:var(--green2)}
.malert{padding:.75rem 1rem;border-radius:var(--rs);font-size:.83rem;margin-bottom:.9rem;display:none}
.malert.on{display:block}
.malert.e{background:var(--err-soft);border:1px solid rgba(217,64,64,.25);color:var(--err)}
.malert.s{background:var(--green-soft);border:1px solid var(--green-border);color:var(--green)}
.terms-check{
  display:flex;align-items:flex-start;gap:.6rem;margin-bottom:1rem;
  padding:.8rem;background:var(--ivory2);border:1px solid var(--green-border);
  border-radius:var(--rs);cursor:pointer;
}
.terms-check input[type=checkbox]{width:20px;height:20px;accent-color:var(--green);cursor:pointer;flex-shrink:0;margin-top:1px}
.terms-check-text{font-size:.8rem;color:var(--t-muted);line-height:1.55}
.terms-check-text a{color:var(--green);text-decoration:underline;text-underline-offset:2px}
.terms-check-text a:hover{color:var(--green2)}
.auth-v.off{display:none}
.reset-v{display:none}
.reset-v.on{display:block}

/* ═══════════════════════════════════════
   LEGAL MODAL
═══════════════════════════════════════ */
.legal-modal{max-width:100%}
.legal-body{font-size:.85rem;color:var(--t-muted);line-height:1.85;display:flex;flex-direction:column;gap:1rem;margin-top:.9rem}
.legal-body h3{font-family:var(--serif);font-size:1rem;font-weight:700;color:var(--forest);margin-bottom:.2rem}
.legal-body p{margin:0}
.legal-badge{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.3rem .75rem;background:var(--green-soft);border:1px solid var(--green-border);
  border-radius:50px;font-size:.72rem;color:var(--green);font-weight:600;
  margin-bottom:1rem;
}

/* ═══════════════════════════════════════
   ANIMATIONS
═══════════════════════════════════════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

/* ─────────────────────────────────────────
   TABLET  ≥ 640px
───────────────────────────────────────── */
@media(min-width:640px){
  nav{padding:.9rem 1.8rem}
  .nav-r .btn-ghost{display:inline-flex}

  .hero{padding:7rem 2rem 4rem}

  .hero-stats{
    display:flex;
    grid-template-columns:unset;
  }
  .hero-stat{
    flex:1;
    border-right:1px solid var(--green-border);
    border-bottom:none;
  }
  .hero-stat:last-child{border-right:none}

  .plant-scene{width:360px;height:520px}
  .streak-badge{right:-20px;min-width:150px}
  .xp-badge{left:-10px;min-width:148px}

  .origin-section{padding:5rem 2rem}
  .why-section{padding:4.5rem 2rem}
  .how-section{padding:4.5rem 2rem}
  .faq-section{padding:4.5rem 2rem}
  .cta-section{padding:4.5rem 2rem}
  .stages-intro{padding:0 2rem}
  .stages-scroll{padding:1.5rem 2rem 2rem}

  .why-grid{grid-template-columns:repeat(2,1fr)}
  .steps-grid{grid-template-columns:repeat(2,1fr)}

  .origin-cards{grid-template-columns:1fr}

  .footer-main{padding:3.5rem 2rem 2.5rem}

  .overlay{align-items:center;padding:1.5rem}
  .modal{
    border-radius:20px;border-bottom:1px solid var(--green-border);
    max-width:440px;
    transform:translateY(12px) scale(.98);
  }
  .modal::before{display:none}
  .overlay.on .modal{transform:none}
}

/* ─────────────────────────────────────────
   TABLET LANDSCAPE / SMALL DESKTOP  ≥ 900px
───────────────────────────────────────── */
@media(min-width:900px){
  nav{padding:1.1rem 2.5rem}
  .nav-links{display:flex;gap:2rem;list-style:none}
  .nav-links a{
    font-size:.86rem;font-weight:500;color:var(--t-muted);
    text-decoration:none;transition:color var(--t);position:relative;
  }
  .nav-links a::after{
    content:'';position:absolute;bottom:-3px;left:0;right:100%;
    height:1.5px;background:var(--green);transition:right .2s var(--ease);
  }
  .nav-links a:hover{color:var(--forest)}
  .nav-links a:hover::after{right:0}

  .hero{
    flex-direction:row;text-align:left;
    padding:8rem 2.5rem 5rem;gap:4rem;min-height:100vh;
  }
  .hero-left{flex:1;max-width:560px}
  .hero-desc,.hero-story{max-width:480px}
  .hero-ctas{justify-content:flex-start}
  .hero-stats{margin:0}
  .hero-right{flex:0 0 420px}
  .plant-scene{width:400px;height:580px}
  .streak-badge{
    top:80px;
    right:-140px;
  }
  .xp-badge{
    bottom:70px;
    left:-20px;
  }

  .origin-section{padding:6rem 2.5rem}
  .origin-inner{flex-direction:row;gap:4rem;align-items:center}
  .origin-cards{grid-template-columns:1fr}

  .why-section{padding:5rem 2.5rem}
  .steps-grid{grid-template-columns:repeat(3,1fr)}
  .how-section{padding:5rem 2.5rem}
  .faq-section{padding:5rem 2.5rem}
  .cta-section{padding:5rem 2.5rem}
  .stages-intro{padding:0 2.5rem}
  .stages-scroll{padding:1.5rem 2.5rem 2rem}

  .footer-main{
    grid-template-columns:2.2fr repeat(4,1fr);
    padding:4rem 2.5rem 3rem;
  }
  .footer-brand{grid-column:auto}
  .footer-brand p{max-width:220px}
}

/* ─────────────────────────────────────────
   DESKTOP  ≥ 1200px
───────────────────────────────────────── */
@media(min-width:1200px){
  .hero{gap:5rem}
  .hero-right{flex:0 0 460px}
  .plant-scene{width:420px;height:610px}
  .streak-badge{right:-160px}
  .xp-badge{left:-20px}
  .cta-box{padding:4.5rem 3.5rem}
}

/* ═══════════════════════════════════════
   SCROLLBAR
═══════════════════════════════════════ */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--green-border2);border-radius:4px}
::-webkit-scrollbar-thumb:hover{background:var(--green)}
</style>
</head>
<body>

<div class="bg-mesh"></div>
<div class="bg-dots"></div>

<!-- ═══════════════ NAV ═══════════════ -->
<nav id="nav">
  <a href="#" class="logo">
  <div class="logo-icon" style="width:32px;height:32px;border-radius:8px;overflow:hidden;padding:0;background:transparent;flex-shrink:0">
    <img src="/florescer/public/img/logo.png" alt="florescer" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:8px"/>
  </div>
  <span class="logo-name">Flores<span>cer</span></span>
</a>
  <ul class="nav-links">
    <li><a href="#origem">Nossa história</a></li>
    <li><a href="#por-que">Por que</a></li>
    <li><a href="#estagios">Estágios</a></li>
    <li><a href="#como-funciona">Como funciona</a></li>
    <li><a href="#faq">FAQ</a></li>
  </ul>
  <div class="nav-r">
    <button class="btn btn-ghost" onclick="openModal('login')">Entrar</button>
    <button class="btn btn-green" onclick="openModal('register')">Começar grátis</button>
  </div>
</nav>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero" id="hero">
  <div class="hero-left">
    <div class="hero-pill">
      <div class="hero-pill-dot">🌱</div>
      Plataforma de estudos gratuita · Sem anúncios
    </div>

    <h1>Plante o hábito.<br>Colha o <em>conhecimento.</em><br>Floresça <span class="gold"> de verdade.</span></h1>

    <div class="hero-story">
      O <strong>Florescer</strong> nasceu de uma frustração real apps de estudo que celebram qualquer coisa e ignoram quando você para. Aqui é diferente: uma planta cresce com cada dia de dedicação, e <span class="gold"><strong>murcha de verdade se você largar</strong></span>. Honesto. Visual. Viciante do jeito certo.
    </div>

    <p class="hero-desc">Streak diário, timer Pomodoro, aulas do YouTube integradas, notas por matéria e uma planta que evolui por 9 estágios de Semente até Árvore Lendária.</p>

    <div class="hero-ctas">
      <button class="btn btn-gold btn-lg" onclick="openModal('register')">🌱 Criar conta grátis</button>
      <button class="btn btn-ghost btn-lg" onclick="openModal('login')">Já tenho conta</button>
    </div>

    <div class="hero-stats">
      <div class="hero-stat">
        <span class="stat-val">9</span>
        <span class="stat-lbl">Estágios</span>
      </div>
      <div class="hero-stat">
        <span class="stat-val">3</span>
        <span class="stat-lbl">Chances</span>
      </div>
      <div class="hero-stat">
        <span class="stat-val stat-gold">300</span>
        <span class="stat-lbl">Dias streak</span>
      </div>
      <div class="hero-stat">
        <span class="stat-val">0</span>
        <span class="stat-lbl">Anúncios</span>
      </div>
    </div>
  </div>

  <div class="hero-right">
    <div class="plant-scene">
      <div class="plant-aura"></div>

      <!-- Stage selector -->
      <div class="stage-selector">
        <button class="s-btn" onclick="prevStage()">‹</button>
        <strong id="sName">Planta Jovem</strong>
        <span style="color:var(--t-faint);font-size:.65rem">·</span>
        <span id="sNum" style="color:var(--green);font-weight:600">3/9</span>
        <button class="s-btn" onclick="nextStage()">›</button>
      </div>

      <!-- Streak badge -->
      <div class="streak-badge glass-lg">
        <div class="streak-top">
          <span class="fire-ico">🌱</span>
          <div>
            <div class="streak-num" id="sStreak">42</div>
            <div class="streak-sub">dias seguidos</div>
          </div>
        </div>
        <div class="streak-divider"></div>
        <div class="drops-row">
          <span class="drops-label">Regar:</span>
          <div id="dropsEl" style="display:flex;gap:.25rem"></div>
        </div>
      </div>

      <!-- XP badge -->
      <div class="xp-badge">
        <div class="xp-head">
          <span class="xp-label">XP · Nível 7</span>
          <span class="xp-val">72%</span>
        </div>
        <div class="xp-track"><div class="xp-fill"></div></div>
        <div class="xp-stage">🌿 Planta Jovem → Planta Forte</div>
      </div>

      <!-- Plant rendered here -->
      <div id="plantWrap"></div>
    </div>
  </div>
</section>

<!-- ═══════════════ FEATURES STRIP ═══════════════ -->
<div class="features-strip">
  <div class="strip-track" id="stripTrack">
    <?php
    $items = [
      ['🌱','Planta que cresce de verdade'],['🔥','Streak diário'],['⏱️','Timer Pomodoro'],
      ['📺','YouTube integrado'],['📊','Notas por matéria'],['🏆','9 estágios'],
      ['💧','Sistema de 3 gotas'],['🎯','Meta personalizável'],['🔒','100% gratuito'],
      ['🚫','Zero anúncios'],['📝','Anotações integradas'],['✨','XP e levels'],
    ];
    $html = '';
    foreach($items as $i=>[$ico,$txt]){
      $html .= "<div class='strip-item'><span>$ico</span> $txt</div>";
      if($i < count($items)-1) $html .= "<div class='strip-sep'>●</div>";
    }
    echo $html.$html; // duplicate for seamless loop
    ?>
  </div>
</div>

<!-- ═══════════════ ORIGEM ═══════════════ -->
<section class="origin-section" id="origem">
  <div class="origin-inner">
    <div class="origin-text reveal">
      <div class="origin-eyebrow">✦ Nossa história</div>
      <h2 class="origin-h">Nasceu de um <em>caderno vazio</em> e uma meta não cumprida.</h2>
      <p class="origin-p">
        Em 2026, <strong>Eu, Guilherme Silva</strong> estudante de engenharia de software estava lutando com a consistência nos estudos. Usei apps durante meses e entendei o poder de uma planta que realmente murcha. Queria algo assim, mas para <strong>qualquer coisa que estudasse</strong>: algoritmos, inglês, física, o que fosse.
      </p>
      <p class="origin-p">
        E não encontrei. Então decidi construir. O <strong>Florescer</strong>  e é esse projeto: uma plataforma que torna o hábito de estudar <strong>visível, honesto e viciante</strong> sem gamificação superficial, sem pontos que não significam nada. Se você para, sua planta murcha. Se você persiste, ela vira uma <strong>Árvore Lendária!</strong>
      </p>
      <p class="origin-p">
        Hoje está disponível gratuitamente para qualquer estudante. Sem planos pagos. Sem anúncios. Sem truques.
      </p>
      <div class="origin-quote">
        "Eu queria sentir que o esforço de hoje se tornasse visível de alguma forma. Uma planta que cresce representa exatamente isso: cada dia de estudo se transforma em algo concreto, algo que você passa a valorizar e não quer perder."
        <cite>Guilherme Silva, criador do Florescer</cite>
      </div>
    </div>
    <div class="origin-cards">
      <div class="origin-card reveal reveal-delay-1">
        <div class="oc-top">
          <div class="oc-ico green">🎯</div>
          <div class="oc-title">Missão</div>
        </div>
        <p class="oc-p">Tornar o hábito de estudar concreto, visual e honesto. Cada dia de esforço deve aparecer de alguma forma e cada dia de preguiça também.</p>
      </div>
      <div class="origin-card reveal reveal-delay-2">
        <div class="oc-top">
          <div class="oc-ico gold">💡</div>
          <div class="oc-title">Filosofia</div>
        </div>
        <p class="oc-p">Sem pontuações vazias. Sem comemorações automáticas. O progresso é real porque o custo da pausa também é real. A planta morre. O streak zera. Começa de novo.</p>
      </div>
      <div class="origin-card reveal reveal-delay-3">
        <div class="oc-top">
          <div class="oc-ico green">🌍</div>
          <div class="oc-title">Compromisso</div>
        </div>
        <p class="oc-p">Gratuito para sempre. Sem anúncios, sem rastreamento de terceiros, sem planos premium escondidos. O Florescer é e sempre será 100% livre para estudantes.</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════ POR QUE ═══════════════ -->
<section class="why-section" id="por-que">
  <p class="eyebrow reveal">Por que o Florescer</p>
  <h2 class="sec-h reveal">Feito para quem estuda <em>de verdade.</em></h2>
  <p class="sec-p reveal">Não é mais um app de tarefas. É um sistema que torna o hábito de estudar concreto, visual e se preciso doloroso de largar.</p>

  <div class="why-grid">
    <div class="why-card featured reveal">
      <div class="wc-ico r">🥀</div>
      <div class="wc-title">Sua planta morre se você parar</div>
      <p class="wc-desc">Diferente de apps que ignoram ausência, aqui você tem 3 gotas de regar. Não bateu a meta? Perde uma gota. Esgotou as 3? A semente morre, o streak zera. Simples, honesto e eficaz.</p>
    </div>
    <div class="why-card featured reveal">
      <div class="wc-ico au">🎯</div>
      <div class="wc-title">Meta diária que você define</div>
      <p class="wc-desc">30 minutos, 45, 90 você escolhe. O sistema registra tudo e exibe seu progresso em tempo real, sem julgamentos. Pequeno ou grande, o que importa é a consistência.</p>
    </div>
    <div class="why-card featured reveal">
      <div class="wc-ico b">📺</div>
      <div class="wc-title">Aulas do YouTube com anotações</div>
      <p class="wc-desc">Cole o link de qualquer vídeo do YouTube e assista com o vídeo e suas anotações lado a lado, no mesmo ambiente. O tempo assistido conta para a meta. Sem precisar abrir várias abas.</p>
    </div>
    <div class="why-card featured reveal">
      <div class="wc-ico g">📊</div>
      <div class="wc-title">Desempenho por matéria</div>
      <p class="wc-desc">Registre notas, acompanhe evolução por disciplina e visualize onde precisa de mais atenção. Histórico completo de estudo com gráficos de progresso.</p>
    </div>
    <div class="why-card featured reveal">
      <div class="wc-ico g">⏱️</div>
      <div class="wc-title">Timer Pomodoro integrado</div>
      <p class="wc-desc">Estude em blocos de foco com pausas programadas. O Pomodoro já está dentro da plataforma sem precisar de app separado. Cada ciclo conta para a sua meta diária.</p>
    </div>
    <!-- why-card gold-card reveal gold-->
    <div class="why-card featured reveal">
      <div class="wc-ico au">✨</div>
      <div class="wc-title">XP e levels reais</div>
      <p class="wc-desc">Cada minuto de estudo gera XP. Suba de nível de Semente até Lendário com 9 níveis de evolução. Não é só cosmético cada level representa horas reais investidas no seu desenvolvimento.</p>
    </div>
  </div>
</section>

<!-- ═══════════════ ESTÁGIOS ═══════════════ -->
<section class="stages-section" id="estagios">
  <div class="stages-intro">
    <p class="eyebrow reveal">Evolução visual</p>
    <h2 class="sec-h reveal">9 estágios de <em>crescimento</em></h2>
    <p class="sec-p reveal">Cada dia de meta cumprida rega sua planta e a faz crescer visivelmente. Chegue à Árvore Lendária com 300 dias consecutivos.</p>
  </div>
  <div class="stages-scroll reveal">
    <div class="stages-track">
      <?php
      $stagesData = [
        ['🌱','Semente','Dia 1',false],
        ['🌿','Broto Inicial','7 dias',false],
        ['☘️','Planta Jovem','15 dias',false],
        ['🌲','Planta Forte','30 dias',false],
        ['🌳','Árvore Crescendo','60 dias',false],
        ['🌴','Árvore Robusta','100 dias',false],
        ['🎋','Árvore Antiga','150 dias',false],
        ['✨','Árvore Gigante','200 dias',false],
        ['🏆','Árvore Lendária','300 dias',true],
      ];
      foreach($stagesData as $i=>[$ico,$name,$days,$leg]):
      $cls = 'stage-item'.($leg?' legendary':'').($i===2?' active':'');
      ?>
      <div class="<?= $cls ?>" data-i="<?= $i ?>" onclick="setStage(<?= $i ?>)">
        <div class="stage-dot"><?= $ico ?></div>
        <div class="stage-name"><?= $name ?></div>
        <div class="stage-days"><?= $days ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════ COMO FUNCIONA ═══════════════ -->
<section class="how-section" id="como-funciona">
  <p class="eyebrow reveal">Como funciona</p>
  <h2 class="sec-h reveal">Simples. Consistente. <em>Eficaz.</em></h2>
  <p class="sec-p reveal">Três passos para transformar seu estudo num hábito que você consegue ver crescer todo dia.</p>

  <div class="steps-grid">
    <div class="step-card reveal">
      <div class="sc-ico">🎯</div>
      <div class="sc-title">Defina sua meta diária</div>
      <p class="sc-desc">Crie sua conta e escolha quantos minutos por dia você quer estudar. Pode começar pequeno 30 minutos já contam. O importante é a consistência, não a quantidade.</p>
    </div>
    <div class="step-card reveal reveal-delay-1">
      <div class="sc-ico">⏱️</div>
      <div class="sc-title">Estude e registre</div>
      <p class="sc-desc">Use o timer Pomodoro, assista aulas do YouTube integradas ou marque aulas concluídas manualmente. O sistema contabiliza tudo automaticamente e atualiza seu progresso em tempo real.</p>
    </div>
    <div class="step-card reveal reveal-delay-2">
      <div class="sc-ico">🌱</div>
      <div class="sc-title">Veja sua planta crescer</div>
      <p class="sc-desc">Cada dia de meta cumprida rega sua semente e ela cresce visivelmente. Construa o streak e acompanhe a planta evoluir pelos 9 estágios de uma sementinha até uma Árvore Lendária imponente.</p>
    </div>
  </div>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="faq-section" id="faq">
  <p class="eyebrow reveal">Perguntas frequentes</p>
  <h2 class="sec-h reveal">Ficou com <em>dúvidas?</em></h2>
  <p class="sec-p reveal">Tudo que você precisa saber sobre o Florescer antes de começar.</p>

  <div class="faq-list">
    <?php
    $faqs = [
      [
        'O Florescer é realmente gratuito? Para sempre?',
        'Sim. O Florescer é totalmente gratuito para usar, sem anúncios, sem distrações e sem cobranças pelas funcionalidades principais.
        Foi criado por um estudante, para estudantes. com o objetivo de tornar o hábito de estudar mais visível, honesto e consistente.
      
        No futuro, podem existir recursos avançados opcionais (como relatórios inteligentes), mas a experiência essencial sempre será gratuita. O que já existe hoje continuará acessível sem custo. O compromisso é que o Florescer seja uma ferramenta de crescimento acessível a todos, sem barreiras financeiras.'
      ],
      [
        'O que acontece exatamente se eu não bater a meta?',
        'Você tem 3 gotas de regar disponíveis. Cada dia que a meta não é atingida, você perde uma gota. Enquanto ainda tiver gotas restantes, sua planta continua viva mas visualmente murcha um pouco. Quando as 3 gotas acabam sem que você bata a meta, sua semente morre e o streak volta a zero. Você pode ganhar uma nova gota ao completar a meta por 7 dias consecutivos.'
      ],
      [
        'Como funciona o YouTube dentro do Florescer?',
        'Na seção de aulas, você cola o link de qualquer vídeo público do YouTube. O vídeo abre dentro da plataforma num player integrado, com um bloco de anotações ao lado. O tempo em que o vídeo está sendo assistido é monitorado e somado automaticamente à sua meta do dia. Não é necessário alternar entre abas tudo acontece num único ambiente.'
      ],
      [
        'Posso mudar minha meta diária depois que definir?',
        'Sim, a qualquer momento. Nas configurações do perfil você pode ajustar quantos minutos por dia quer estudar. Aumentar ou diminuir a meta não afeta seu streak atual mas a mudança entra em vigor no dia seguinte para manter a integridade do registro.'
      ],
      [
        'Como funciona o sistema de XP e levels?',
        'Cada minuto estudado gera 1 XP. Conforme você acumula XP, sobe de level começando em Semente e podendo chegar até Lendário após centenas de horas de estudo. Os levels são permanentes e não resetam quando a semente morre; representam seu histórico total de dedicação na plataforma.'
      ],
      [
        'Meus dados ficam seguros? Quais dados vocês coletam?',
        'Coletamos apenas o mínimo necessário para o funcionamento: nome, e-mail, senha (criptografada com hash bcrypt), e seus dados de uso dentro da plataforma (histórico de estudo, anotações, progresso). Nada disso é compartilhado com terceiros. Não usamos Google Analytics, Facebook Pixel ou qualquer ferramenta de rastreamento externo. Você pode solicitar a exclusão completa da sua conta e dados a qualquer momento.'
      ],
      [
        'O Florescer funciona no celular?',
        'Sim. O layout foi desenvolvido com design responsivo e funciona em qualquer dispositivo celular, tablet ou desktop, mas para melhor experiência use no desktop. Não há app nativo por enquanto, mas o site funciona perfeitamente no navegador mobile e pode ser adicionado à tela inicial como um PWA.'
      ],
      [
        'Posso usar o Florescer para qualquer tipo de estudo?',
        'Absolutamente. Não importa se é para faculdade, concurso público, idiomas, programação, música ou qualquer outra área. O sistema é agnóstico ao conteúdo o que importa é o tempo que você dedica. Você pode criar matérias personalizadas para organizar seu progresso da forma que fizer mais sentido para você.'
      ],
    ];
    foreach($faqs as [$q,$a]):
    ?>
    <div class="faq-item reveal">
      <button class="faq-q" onclick="toggleFaq(this)">
        <span><?= htmlspecialchars($q) ?></span>
        <span class="faq-ico">+</span>
      </button>
      <div class="faq-body"><p><?= htmlspecialchars($a) ?></p></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══════════════ CTA ═══════════════ -->
<section class="cta-section">
  <div class="cta-box reveal">
    <h2>Pronto para <em style="font-family:var(--serif);font-style:italic;color:var(--green);font-weight:400">florescer?</em></h2>
    <p>Plante o hábito hoje. A versão sua daqui a 300 dias vai olhar para essa árvore lendária e agradecer cada dia que não desistiu.</p>
    <div class="cta-btns">
      <button class="btn btn-gold btn-lg" onclick="openModal('register')">🌱 Criar conta grátis</button>
      <button class="btn btn-ghost btn-lg" onclick="openModal('login')">Já tenho conta</button>
    </div>
  </div>
</section>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer>
  <div class="footer-main">
    <div class="footer-brand">
      <div class="footer-brand-name">🌱 Flores<span>cer</span></div>
      <p>Transforme seu estudo em hábito. Uma planta que cresce com seu conhecimento e murcha se você parar.</p>
      <div class="footer-creator">
        🛠️ Criado por <strong>Guilherme Silva</strong>
        <span>Com dedicação para estudantes que querem crescer de verdade. · 2024</span>
      </div>
    </div>
    <div class="footer-col">
      <h4>Plataforma</h4>
      <a href="#" onclick="openModal('register');return false">Criar conta</a>
      <a href="#" onclick="openModal('login');return false">Entrar</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="#estagios">Estágios da planta</a>
      <a href="#faq">FAQ</a>
    </div>
    <div class="footer-col">
      <h4>Sobre</h4>
      <a href="#origem">Nossa história</a>
      <a href="#por-que">Por que o Florescer</a>
      <a href="#" onclick="openModal('register');return false">Começar agora</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="#" onclick="openLegal('terms');return false">Termos de uso</a>
      <a href="#" onclick="openLegal('privacy');return false">Política de privacidade</a>
      <a href="#" onclick="openLegal('cookies');return false">Política de cookies</a>
    </div>

    <div class="footer-col">
      <h4>contato</h4>
      <a href="https://www.instagram.com/florescerapp?igsh=MWU2amZmYzNjeXRtdA%3D%3D&utm_source=qr" target="_blank">Instagram</a>
      <a href="mailto:florescer.appcontato@gmail.com">florescer.appcontato@gmail.com</a>
      <a href="#contato">Telefone</a>
    </div>

  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> Florescer. Feito com 🌱 para quem quer crescer de verdade.</p>
    <div class="footer-badge">🔒 Sem anúncios · Sem rastreamento · 100% grátis</div>
  </div>
</footer>

<!-- ═══════════════ MODAL AUTH ═══════════════ -->
<div class="overlay" id="overlay">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Autenticação">
    <button class="modal-x" onclick="closeModal()" aria-label="Fechar">✕</button>

    <div class="modal-brand">
      <div class="logo-icon" style="width:30px;height:30px;border-radius:8px">
        <svg width="16" height="16" viewBox="0 0 34 34" fill="none">
          <rect x="15.5" y="8" width="3" height="16" rx="1.5" fill="white"/>
          <ellipse cx="10" cy="15" rx="6" ry="3" transform="rotate(-30 10 15)" fill="white" opacity=".85"/>
          <ellipse cx="24" cy="13" rx="6" ry="3" transform="rotate(30 24 13)" fill="white" opacity=".7"/>
        </svg>
      </div>
      <span>Flores<em>cer</em></span>
    </div>

    <div class="auth-v" id="authV">
      <div class="tabs">
        <button class="tab on" id="t1" onclick="switchTab('login')">Entrar</button>
        <button class="tab" id="t2" onclick="switchTab('register')">Criar conta</button>
      </div>
      <div class="malert" id="authAlert"></div>

      <!-- LOGIN -->
      <form id="fLogin" novalidate>
        <div class="modal-h">Bem-vindo de volta</div>
        <p class="modal-sub">Continue sua jornada de crescimento.</p>
        <div class="fg">
          <label class="lbl" for="lEmail">E-mail</label>
          <input class="inp" type="email" id="lEmail" placeholder="seu@email.com" autocomplete="email"/>
          <span class="ferr" id="lEmailE">Por favor, insira um e-mail válido.</span>
        </div>
        <div class="fg">
          <label class="lbl" for="lPass">Senha</label>
          <div class="iw">
            <input class="inp has-eye" type="password" id="lPass" placeholder="••••••••" autocomplete="current-password"/>
            <button type="button" class="eye-btn" onclick="toggleEye('lPass',this)" aria-label="Mostrar senha">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
                </svg> 
            </button>
          </div>
          <a href="#" class="forgot" onclick="showReset();return false">Esqueci minha senha</a>
          <span class="ferr" id="lPassE">Informe sua senha.</span>
        </div>
        <button type="submit" class="btn btn-green btn-full">Entrar na conta</button>
      </form>

      <!-- REGISTER -->
      <form id="fReg" novalidate style="display:none">
        <div class="modal-h">Plante sua semente 🌱</div>
        <p class="modal-sub">Crie sua conta gratuita e comece a florescer hoje.</p>
        <div class="fg">
          <label class="lbl" for="rName">Nome</label>
          <input class="inp" type="text" id="rName" placeholder="Seu nome" autocomplete="name"/>
          <span class="ferr" id="rNameE">Informe seu nome.</span>
        </div>
        <div class="fg">
          <label class="lbl" for="rEmail">E-mail</label>
          <input class="inp" type="email" id="rEmail" placeholder="seu@email.com" autocomplete="email" oninput="checkEmail(this)"/>
          <span class="ferr" id="rEmailLive" style="display:none;color:var(--err);font-size:.74rem"></span>
          <span class="ferr" id="rEmailE">Por favor, insira um e-mail válido.</span>
        </div>
        <div class="fg">
          <label class="lbl" for="rPass">Senha</label>
          <div class="iw">
            <input class="inp has-eye" type="password" id="rPass" placeholder="Mínimo 6 caracteres" autocomplete="new-password" oninput="checkStr(this.value)"/>
           <button type="button" class="eye-btn" onclick="toggleEye('lPass',this)" aria-label="Mostrar senha">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
                </svg> 
            </button>
          </div>
          <div class="sbar"><div class="sfill" id="sFill"></div></div>
          <div class="stxt" id="sTxt"></div>
          <span class="ferr" id="rPassE">Senha fraca. Use ao menos 6 caracteres, um número e um símbolo.</span>
        </div>
        <div class="fg">
          <label class="lbl" for="rConf">Confirmar senha</label>
          <div class="iw">
            <input class="inp has-eye" type="password" id="rConf" placeholder="Repita a senha" autocomplete="new-password"/>
           <button type="button" class="eye-btn" onclick="toggleEye('lPass',this)" aria-label="Mostrar senha">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
                </svg> 
            </button>
          </div>
          <span class="ferr" id="rConfE">As senhas não coincidem.</span>
        </div>
        <!-- Termos -->
        <label class="terms-check">
          <input type="checkbox" id="termsCheck" onchange="checkTerms()"/>
          <span class="terms-check-text">
            Li e concordo com os <a href="#" onclick="openLegal('terms');return false">Termos de Uso</a> e a <a href="#" onclick="openLegal('privacy');return false">Política de Privacidade</a> do Florescer.
          </span>
        </label>
        <span class="ferr" id="termsE">Você precisa aceitar os termos para criar uma conta.</span>
        <button type="submit" class="btn btn-green btn-full" id="regBtn">Criar minha conta</button>
      </form>
    </div>

    <!-- RESET VIEW -->
    <div class="reset-v" id="resetV">
      <div id="rs1">
        <div class="modal-h">Recuperar senha</div>
        <p class="modal-sub">Informe seu e-mail para receber um código de recuperação.</p>
        <div class="malert" id="rAlert1"></div>
        <form id="fR1" novalidate>
          <div class="fg">
            <label class="lbl" for="rEmail1">E-mail da conta</label>
            <input class="inp" type="email" id="rEmail1" placeholder="seu@email.com"/>
            <span class="ferr" id="rEmail1E">E-mail inválido.</span>
          </div>
          <button type="submit" class="btn btn-green btn-full">Enviar código de recuperação</button>
        </form>
        <button class="btn btn-ghost btn-full" style="margin-top:.7rem" onclick="showAuth()">← Voltar ao login</button>
      </div>
      <div id="rs2" style="display:none">
        <div class="modal-h">Insira o código</div>
        <p class="modal-sub">Enviamos um código de 6 dígitos para seu e-mail. Ele expira em 10 minutos.</p>
        <div class="malert" id="rAlert2"></div>
        <form id="fR2" novalidate>
          <div class="fg">
            <label class="lbl" for="rCode">Código de recuperação</label>
            <input class="inp" type="text" id="rCode" placeholder="000000" maxlength="6" inputmode="numeric" style="letter-spacing:.4em;font-size:1.3rem;text-align:center"/>
            <span class="ferr" id="rCodeE">Código inválido. Deve ter 6 dígitos.</span>
          </div>
          <div class="fg">
            <label class="lbl" for="nPass">Nova senha</label>
            <div class="iw">
              <input class="inp has-eye" type="password" id="nPass" placeholder="Mínimo 6 caracteres"/>
              <button type="button" class="eye-btn" onclick="toggleEye('nPass',this)"><svg><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
            <span class="ferr" id="nPassE">Senha fraca ou inválida.</span>
          </div>
          <div class="fg">
            <label class="lbl" for="nConf">Confirmar nova senha</label>
            <div class="iw">
              <input class="inp has-eye" type="password" id="nConf" placeholder="Repita a nova senha"/>
              <button type="button" class="eye-btn" onclick="toggleEye('nConf',this)"><svg><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
            <span class="ferr" id="nConfE">As senhas não coincidem.</span>
          </div>
          <button type="submit" class="btn btn-green btn-full">Redefinir senha</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ MODAL LEGAL ═══════════════ -->
<div class="overlay" id="legalOv">
  <div class="modal legal-modal" role="dialog" aria-modal="true">
    <button class="modal-x" onclick="closeLegal()" aria-label="Fechar">✕</button>
    <div id="legalContent"></div>
  </div>
</div>

<script>
/* ═══════════════ PLANT SVG STAGES ═══════════════ */
const stageData=[
  {name:'Semente',num:'1/9',streak:0,drops:0},
  {name:'Broto Inicial',num:'2/9',streak:7,drops:1},
  {name:'Planta Jovem',num:'3/9',streak:15,drops:2},
  {name:'Planta Forte',num:'4/9',streak:30,drops:2},
  {name:'Árvore Crescendo',num:'5/9',streak:60,drops:3},
  {name:'Árvore Robusta',num:'6/9',streak:100,drops:3},
  {name:'Árvore Antiga',num:'7/9',streak:150,drops:3},
  {name:'Árvore Gigante',num:'8/9',streak:200,drops:3},
  {name:'Árvore Lendária',num:'9/9',streak:300,drops:3},
];
let currentStage=2;

function plantSVG(s){
  const G=(id,stops)=>`<linearGradient id="${id}" x1="0" y1="1" x2="0" y2="0" gradientUnits="objectBoundingBox">${stops}</linearGradient>`;
  const trunk=(w,h,x,y)=>`<path d="M${x} ${y} C${x} ${y-h*.12} ${x-w*.15} ${y-h*.4} ${x} ${y-h*.6} C${x+w*.15} ${y-h*.8} ${x-w*.1} ${y-h*.9} ${x} ${y-h}" stroke="url(#trunk)" stroke-width="${w}" stroke-linecap="round" fill="none"/>`;
  const leaf=(cx,cy,rx,ry,rot,c,op)=>`<ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" transform="rotate(${rot} ${cx} ${cy})" fill="${c}" opacity="${op}"/>`;
  const vein=(x1,y1,x2,y2)=>`<path d="M${x1} ${y1} Q${(x1+x2)/2} ${(y1+y2)/2-8} ${x2} ${y2}" stroke="rgba(255,255,255,.12)" stroke-width="1" fill="none"/>`;
  const branch=(x1,y1,x2,y2,w)=>`<path d="M${x1} ${y1} Q${x1+(x2-x1)*.4} ${(y1+y2)/2} ${x2} ${y2}" stroke="#1a5c34" stroke-width="${w}" stroke-linecap="round" fill="none"/>`;

  if(s===0){
    return `<svg width="90" height="110" viewBox="0 0 90 110"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2dd48a"/><stop offset="100%" stop-color="#0a4428"/></linearGradient></defs>
      <ellipse cx="45" cy="96" rx="28" ry="8" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="45" cy="90" rx="18" ry="9" fill="#c8e0c0"/>
      <path d="M45 90 C45 80 43 68 45 58" stroke="url(#trunk)" stroke-width="3.5" stroke-linecap="round" fill="none"/>
      ${leaf(38,70,10,5.5,-30,'#1aaa6a',.9)}${leaf(52,64,10,5.5,30,'#2dd48a',.85)}
      <circle cx="45" cy="54" r="8" fill="#2dd48a" opacity=".9"/>
      <circle cx="45" cy="50" r="5" fill="#5af0b8" opacity=".8"/>
    </svg>`;
  }
  if(s===1){
    return `<svg width="130" height="180" viewBox="0 0 130 180"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2dd48a"/><stop offset="100%" stop-color="#0a4428"/></linearGradient></defs>
      <ellipse cx="65" cy="165" rx="38" ry="10" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="65" cy="158" rx="22" ry="11" fill="#c8e0c0"/>
      <path d="M65 160 C65 145 62 120 64 95 C66 72 64 48 65 28" stroke="url(#trunk)" stroke-width="5" stroke-linecap="round" fill="none"/>
      ${leaf(44,128,20,10,-40,'#1aaa6a',.9)}${leaf(86,116,20,10,40,'#2dd48a',.85)}
      ${leaf(42,100,18,9,-35,'#0d8a52',.88)}${leaf(88,88,18,9,35,'#3deda0',.82)}
      ${leaf(48,75,16,8,-30,'#1aaa6a',.85)}${leaf(82,64,16,8,30,'#2dd48a',.8)}
      <ellipse cx="65" cy="44" rx="18" ry="14" fill="#2dd48a" opacity=".92"/>
      <ellipse cx="65" cy="34" rx="12" ry="8" fill="#5af0b8" opacity=".85"/>
    </svg>`;
  }
  if(s===2){
    return `<svg width="180" height="270" viewBox="0 0 180 270"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2dd48a"/><stop offset="100%" stop-color="#0a4428"/></linearGradient></defs>
      <ellipse cx="90" cy="255" rx="50" ry="13" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="90" cy="247" rx="30" ry="13" fill="#c8e0c0"/>
      <path d="M90 250 C90 228 86 196 88 160 C90 130 87 98 89 68 C91 44 89 22 90 6" stroke="url(#trunk)" stroke-width="7" stroke-linecap="round" fill="none"/>
      ${branch(90,195,72,182,3.5)}${branch(90,178,108,165,3.5)}
      ${branch(90,148,68,134,3)}${branch(90,134,112,120,3)}
      ${leaf(58,176,22,11,-25,'#1aaa6a',.9)}${leaf(116,162,22,11,25,'#2dd48a',.85)}
      ${leaf(54,127,24,12,-38,'#0d8a52',.88)}${leaf(118,113,24,12,38,'#3deda0',.84)}
      ${leaf(60,98,22,11,-32,'#1aaa6a',.86)}${leaf(120,84,22,11,32,'#2dd48a',.82)}
      ${leaf(66,68,20,10,-28,'#0d8a52',.88)}${leaf(114,56,20,10,28,'#3deda0',.82)}
      <ellipse cx="90" cy="40" rx="28" ry="18" fill="#2dd48a" opacity=".93"/>
      <ellipse cx="90" cy="28" rx="18" ry="11" fill="#5af0b8" opacity=".87"/>
      <ellipse cx="90" cy="18" rx="10" ry="6" fill="#90ffd8" opacity=".75"/>
    </svg>`;
  }
  if(s===3){
    return `<svg width="220" height="340" viewBox="0 0 220 340"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2dd48a"/><stop offset="100%" stop-color="#071a0e"/></linearGradient></defs>
      <ellipse cx="110" cy="326" rx="62" ry="15" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="110" cy="317" rx="38" ry="15" fill="#c8e0c0"/>
      <path d="M110 320 C110 296 104 256 106 210 C108 170 105 128 107 88 C109 56 107 28 110 8" stroke="url(#trunk)" stroke-width="11" stroke-linecap="round" fill="none"/>
      ${branch(110,250,86,234,5)}${branch(110,232,134,216,5)}
      ${branch(110,196,82,178,4.5)}${branch(110,180,138,162,4.5)}
      ${branch(110,148,84,130,4)}${branch(110,134,136,116,4)}
      ${leaf(72,226,26,13,-25,'#1aaa6a',.9)}${leaf(148,210,26,13,25,'#2dd48a',.86)}
      ${leaf(68,170,28,14,-35,'#0d8a52',.88)}${leaf(150,154,28,14,35,'#3deda0',.84)}
      ${leaf(70,122,26,13,-30,'#1aaa6a',.88)}${leaf(150,106,26,13,30,'#2dd48a',.84)}
      ${leaf(76,84,24,12,-26,'#0d8a52',.9)}${leaf(144,70,24,12,26,'#3deda0',.84)}
      <ellipse cx="110" cy="52" rx="36" ry="22" fill="#1aaa6a" opacity=".9"/>
      <ellipse cx="110" cy="38" rx="28" ry="16" fill="#2dd48a" opacity=".93"/>
      <ellipse cx="110" cy="24" rx="18" ry="11" fill="#5af0b8" opacity=".87"/>
    </svg>`;
  }
  if(s===4){
    return `<svg width="260" height="400" viewBox="0 0 260 400"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2dd48a"/><stop offset="100%" stop-color="#040d07"/></linearGradient></defs>
      <ellipse cx="130" cy="385" rx="72" ry="17" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="130" cy="375" rx="45" ry="17" fill="#c8e0c0"/>
      <path d="M130 380 C130 352 122 306 124 252 C126 204 123 154 125 106 C127 66 125 32 130 8" stroke="url(#trunk)" stroke-width="14" stroke-linecap="round" fill="none"/>
      ${branch(130,300,100,280,6.5)}${branch(130,278,160,258,6.5)}
      ${branch(130,240,95,218,6)}${branch(130,222,165,200,6)}
      ${branch(130,188,98,166,5)}${branch(130,170,162,148,5)}
      ${branch(130,140,102,118,4.5)}${branch(130,124,158,102,4.5)}
      ${leaf(85,272,30,15,-25,'#1aaa6a',.9)}${leaf(175,252,30,15,25,'#2dd48a',.86)}
      ${leaf(78,210,32,16,-32,'#0d8a52',.88)}${leaf(180,192,32,16,32,'#3deda0',.84)}
      ${leaf(80,158,30,15,-28,'#1aaa6a',.88)}${leaf(178,140,30,15,28,'#2dd48a',.84)}
      ${leaf(85,110,28,14,-25,'#0d8a52',.9)}${leaf(175,94,28,14,25,'#3deda0',.85)}
      <ellipse cx="130" cy="70" rx="44" ry="28" fill="#0d8a52" opacity=".9"/>
      <ellipse cx="130" cy="52" rx="36" ry="22" fill="#2dd48a" opacity=".93"/>
      <ellipse cx="130" cy="34" rx="24" ry="15" fill="#5af0b8" opacity=".88"/>
    </svg>`;
  }
  if(s===5){
    return `<svg width="300" height="460" viewBox="0 0 300 460"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1a8855"/><stop offset="100%" stop-color="#020906"/></linearGradient></defs>
      <ellipse cx="150" cy="444" rx="84" ry="19" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="150" cy="432" rx="52" ry="19" fill="#c8e0c0"/>
      <path d="M145 448 C143 448 136 440 135 442" stroke="#030a06" stroke-width="5" fill="none" stroke-linecap="round"/>
      <path d="M155 448 C157 448 164 440 165 442" stroke="#030a06" stroke-width="5" fill="none" stroke-linecap="round"/>
      <path d="M150 446 C150 414 141 362 143 298 C145 240 142 178 144 122 C146 76 144 36 150 8" stroke="url(#trunk)" stroke-width="18" stroke-linecap="round" fill="none"/>
      ${branch(150,354,114,330,8)}${branch(150,330,186,306,8)}
      ${branch(150,284,110,258,7)}${branch(150,262,190,236,7)}
      ${branch(150,220,114,194,6)}${branch(150,200,186,174,6)}
      ${branch(150,164,118,138,5.5)}${branch(150,148,182,122,5.5)}
      ${leaf(95,322,36,18,-26,'#1aaa6a',.9)}${leaf(205,298,36,18,26,'#2dd48a',.86)}
      ${leaf(88,250,38,19,-32,'#0d8a52',.88)}${leaf(210,226,38,19,32,'#3deda0',.84)}
      ${leaf(92,186,36,18,-28,'#1aaa6a',.88)}${leaf(208,162,36,18,28,'#2dd48a',.84)}
      ${leaf(98,130,34,17,-24,'#0d8a52',.9)}${leaf(202,108,34,17,24,'#3deda0',.85)}
      <ellipse cx="150" cy="78" rx="52" ry="34" fill="#1aaa6a" opacity=".9"/>
      <ellipse cx="150" cy="56" rx="44" ry="28" fill="#2dd48a" opacity=".94"/>
      <ellipse cx="150" cy="36" rx="30" ry="18" fill="#5af0b8" opacity=".88"/>
      <ellipse cx="150" cy="22" rx="16" ry="9" fill="#a0ffd8" opacity=".75"/>
    </svg>`;
  }
  if(s===6){
    return `<svg width="330" height="500" viewBox="0 0 330 500"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#148045"/><stop offset="100%" stop-color="#010604"/></linearGradient></defs>
      <ellipse cx="165" cy="484" rx="92" ry="20" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="165" cy="472" rx="58" ry="20" fill="#c8e0c0"/>
      <path d="M148 484 C132 490 114 496 94 492" stroke="#020805" stroke-width="6" fill="none" stroke-linecap="round" opacity=".75"/>
      <path d="M182 484 C198 490 216 496 236 492" stroke="#020805" stroke-width="6" fill="none" stroke-linecap="round" opacity=".75"/>
      <path d="M165 480 C165 444 155 386 157 316 C159 252 156 182 158 120 C160 70 158 32 165 8" stroke="url(#trunk)" stroke-width="22" stroke-linecap="round" fill="none"/>
      ${branch(165,380,124,354,9.5)}${branch(165,356,206,330,9.5)}
      ${branch(165,306,120,278,8.5)}${branch(165,284,210,256,8.5)}
      ${branch(165,240,124,212,8)}${branch(165,218,206,190,8)}
      ${branch(165,180,130,152,7)}${branch(165,160,200,132,7)}
      ${branch(124,354,98,334,6)}${branch(206,330,232,310,6)}
      ${leaf(100,345,40,20,-25,'#1aaa6a',.9)}${leaf(230,320,40,20,25,'#2dd48a',.86)}
      ${leaf(94,270,42,21,-30,'#0d8a52',.88)}${leaf(234,246,42,21,30,'#3deda0',.84)}
      ${leaf(98,204,40,20,-26,'#1aaa6a',.88)}${leaf(230,180,40,20,26,'#2dd48a',.84)}
      ${leaf(104,144,38,19,-22,'#0d8a52',.9)}${leaf(224,120,38,19,22,'#3deda0',.85)}
      <ellipse cx="165" cy="82" rx="58" ry="38" fill="#0d8a52" opacity=".9"/>
      <ellipse cx="165" cy="58" rx="50" ry="32" fill="#2dd48a" opacity=".94"/>
      <ellipse cx="165" cy="36" rx="34" ry="21" fill="#5af0b8" opacity=".88"/>
      <ellipse cx="165" cy="20" rx="18" ry="10" fill="#c0ffe8" opacity=".7"/>
    </svg>`;
  }
  if(s===7){
    return `<svg width="360" height="530" viewBox="0 0 360 530"><defs><linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0f6535"/><stop offset="100%" stop-color="#010503"/></linearGradient></defs>
      <ellipse cx="180" cy="514" rx="100" ry="22" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="180" cy="500" rx="64" ry="22" fill="#c8e0c0"/>
      <path d="M158 510 C136 518 110 526 84 522" stroke="#030a06" stroke-width="7" fill="none" stroke-linecap="round" opacity=".78"/>
      <path d="M202 510 C224 518 250 526 276 522" stroke="#030a06" stroke-width="7" fill="none" stroke-linecap="round" opacity=".78"/>
      <path d="M160 516 C148 528 132 534 114 532" stroke="#020805" stroke-width="4.5" fill="none" stroke-linecap="round" opacity=".5"/>
      <path d="M200 516 C212 528 228 534 246 532" stroke="#020805" stroke-width="4.5" fill="none" stroke-linecap="round" opacity=".5"/>
      <path d="M180 508 C180 468 169 404 171 328 C173 258 170 182 172 116 C174 62 172 28 180 6" stroke="url(#trunk)" stroke-width="28" stroke-linecap="round" fill="none"/>
      ${branch(180,408,134,378,11)}${branch(180,382,226,352,11)}
      ${branch(180,326,130,296,10)}${branch(180,302,230,272,10)}
      ${branch(180,256,136,226,9)}${branch(180,232,224,202,9)}
      ${branch(180,192,140,162,8)}${branch(180,170,220,140,8)}
      ${branch(134,378,104,354,6.5)}${branch(226,352,256,328,6.5)}
      ${leaf(106,370,44,22,-24,'#1aaa6a',.9)}${leaf(254,344,44,22,24,'#2dd48a',.86)}
      ${leaf(98,288,46,23,-30,'#0d8a52',.88)}${leaf(260,262,46,23,30,'#3deda0',.84)}
      ${leaf(102,218,44,22,-26,'#1aaa6a',.88)}${leaf(256,192,44,22,26,'#2dd48a',.84)}
      ${leaf(108,154,42,21,-22,'#0d8a52',.9)}${leaf(250,130,42,21,22,'#3deda0',.85)}
      <ellipse cx="180" cy="86" rx="64" ry="42" fill="#1aaa6a" opacity=".9"/>
      <ellipse cx="180" cy="60" rx="55" ry="36" fill="#2dd48a" opacity=".94"/>
      <ellipse cx="180" cy="36" rx="38" ry="24" fill="#5af0b8" opacity=".88"/>
      <ellipse cx="180" cy="20" rx="22" ry="12" fill="#c0ffe8" opacity=".72"/>
    </svg>`;
  }
  if(s===8){
    return `<svg width="390" height="560" viewBox="0 0 390 560"><defs>
      <linearGradient id="trunk" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0a5028"/><stop offset="100%" stop-color="#010402"/></linearGradient>
      <radialGradient id="aura" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#f0c060" stop-opacity=".12"/><stop offset="100%" stop-color="transparent"/></radialGradient>
      </defs>
      <!-- legendary aura -->
      <ellipse cx="195" cy="280" rx="170" ry="230" fill="url(#aura)"/>
      <ellipse cx="195" cy="543" rx="110" ry="24" fill="#d4e8d0" opacity=".6"/>
      <ellipse cx="195" cy="528" rx="70" ry="24" fill="#c8e0c0"/>
      <!-- roots -->
      <path d="M170 530 C144 540 114 548 86 544" stroke="#020805" stroke-width="8" fill="none" stroke-linecap="round" opacity=".82"/>
      <path d="M220 530 C246 540 276 548 304 544" stroke="#020805" stroke-width="8" fill="none" stroke-linecap="round" opacity=".82"/>
      <path d="M162 535 C144 548 124 554 104 552" stroke="#020805" stroke-width="5" fill="none" stroke-linecap="round" opacity=".56"/>
      <path d="M228 535 C246 548 266 554 286 552" stroke="#020805" stroke-width="5" fill="none" stroke-linecap="round" opacity=".56"/>
      <path d="M148 540 C130 554 108 562 88 560" stroke="#020805" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".38"/>
      <path d="M242 540 C260 554 282 562 302 560" stroke="#020805" stroke-width="3.5" fill="none" stroke-linecap="round" opacity=".38"/>
      <!-- massive trunk -->
      <path d="M195 534 C195 490 183 422 185 340 C187 264 184 182 186 112 C188 54 186 22 195 4" stroke="url(#trunk)" stroke-width="34" stroke-linecap="round" fill="none"/>
      <!-- trunk highlight -->
      <path d="M191 490 C196 480 195 468 192 458" stroke="rgba(255,255,255,.05)" stroke-width="3" stroke-linecap="round" fill="none"/>
      <path d="M193 430 C198 420 197 408 194 398" stroke="rgba(255,255,255,.04)" stroke-width="2.5" stroke-linecap="round" fill="none"/>
      ${branch(195,432,144,400,13)}${branch(195,406,246,374,13)}
      ${branch(195,346,140,314,12)}${branch(195,320,250,288,12)}
      ${branch(195,268,144,236,11)}${branch(195,244,246,212,11)}
      ${branch(195,200,148,168,10)}${branch(195,178,242,146,10)}
      ${branch(195,148,152,116,9)}${branch(195,126,238,94,9)}
      ${branch(144,400,110,374,7.5)}${branch(246,374,280,348,7.5)}
      ${branch(140,314,106,288,7)}${branch(250,288,284,262,7)}
      <!-- foliage -->
      ${leaf(104,388,48,24,-25,'#1aaa6a',.9)}${leaf(286,362,48,24,25,'#2dd48a',.86)}
      ${leaf(96,306,50,25,-30,'#0d8a52',.88)}${leaf(292,280,50,25,30,'#3deda0',.84)}
      ${leaf(100,228,48,24,-26,'#1aaa6a',.88)}${leaf(288,202,48,24,26,'#2dd48a',.84)}
      ${leaf(106,160,46,23,-22,'#0d8a52',.9)}${leaf(282,136,46,23,22,'#3deda0',.85)}
      ${leaf(110,106,44,22,-20,'#1aaa6a',.9)}${leaf(278,82,44,22,20,'#2dd48a',.86)}
      <!-- legendary crown -->
      <ellipse cx="195" cy="60" rx="75" ry="48" fill="#0d8a52" opacity=".9"/>
      <ellipse cx="195" cy="40" rx="63" ry="40" fill="#2dd48a" opacity=".95"/>
      <ellipse cx="195" cy="22" rx="44" ry="28" fill="#5af0b8" opacity=".9"/>
      <ellipse cx="195" cy="8" rx="26" ry="15" fill="#c0ffe8" opacity=".8"/>
      <!-- golden crown halo -->
      <ellipse cx="195" cy="40" rx="68" ry="44" fill="none" stroke="#f0c060" stroke-width="2" opacity=".4">
        <animate attributeName="opacity" values="0.4;0.7;0.4" dur="3s" repeatCount="indefinite"/>
        <animate attributeName="rx" values="68;74;68" dur="3s" repeatCount="indefinite"/>
        <animate attributeName="ry" values="44;48;44" dur="3s" repeatCount="indefinite"/>
      </ellipse>
      <!-- golden particles -->
      <circle cx="148" cy="34" r="3.5" fill="#f0c060" opacity=".8"><animate attributeName="opacity" values="0.8;0.2;0.8" dur="2.3s" repeatCount="indefinite"/><animate attributeName="r" values="3.5;5;3.5" dur="2.3s" repeatCount="indefinite"/></circle>
      <circle cx="242" cy="22" r="3" fill="#f0c060" opacity=".7"><animate attributeName="opacity" values="0.7;0.15;0.7" dur="3.2s" repeatCount="indefinite"/></circle>
      <circle cx="108" cy="96" r="2.5" fill="#f0c060" opacity=".6"><animate attributeName="opacity" values="0.6;0.1;0.6" dur="2.8s" repeatCount="indefinite"/></circle>
      <circle cx="282" cy="78" r="2.5" fill="#f0c060" opacity=".55"><animate attributeName="opacity" values="0.55;0.1;0.55" dur="3.7s" repeatCount="indefinite"/></circle>
      <circle cx="60" cy="228" r="2" fill="#f0c060" opacity=".45"><animate attributeName="opacity" values="0.45;0.1;0.45" dur="2.5s" repeatCount="indefinite"/></circle>
      <circle cx="330" cy="200" r="2" fill="#f0c060" opacity=".5"><animate attributeName="opacity" values="0.5;0.1;0.5" dur="3.9s" repeatCount="indefinite"/></circle>
      <!-- legendary star -->
      <path d="M195 -12 L197 -6 L204 -6 L198.5 -2 L201 5 L195 1 L189 5 L191.5 -2 L186 -6 L193 -6 Z" fill="#f0c060" opacity=".92">
        <animate attributeName="opacity" values="0.92;0.5;0.92" dur="1.8s" repeatCount="indefinite"/>
        <animateTransform attributeName="transform" type="rotate" from="0 195 0" to="360 195 0" dur="9s" repeatCount="indefinite"/>
      </path>
    </svg>`;
  }
  return '';
}

function renderDrops(n){
  const el=document.getElementById('dropsEl');
  el.innerHTML='';
  for(let i=0;i<3;i++){
    const full=i<n;
    el.innerHTML+=`<div class="drop-ico"><svg viewBox="0 0 18 22" fill="none">
      <path d="M9 2C9 2 2 10 2 14.5a7 7 0 0014 0C16 10 9 2 9 2z"
        fill="${full?'#38bdf8':'rgba(13,38,24,.12)'}"
        stroke="${full?'#22a8e0':'rgba(13,38,24,.2)'}" stroke-width="1.2"/>
    </svg></div>`;
  }
}

function setStage(s){
  currentStage=s;
  const d=stageData[s];
  document.getElementById('sName').textContent=d.name;
  document.getElementById('sNum').textContent=d.num;
  document.getElementById('sStreak').textContent=s===8?'300+':d.streak||'—';
  document.getElementById('plantWrap').innerHTML=plantSVG(s);
  document.querySelectorAll('.stage-item').forEach((el,i)=>el.classList.toggle('active',i===s));
  renderDrops(d.drops);
}
function nextStage(){setStage(Math.min(currentStage+1,8))}
function prevStage(){setStage(Math.max(currentStage-1,0))}

/* ═══════════════ INIT ═══════════════ */
setStage(2);
/* dispara XP após render */
setTimeout(()=>{
  const fill=document.querySelector('.xp-fill');
  if(fill) fill.style.width='72%';
},400);
renderDrops(2);

/* ═══ MODAL SWIPE TO CLOSE (mobile) ═══ */
(function(){
  const modal = document.querySelector('.modal');
  const ov = document.getElementById('overlay');
  let startY = 0, isDragging = false;

  modal.addEventListener('touchstart', e => {
    startY = e.touches[0].clientY;
    isDragging = true;
  }, {passive: true});

  modal.addEventListener('touchmove', e => {
    if (!isDragging) return;
    const dy = e.touches[0].clientY - startY;
    if (dy > 0) modal.style.transform = `translateY(${dy}px)`;
  }, {passive: true});

  modal.addEventListener('touchend', e => {
    const dy = e.changedTouches[0].clientY - startY;
    modal.style.transform = '';
    isDragging = false;
    if (dy > 80) closeModal();
  });

  /* fecha ao clicar fora — mantido do original mas corrigido */
  ov.addEventListener('click', e => {
    if (e.target === ov) closeModal();
  });
})();

/* ═══ LEGAL MODAL CLICK-OUTSIDE ═══ */
document.getElementById('legalOv').addEventListener('click', e => {
  if (e.target === document.getElementById('legalOv')) closeLegal();
});

/* ═══════════════ NAV SCROLL ═══════════════ */
const navEl=document.getElementById('nav');
window.addEventListener('scroll',()=>navEl.classList.toggle('scrolled',scrollY>40),{passive:true});

/* ═══════════════ REVEAL ═══════════════ */
const ro=new IntersectionObserver(entries=>entries.forEach((e,i)=>{
  if(e.isIntersecting){setTimeout(()=>e.target.classList.add('visible'),i*75);ro.unobserve(e.target)}
}),{threshold:.07});
document.querySelectorAll('.reveal').forEach(el=>ro.observe(el));

/* ═══════════════ FAQ ═══════════════ */
function toggleFaq(btn){
  const item=btn.closest('.faq-item');
  const isOpen=item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(i=>i.classList.remove('open'));
  if(!isOpen) item.classList.add('open');
}

/* ═══════════════ MODAL AUTH ═══════════════ */
const ov=document.getElementById('overlay');
function openModal(tab='login'){
  ov.classList.add('on');document.body.style.overflow='hidden';
  switchTab(tab);showAuth();
}
function closeModal(){ov.classList.remove('on');document.body.style.overflow='';}
ov.addEventListener('click',e=>{if(e.target===ov)e.stopPropagation();});

function switchTab(tab){
  const isL=tab==='login';
  document.getElementById('t1').classList.toggle('on',isL);
  document.getElementById('t2').classList.toggle('on',!isL);
  document.getElementById('fLogin').style.display=isL?'block':'none';
  document.getElementById('fReg').style.display=isL?'none':'block';
  clearAlerts();
}
function showReset(){
  document.getElementById('authV').classList.add('off');
  document.getElementById('resetV').classList.add('on');
  document.getElementById('rs1').style.display='block';
  document.getElementById('rs2').style.display='none';
}
function showAuth(){
  document.getElementById('authV').classList.remove('off');
  document.getElementById('resetV').classList.remove('on');
}
function clearAlerts(){
  ['authAlert','rAlert1','rAlert2'].forEach(id=>{
    const el=document.getElementById(id);
    if(el){el.className='malert';el.textContent=''}
  });
  document.querySelectorAll('.inp').forEach(i=>i.classList.remove('err'));
  document.querySelectorAll('.ferr').forEach(e=>e.classList.remove('on'));
}
function showAlert(id,msg,type='e'){
  const el=document.getElementById(id);
  el.textContent=msg;el.className=`malert ${type} on`;
}
function ferr(iId,eId,show){
  document.getElementById(iId)?.classList.toggle('err',show);
  document.getElementById(eId)?.classList.toggle('on',show);
}
function setLoad(btn,on){btn.classList.toggle('loading',on);btn.disabled=on;}

function checkTerms(){
  const ok=document.getElementById('termsCheck').checked;
  document.getElementById('termsE').classList.toggle('on',false);
}

/* ═══════════════ EYE TOGGLE ═══════════════ */
const eyeO=`<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const eyeC=`<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
function toggleEye(id,btn){
  const inp=document.getElementById(id);
  const svg=btn.querySelector('svg');
  const show=inp.type==='password';
  inp.type=show?'text':'password';
  svg.innerHTML=show?eyeC:eyeO;
  btn.style.color=show?'var(--green)':'var(--t-faint)';
}

/* ═══════════════ STRENGTH ═══════════════ */
function checkStr(v){
  const fill=document.getElementById('sFill'),txt=document.getElementById('sTxt');
  let s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/\d/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;if(/[A-Z]/.test(v))s++;
  const map=[[0,'0%','transparent',''],[1,'25%','var(--err)','Muito fraca'],
    [2,'50%','#f59e0b','Fraca'],[3,'75%','var(--green)','Boa'],
    [4,'100%','var(--green)','Forte'],[5,'100%','var(--green)','Muito forte']];
  const[,w,c,l]=map[Math.min(s,5)];
  fill.style.width=w;fill.style.background=c;txt.textContent=l;txt.style.color=c;
}

/* ═══════════════ VALIDATORS ═══════════════ */
const isEmail=v=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim());
const isStrong=v=>v.length>=6&&/\d/.test(v)&&/[^a-zA-Z0-9]/.test(v);

/* ═══════════════ LOGIN ═══════════════ */
document.getElementById('fLogin').addEventListener('submit',async function(e){
  e.preventDefault();clearAlerts();
  const email=document.getElementById('lEmail').value;
  const pass=document.getElementById('lPass').value;
  let ok=true;
  if(!isEmail(email)){ferr('lEmail','lEmailE',true);ok=false;}
  if(!pass){ferr('lPass','lPassE',true);ok=false;}
  if(!ok)return;
  const btn=this.querySelector('button[type=submit]');setLoad(btn,true);
  try{
    const res=await fetch('https://florescerapp.com.br/florescer/api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'login',email,password:pass})});
    const data=await res.json();
    if(data.success){window.location.href='/florescer/public/views/dashboard.php';}
    else showAlert('authAlert',data.message||'E-mail ou senha incorretos. Tente novamente.');
  }catch{showAlert('authAlert','Erro de conexão. Verifique sua internet e tente novamente.');}
  finally{setLoad(btn,false);}
});

/* ═══════════════ REGISTER ═══════════════ */
document.getElementById('fReg').addEventListener('submit',async function(e){
  e.preventDefault();clearAlerts();
  const name=document.getElementById('rName').value.trim();
  const email=document.getElementById('rEmail').value.trim();
  const pass=document.getElementById('rPass').value;
  const conf=document.getElementById('rConf').value;
  const terms=document.getElementById('termsCheck').checked;
  let ok=true;
  if(!name){ferr('rName','rNameE',true);ok=false;}
  if(!isEmail(email)){ferr('rEmail','rEmailE',true);ok=false;}
  if(!isStrong(pass)){ferr('rPass','rPassE',true);ok=false;}
  if(pass!==conf){ferr('rConf','rConfE',true);ok=false;}
  if(!terms){document.getElementById('termsE').classList.add('on');ok=false;}
  if(!ok)return;
  const btn=this.querySelector('button[type=submit]');setLoad(btn,true);
  try{
    const res=await fetch('https://florescerapp.com.br/florescer/api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'register',name,email,password:pass})});
    const data=await res.json();
    if(data.success){window.location.href='/florescer/public/views/dashboard.php';}
    else showAlert('authAlert',data.message||'Não foi possível criar a conta. Tente novamente.');
  }catch{showAlert('authAlert','Erro de conexão. Verifique sua internet e tente novamente.');}
  finally{setLoad(btn,false);}
});

async function checkEmail(inp){
  const val = inp.value.trim();
  const msg = document.getElementById('rEmailLive');
  if(!val || !isEmail(val)){ msg.style.display='none'; return; }

  // Verifica se domínio tem MX válido via API pública gratuita
  try {
    const res = await fetch(`https://api.eva.pingutil.com/email?email=${encodeURIComponent(val)}`);
    const data = await res.json();
    const valid = data?.data?.valid_syntax && !data?.data?.disposable;
    msg.style.display = 'block';
    msg.style.color = valid ? 'var(--green)' : 'var(--err)';
    msg.textContent = valid ? '✓ E-mail válido' : '✗ E-mail inválido ou temporário';
    inp.classList.toggle('err', !valid);
  } catch {
    // Se a API falhar, não bloqueia — usa apenas validação local
    msg.style.display = 'none';
  }
}

/* ═══════════════ RESET ═══════════════ */
document.getElementById('fR1').addEventListener('submit',async function(e){
  e.preventDefault();
  const email=document.getElementById('rEmail1').value.trim();
  if(!isEmail(email)){ferr('rEmail1','rEmail1E',true);return;}
  ferr('rEmail1','rEmail1E',false);
  const btn=this.querySelector('button[type=submit]');setLoad(btn,true);
  try{
    await fetch('https://florescerapp.com.br/florescer/api/reset.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'request',email})});
    showAlert('rAlert1','Se esse e-mail estiver cadastrado, você receberá o código de recuperação em breve.','s');
    setTimeout(()=>{document.getElementById('rs1').style.display='none';document.getElementById('rs2').style.display='block';},2000);
  }catch{showAlert('rAlert1','Erro de conexão. Tente novamente.');}
  finally{setLoad(btn,false);}
});

document.getElementById('fR2').addEventListener('submit',async function(e){
  e.preventDefault();
  const code=document.getElementById('rCode').value.trim();
  const npass=document.getElementById('nPass').value;
  const nconf=document.getElementById('nConf').value;
  const email=document.getElementById('rEmail1').value.trim();
  let ok=true;
  if(!/^\d{6}$/.test(code)){ferr('rCode','rCodeE',true);ok=false;}
  if(!isStrong(npass)){ferr('nPass','nPassE',true);ok=false;}
  if(npass!==nconf){ferr('nConf','nConfE',true);ok=false;}
  if(!ok)return;
  const btn=this.querySelector('button[type=submit]');setLoad(btn,true);
  try{
    const res=await fetch('https://florescerapp.com.br/florescer/api/reset.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'reset',email,code,password:npass})});
    const data=await res.json();
    if(data.success){showAlert('rAlert2','Senha redefinida com sucesso! Redirecionando para o login…','s');setTimeout(()=>{closeModal();openModal('login');},2200);}
    else showAlert('rAlert2',data.message||'Código inválido ou expirado. Tente solicitar um novo.');
  }catch{showAlert('rAlert2','Erro de conexão. Tente novamente.');}
  finally{setLoad(btn,false);}
});

document.getElementById('rCode').addEventListener('input',function(){
  this.value=this.value.replace(/\D/g,'').slice(0,6);
});

/* ═══════════════ LEGAL MODAL ═══════════════ */
const legalOv=document.getElementById('legalOv');

const legalTexts={
  terms:`
    <div class="modal-h">Termos de Uso</div>
    <div class="legal-badge">📄 Última atualização: <?= date('d/m/Y') ?></div>
    <div class="legal-body">
      <div>
        <h3>1. Aceitação dos Termos</h3>
        <p>Ao criar uma conta no Florescer, você confirma que leu, compreendeu e concorda integralmente com estes Termos de Uso. Se não concordar com qualquer parte destes termos, não utilize a plataforma. O uso continuado após alterações aos termos constitui aceitação das mesmas.</p>
      </div>
      <div>
        <h3>2. Descrição do Serviço</h3>
        <p>O Florescer é uma plataforma digital gratuita de acompanhamento de estudos. Oferece ferramentas como timer Pomodoro, integração com YouTube, rastreamento de streak, sistema de progressão por estágios visuais (a "planta"), registro de notas por matéria e gamificação por XP e levels. O serviço é oferecido "como está", sem garantia de disponibilidade 100%.</p>
      </div>
      <div>
        <h3>3. Cadastro e Conta</h3>
        <p>Para usar o Florescer, você precisa criar uma conta com nome, e-mail e senha. Você é inteiramente responsável pela segurança das suas credenciais. Não compartilhe sua senha. Em caso de acesso não autorizado suspeito, entre em contato imediatamente. Contas inativas por mais de 24 meses poderão ser removidas mediante aviso prévio.</p>
      </div>
      <div>
        <h3>4. Uso Adequado e Proibições</h3>
        <p>O Florescer é destinado exclusivamente a fins educacionais pessoais. É terminantemente proibido: usar a plataforma para atividades ilegais; assediar, ameaçar ou prejudicar outros usuários; tentar comprometer a segurança ou integridade da plataforma; criar contas falsas ou automatizadas; e revender, sublicenciar ou explorar comercialmente os serviços sem autorização.</p>
      </div>
      <div>
        <h3>5. Propriedade do Conteúdo</h3>
        <p>Todo o conteúdo que você insere na plataforma (anotações, metas, histórico de estudo) pertence a você. O Florescer não reivindica direitos de propriedade intelectual sobre seu conteúdo. Ao usar a plataforma, você concede ao Florescer uma licença limitada e não exclusiva para armazenar e exibir esse conteúdo exclusivamente para que o serviço funcione corretamente.</p>
      </div>
      <div>
        <h3>6. Propriedade Intelectual da Plataforma</h3>
        <p>O design, código, marca, identidade visual e funcionalidades do Florescer são propriedade do criador. Você não pode copiar, modificar, distribuir ou criar obras derivadas da plataforma sem autorização expressa por escrito.</p>
      </div>
      <div>
        <h3>7. Isenção de Responsabilidade</h3>
        <p>O Florescer não se responsabiliza por: perda de dados causada por falhas técnicas imprevisíveis; resultados acadêmicos ou profissionais do usuário; conteúdo de vídeos do YouTube acessados pela plataforma (responsabilidade do YouTube/Google); ou danos indiretos, incidentais ou consequenciais decorrentes do uso.</p>
      </div>
      <div>
        <h3>8. Encerramento de Conta</h3>
        <p>Você pode solicitar a exclusão da sua conta a qualquer momento nas configurações do perfil ou por contato direto. Nos reservamos o direito de suspender ou encerrar contas que violem estes termos, sem aviso prévio em casos de violação grave.</p>
      </div>
      <div>
        <h3>9. Alterações aos Termos</h3>
        <p>Podemos atualizar estes termos periodicamente. Alterações significativas serão comunicadas por e-mail ou notificação na plataforma com pelo menos 15 dias de antecedência. O uso continuado após esse prazo constitui aceitação das mudanças.</p>
      </div>
      <div>
        <h3>10. Legislação Aplicável</h3>
        <p>Estes termos são regidos pela legislação brasileira, especialmente pelo Código de Defesa do Consumidor (Lei 8.078/90) e pela Lei Geral de Proteção de Dados (LGPD Lei 13.709/18).</p>
      </div>
    </div>`,
  privacy:`
    <div class="modal-h">Política de Privacidade</div>
    <div class="legal-badge">🔒 Última atualização: <?= date('d/m/Y') ?></div>
    <div class="legal-body">
      <div>
        <h3>Nosso compromisso com sua privacidade</h3>
        <p>O Florescer foi construído com respeito à privacidade do usuário como princípio central, não como obrigação legal. Coletamos o mínimo necessário para que o serviço funcione e nunca usamos seus dados para fins comerciais.</p>
      </div>
      <div>
        <h3>Quais dados coletamos</h3>
        <p><strong>Dados de cadastro:</strong> nome, endereço de e-mail e senha (armazenada exclusivamente como hash bcrypt nunca em texto puro). <strong>Dados de uso:</strong> histórico de sessões de estudo, metas diárias configuradas, anotações, notas por matéria, streak atual, XP e level. <strong>Dados técnicos:</strong> endereço IP (para segurança e prevenção de fraudes) e logs de acesso padrão do servidor.</p>
      </div>
      <div>
        <h3>Como usamos seus dados</h3>
        <p>Seus dados são usados exclusivamente para: fazer a plataforma funcionar corretamente; exibir seu progresso, streak e histórico; enviar e-mails transacionais necessários (como recuperação de senha); e melhorar a experiência com base em padrões de uso agregados e anônimos.</p>
      </div>
      <div>
        <h3>O que nunca fazemos com seus dados</h3>
        <p>Nunca vendemos, alugamos ou cedemos seus dados a terceiros. Nunca usamos seus dados para publicidade direcionada. Nunca compartilhamos informações pessoais identificáveis com parceiros ou anunciantes. Não existe monetização dos seus dados de nenhuma forma.</p>
      </div>
      <div>
        <h3>Armazenamento e segurança</h3>
        <p>Seus dados são armazenados em servidores com acesso restrito e proteção adequada. Senhas são tratadas com bcrypt (fator de custo ≥ 12). Comunicações são feitas exclusivamente via HTTPS. Realizamos backups regulares para prevenir perda de dados.</p>
      </div>
      <div>
        <h3>Retenção dos dados</h3>
        <p>Mantemos seus dados enquanto sua conta estiver ativa. Após a exclusão da conta, os dados são removidos dos sistemas ativos em até 30 dias e dos backups em até 90 dias. Dados anonimizados e agregados (estatísticas de uso sem identificação) podem ser mantidos indefinidamente.</p>
      </div>
      <div>
        <h3>Seus direitos (LGPD)</h3>
        <p>De acordo com a Lei Geral de Proteção de Dados (Lei 13.709/18), você tem direito a: confirmar se tratamos seus dados; acessar seus dados; corrigir dados incompletos ou incorretos; solicitar a exclusão de dados desnecessários; revogar consentimento; e portabilidade dos dados. Para exercer qualquer desses direitos, acesse as configurações da conta ou entre em contato conosco.</p>
      </div>
      <div>
        <h3>Serviços de terceiros</h3>
        <p>O Florescer integra o YouTube para reprodução de vídeos. Ao assistir um vídeo, você está sujeito também à Política de Privacidade do Google/YouTube. Não utilizamos Google Analytics, Facebook Pixel, Hotjar ou qualquer outra ferramenta de rastreamento de terceiros.</p>
      </div>
      <div>
        <h3>Menores de idade</h3>
        <p>O Florescer não é direcionado a menores de 13 anos e não coletamos conscientemente dados de crianças. Se identificarmos uma conta de menor sem autorização parental adequada, a conta será removida.</p>
      </div>
    </div>`,
  cookies:`
    <div class="modal-h">Política de Cookies</div>
    <div class="legal-badge">🍪 Última atualização: <?= date('d/m/Y') ?></div>
    <div class="legal-body">
      <div>
        <h3>O que são cookies</h3>
        <p>Cookies são pequenos arquivos de texto que um site armazena no seu navegador para reconhecer você em visitas futuras. Eles são usados de formas muito diferentes desde manter você autenticado até rastrear comportamentos de navegação para publicidade.</p>
      </div>
      <div>
        <h3>O que usamos: apenas o essencial</h3>
        <p>O Florescer utiliza <strong>exclusivamente cookies de sessão PHP</strong> um único cookie técnico chamado <code>PHPSESSID</code>. Ele é criado quando você faz login e serve apenas para manter sua sessão ativa enquanto você usa a plataforma. Sem ele, você precisaria fazer login a cada página. Este cookie é excluído automaticamente quando você fecha o navegador ou faz logout.</p>
      </div>
      <div>
        <h3>O que absolutamente não usamos</h3>
        <p>Não utilizamos cookies de rastreamento, cookies analíticos, cookies de publicidade ou cookies de terceiros de nenhum tipo. Isso significa: sem Google Analytics, sem Facebook Pixel, sem Hotjar, sem cookies de remarketing, sem qualquer tecnologia de rastreamento entre sites.</p>
      </div>
      <div>
        <h3>Cookies do YouTube</h3>
        <p>Quando você assiste um vídeo do YouTube dentro do Florescer, o player do YouTube pode definir seus próprios cookies. Esses cookies são de responsabilidade do Google/YouTube e seguem a política de privacidade deles. Não temos controle sobre esses cookies de terceiros.</p>
      </div>
      <div>
        <h3>Como gerenciar cookies</h3>
        <p>Você pode limpar cookies a qualquer momento nas configurações do seu navegador. Ao limpar o cookie de sessão do Florescer, você será desconectado e precisará fazer login novamente. Bloquear cookies de sessão impedirá o funcionamento da autenticação na plataforma.</p>
      </div>
      <div>
        <h3>Consentimento</h3>
        <p>Ao usar o Florescer após fazer login, você consente com o uso do cookie de sessão descrito acima. Como usamos apenas cookies estritamente necessários para o funcionamento do serviço, o consentimento está implícito no uso da plataforma, conforme permitido pela LGPD e pelas diretrizes de privacidade da UE.</p>
      </div>
    </div>`
};

function openLegal(type){
  document.getElementById('legalContent').innerHTML=legalTexts[type]||'';
  legalOv.classList.add('on');
  document.body.style.overflow='hidden';
}
function closeLegal(){
  legalOv.classList.remove('on');
  document.body.style.overflow='';
}
legalOv.addEventListener('click',e=>{if(e.target===legalOv)e.stopPropagation();});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){closeModal();closeLegal();}
});
</script>
</body>
</html>