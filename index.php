<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seela suwa herath — Monastery Welfare & Donation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --white:#FFFFFF;--ivory:#FFFBF7;--cream:#FEF3E8;--sand:#F5E0C8;
            --orange:#D4622A;--orange-mid:#F0864A;--orange-light:#F0A050;--orange-pale:#FDEBD8;
            --text-dark:#1E1610;--text-mid:#5A4A3A;--text-light:#9A8070;
            --border:rgba(210,170,130,0.28);
        }
        html{scroll-behavior:smooth}
        body{font-family:'Jost',sans-serif;font-weight:300;background:var(--white);color:var(--text-dark);overflow-x:hidden}

        /* NAV */
        nav{position:fixed;top:0;left:0;right:0;z-index:200;padding:0 6%;height:76px;display:flex;align-items:center;justify-content:space-between;transition:background .4s,box-shadow .4s}
        nav.scrolled{background:rgba(255,255,255,.96);backdrop-filter:blur(16px);box-shadow:0 1px 0 var(--border)}
        .nav-logo{display:flex;align-items:center;gap:12px;text-decoration:none}
        .nav-logo-mark{width:38px;height:38px;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.35);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;transition:background .4s,border-color .4s}
        nav.scrolled .nav-logo-mark{background:linear-gradient(135deg,var(--orange),var(--orange-light));border-color:transparent}
        .nav-logo-name{font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-weight:600;color:var(--white);transition:color .4s}
        nav.scrolled .nav-logo-name{color:var(--text-dark)}
        .nav-logo-sub{font-size:.6rem;color:rgba(255,255,255,.55);letter-spacing:.13em;text-transform:uppercase;display:block;margin-top:-4px;transition:color .4s}
        nav.scrolled .nav-logo-sub{color:var(--text-light)}
        .nav-links{display:flex;align-items:center;gap:32px;list-style:none}
        .nav-links a{text-decoration:none;color:rgba(255,255,255,.85);font-size:.83rem;font-weight:400;letter-spacing:.07em;text-transform:uppercase;transition:color .2s}
        nav.scrolled .nav-links a{color:var(--text-mid)}
        .nav-links a:hover{color:var(--white)}
        nav.scrolled .nav-links a:hover{color:var(--orange)}
        .nav-donate-btn{background:var(--white)!important;color:var(--orange)!important;padding:10px 26px!important;border-radius:40px!important;font-weight:500!important;transition:all .25s!important}
        nav.scrolled .nav-donate-btn{background:var(--orange)!important;color:var(--white)!important}
        .nav-donate-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(212,98,42,.3)}

        /* HERO */
        .hero{position:relative;height:100vh;min-height:640px;display:flex;align-items:center;overflow:visible}
        .hero-img{position:absolute;inset:0;z-index:0;background:#2A1A0E;overflow:hidden;border-radius:0}
        .hero-img img{width:100%;height:100%;object-fit:cover;object-position:center;opacity:.72}
        .hero-img-fallback{position:absolute;inset:0;background:linear-gradient(160deg,#3D1F0A 0%,#7A3A1A 45%,#C06030 100%);display:none}
        .hero-overlay{position:absolute;inset:0;z-index:1;background:linear-gradient(to right,rgba(15,8,3,.72) 0%,rgba(15,8,3,.45) 55%,rgba(15,8,3,.12) 100%)}
        .hero-overlay-btm{position:absolute;bottom:0;left:0;right:0;z-index:2;height:200px;background:linear-gradient(to top,rgba(255,251,247,1) 0%,transparent 100%)}
        .hero-content{position:relative;z-index:3;padding:0 6% 120px;max-width:700px}
        .hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(8px);padding:7px 18px;border-radius:40px;font-size:.72rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.85);margin-bottom:28px}
        .hero-badge span{color:var(--orange-light)}
        .hero-title{font-family:'Cormorant Garamond',serif;font-size:clamp(3rem,6.5vw,5.8rem);font-weight:300;line-height:1.05;color:var(--white);margin-bottom:24px;text-shadow:0 2px 32px rgba(0,0,0,.3)}
        .hero-title em{font-style:italic;color:var(--orange-light)}
        .hero-desc{font-size:1.05rem;color:rgba(255,255,255,.72);max-width:480px;line-height:1.85;margin-bottom:40px}
        .hero-btns{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .btn-hp{background:var(--orange);color:var(--white);padding:16px 44px;border-radius:50px;text-decoration:none;font-size:.95rem;font-weight:500;letter-spacing:.04em;transition:all .3s;border:2px solid var(--orange)}
        .btn-hp:hover{background:var(--orange-mid);border-color:var(--orange-mid);transform:translateY(-2px);box-shadow:0 12px 36px rgba(212,98,42,.4)}
        .btn-hg{background:rgba(255,255,255,.1);color:var(--white);padding:16px 36px;border-radius:50px;text-decoration:none;font-size:.95rem;font-weight:400;letter-spacing:.04em;transition:all .3s;border:1.5px solid rgba(255,255,255,.35);backdrop-filter:blur(6px)}
        .btn-hg:hover{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.6)}

        /* STATS BAR — half in hero, half in features */
        .stats-bar{position:absolute;bottom:-52px;left:0;right:0;margin:0 auto;z-index:10;display:flex;width:88%;max-width:960px;background:var(--white);border-radius:20px;border:1px solid rgba(210,170,130,.25);box-shadow:0 20px 60px rgba(0,0,0,.14),0 4px 16px rgba(212,98,42,.08);overflow:hidden}
        .stat-box{flex:1;padding:28px 24px;text-align:center;border-right:1px solid var(--border);position:relative}
        .stat-box:last-child{border-right:none}
        .stat-box::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--orange),var(--orange-light));opacity:0;transition:opacity .3s}
        .stat-box:hover::before{opacity:1}
        .stat-num{font-family:'Cormorant Garamond',serif;font-size:2.2rem;font-weight:600;color:var(--orange);line-height:1}
        .stat-lbl{font-size:.72rem;color:var(--text-light);letter-spacing:.08em;text-transform:uppercase;margin-top:5px}

        /* FEATURES — extra top padding for overlapping stats bar */
        .features{background:var(--white);padding:110px 6% 80px;position:relative;z-index:5}
        .sec-label{font-size:.72rem;font-weight:500;letter-spacing:.18em;text-transform:uppercase;color:var(--orange);display:block;margin-bottom:14px}
        .sec-title{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,3.8vw,3rem);font-weight:300;line-height:1.2;color:var(--text-dark);margin-bottom:56px}
        .feat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:28px}
        .feat-card{padding:30px 22px;border:1px solid var(--border);border-radius:16px;background:var(--white);transition:all .3s}
        .feat-card:hover{border-color:var(--orange-light);transform:translateY(-4px);box-shadow:0 16px 48px rgba(212,98,42,.08)}
        .feat-icon{width:54px;height:54px;background:var(--orange-pale);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:18px}
        .feat-title{font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:600;color:var(--text-dark);margin-bottom:8px}
        .feat-desc{font-size:.84rem;color:var(--text-light);line-height:1.7}

        /* MISSION */
        .mission{padding:80px 6%;background:var(--ivory);border-top:1px solid var(--border)}
        .mission-inner{max-width:1140px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:72px;align-items:center}
        .gal{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:auto auto;gap:10px}
        .gal-img{border-radius:12px;overflow:hidden;background:var(--sand)}
        .gal-img img{width:100%;height:100%;object-fit:cover;display:block}
        .gal-img.tall{grid-row:span 2;height:450px}
        .gal-img:not(.tall){height:220px}
        .mission-body{font-size:.97rem;color:var(--text-mid);line-height:1.9;margin-bottom:16px}
        .mission-quote{padding:18px 22px;border-left:3px solid var(--orange);background:var(--white);border-radius:0 10px 10px 0;margin:24px 0}
        .mission-quote p{font-family:'Cormorant Garamond',serif;font-size:1.12rem;font-style:italic;color:var(--text-dark);line-height:1.6}
        .inl-stats{display:flex;gap:28px;margin-top:28px;padding-top:24px;border-top:1px solid var(--border)}
        .inl-num{font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:600;color:var(--orange)}
        .inl-lbl{font-size:.72rem;color:var(--text-light);letter-spacing:.07em;text-transform:uppercase}

        /* HOW */
        .how{padding:90px 6%;background:var(--white);border-top:1px solid var(--border)}
        .how-inner{max-width:1060px;margin:0 auto}
        .how-hd{text-align:center;margin-bottom:60px}
        .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:40px;position:relative}
        .steps::before{content:'';position:absolute;top:40px;left:18%;right:18%;height:1px;background:repeating-linear-gradient(90deg,var(--border) 0,var(--border) 6px,transparent 6px,transparent 14px)}
        .step{text-align:center;padding:0 16px}
        .step-c{width:80px;height:80px;margin:0 auto 22px;border-radius:50%;background:var(--orange-pale);border:2px solid rgba(212,98,42,.15);display:flex;align-items:center;justify-content:center;font-size:1.7rem;position:relative;z-index:1;transition:all .3s}
        .step:hover .step-c{background:var(--orange);border-color:var(--orange);transform:scale(1.08)}
        .step-n{position:absolute;top:-6px;right:-6px;width:24px;height:24px;background:var(--orange);color:#fff;border-radius:50%;font-size:.7rem;font-weight:600;display:flex;align-items:center;justify-content:center}
        .step-title{font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-weight:600;color:var(--text-dark);margin-bottom:10px}
        .step-desc{font-size:.85rem;color:var(--text-light);line-height:1.75}

        /* DONATE CTA */
        .dcta{padding:100px 6%;background:var(--white);text-align:center;position:relative;overflow:hidden}
        .dcta::before{content:'☸';position:absolute;font-size:480px;opacity:.025;top:50%;left:50%;transform:translate(-50%,-50%);color:var(--orange);line-height:1}
        .dcta-inner{position:relative;z-index:1;max-width:680px;margin:0 auto}
        .dcta .sec-title{max-width:100%;text-align:center;margin:16px auto 20px}
        .dcta p{font-size:1.05rem;color:var(--text-mid);line-height:1.8;margin-bottom:40px}
        .chips{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:32px}
        .chip{background:var(--ivory);border:1.5px solid var(--border);color:var(--text-mid);padding:11px 24px;border-radius:40px;font-size:.88rem;cursor:pointer;transition:all .2s;text-decoration:none}
        .chip:hover,.chip.active{background:var(--orange);border-color:var(--orange);color:#fff}
        .btn-donate{display:inline-flex;align-items:center;gap:10px;background:var(--orange);color:#fff;padding:18px 56px;border-radius:50px;text-decoration:none;font-size:1.05rem;font-weight:500;letter-spacing:.04em;transition:all .3s;border:none;cursor:pointer}
        .btn-donate:hover{background:var(--text-dark);transform:translateY(-2px);box-shadow:0 16px 48px rgba(0,0,0,.14)}
        .dcta-note{margin-top:16px;font-size:.78rem;color:var(--text-light)}

        /* TESTIMONIAL */
        .testi{background:var(--cream);border-top:1px solid var(--border);padding:80px 6%}
        .testi-inner{max-width:860px;margin:0 auto;text-align:center}
        .testi-q{font-family:'Cormorant Garamond',serif;font-size:clamp(1.4rem,2.5vw,2rem);font-weight:300;font-style:italic;color:var(--text-dark);line-height:1.55;margin-bottom:24px}
        .testi-q::before{content:'\201C';color:var(--orange)}
        .testi-q::after{content:'\201D';color:var(--orange)}
        .testi-auth{font-size:.83rem;color:var(--text-light);letter-spacing:.08em;text-transform:uppercase}
        .testi-auth strong{color:var(--text-mid);font-weight:500}

        /* FOOTER */
        footer{background:var(--text-dark);padding:60px 6% 28px}
        .foot-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:24px}
        .foot-brand{font-family:'Cormorant Garamond',serif;font-size:1.5rem;color:#fff;margin-bottom:12px}
        .foot-tag{font-size:.84rem;color:rgba(255,255,255,.38);line-height:1.7;max-width:240px}
        .foot-col h4{font-size:.68rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:14px}
        .foot-col ul{list-style:none}
        .foot-col ul li{margin-bottom:9px}
        .foot-col ul a{color:rgba(255,255,255,.48);text-decoration:none;font-size:.86rem;transition:color .2s}
        .foot-col ul a:hover{color:var(--orange-light)}
        .foot-btm{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;font-size:.77rem;color:rgba(255,255,255,.22)}

        /* RESPONSIVE */
        @media(max-width:960px){.hero-content{padding:0 6% 150px}.feat-grid{grid-template-columns:repeat(2,1fr)}.mission-inner{grid-template-columns:1fr;gap:48px}.steps{grid-template-columns:1fr}.steps::before{display:none}.foot-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:680px){.hero{min-height:760px}.hero-content{padding:0 6% 190px}.nav-links{display:none}.stats-bar{width:92%;bottom:-96px;flex-wrap:wrap}.stat-box{min-width:50%;border-bottom:1px solid var(--border)}.feat-grid{grid-template-columns:1fr}.foot-grid{grid-template-columns:1fr}.features{padding-top:180px}}

        /* ANIMATIONS */
        @keyframes fadeUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
        .hero-badge{animation:fadeUp .6s .1s both}
        .hero-title{animation:fadeUp .7s .2s both}
        .hero-desc{animation:fadeUp .7s .35s both}
        .hero-btns{animation:fadeUp .7s .45s both}
        .stats-bar{animation:fadeUp .7s .55s both}
    </style>
</head>
<body>

<nav id="mainNav">
    <a href="index.php" class="nav-logo">
        <div class="nav-logo-mark">☸</div>
        <div>
            <span class="nav-logo-name">Seela suwa herath</span>
            <span class="nav-logo-sub">Monastery Welfare</span>
        </div>
    </a>
    <ul class="nav-links">
        <li><a href="#mission">Our Mission</a></li>
        <li><a href="#how">How It Works</a></li>
        <li><a href="public_transparency.php">Transparency</a></li>
        <li><a href="login.php">Sign In</a></li>
        <li><a href="public_donate.php" class="nav-donate-btn">Donate Now</a></li>
    </ul>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-img">
        <img src="images/hero_bg.png" alt="Monastery"
             onerror="this.style.display='none';document.querySelector('.hero-img-fallback').style.display='block'">
        <div class="hero-img-fallback"></div>
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-overlay-btm"></div>
    <div class="hero-content">
        <div class="hero-badge">☸ &nbsp;<span>Monastery Welfare Platform</span> &nbsp;· Sri Lanka</div>
        <h1 class="hero-title">Caring for Those<br>Who <em>Serve</em><br>Our World</h1>
        <p class="hero-desc">Supporting the health, welfare, and dignified living of monks and clergy through transparent, community-driven generosity.</p>
        <div class="hero-btns">
            <a href="public_donate.php" class="btn-hp">🙏 Donate Today</a>
            <a href="#mission" class="btn-hg">Learn More</a>
        </div>
    </div>
    <div class="stats-bar">
        <div class="stat-box"><div class="stat-num">240+</div><div class="stat-lbl">Monks Supported</div></div>
        <div class="stat-box"><div class="stat-num">18</div><div class="stat-lbl">Doctors on Call</div></div>
        <div class="stat-box"><div class="stat-num">Rs. 2.4M</div><div class="stat-lbl">Raised This Year</div></div>
        <div class="stat-box"><div class="stat-num">100%</div><div class="stat-lbl">Transparent</div></div>
    </div>
</section>

<!-- FEATURES -->
<section class="features">
    <span class="sec-label">What We Do</span>
    <h2 class="sec-title">Complete Welfare for<br>Monastery Communities</h2>
    <div class="feat-grid">
        <div class="feat-card"><div class="feat-icon">🏥</div><div class="feat-title">Healthcare Access</div><div class="feat-desc">Professional doctors, scheduled appointments, and medical support for all residents.</div></div>
        <div class="feat-card"><div class="feat-icon">🙏</div><div class="feat-title">Donor Community</div><div class="feat-desc">Join thousands of compassionate donors supporting monastery welfare year-round.</div></div>
        <div class="feat-card"><div class="feat-icon">📊</div><div class="feat-title">Full Transparency</div><div class="feat-desc">Every rupee tracked and publicly reported — complete accountability to all donors.</div></div>
        <div class="feat-card"><div class="feat-icon">🏠</div><div class="feat-title">Housing & Welfare</div><div class="feat-desc">Room management, daily needs, and ensuring dignified living conditions for monks.</div></div>
    </div>
</section>

<!-- MISSION -->
<section class="mission" id="mission">
    <div class="mission-inner">
        <div class="gal">
            <div class="gal-img tall"><img src="images/img1.jpeg" alt="" onerror="this.parentElement.innerHTML='<div class=ph>🛕</div>'"></div>
            <div class="gal-img"><img src="images/img2.jpeg" alt="" onerror="this.parentElement.innerHTML='<div class=ph>🙏</div>'"></div>
            <div class="gal-img"><img src="images/img3.jpeg" alt="" onerror="this.parentElement.innerHTML='<div class=ph>🏥</div>'"></div>
        </div>
        <div>
            <span class="sec-label">Our Mission</span>
            <h2 class="sec-title">A Community Rooted<br>in Compassion & Care</h2>
            <p class="mission-body">This platform bridges generous donors with monks and clergy who dedicate their lives to spiritual service. Our welfare system ensures they receive proper healthcare, housing, and daily support — with full transparency at every step.</p>
            <p class="mission-body">Every donation is recorded, verified, and published. Donors receive instant receipts and the knowledge that their generosity creates real, lasting impact.</p>
            <div class="mission-quote"><p>To give is not to lose. In generosity, we find the deepest form of abundance.</p></div>
            <div class="inl-stats">
                <div><div class="inl-num">98%</div><div class="inl-lbl">Funds Utilised</div></div>
                <div><div class="inl-num">5 yrs</div><div class="inl-lbl">In Service</div></div>
                <div><div class="inl-num">1,200+</div><div class="inl-lbl">Donors</div></div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="how">
    <div class="how-inner">
        <div class="how-hd"><span class="sec-label">Simple Process</span><h2 class="sec-title">How Your Donation Works</h2></div>
        <div class="steps">
            <div class="step"><div class="step-c">💝<span class="step-n">1</span></div><div class="step-title">Choose to Give</div><div class="step-desc">Select a cause — healthcare, welfare, housing, food — or make a general donation. Any amount matters.</div></div>
            <div class="step"><div class="step-c">🔒<span class="step-n">2</span></div><div class="step-title">Secure Payment</div><div class="step-desc">Pay safely via PayHere, bank transfer, or upload a bank slip. Your personal data is always protected.</div></div>
            <div class="step"><div class="step-c">📋<span class="step-n">3</span></div><div class="step-title">Track Your Impact</div><div class="step-desc">Receive an instant receipt. View our public transparency reports to see exactly how funds are used.</div></div>
        </div>
    </div>
</section>

<!-- DONATE CTA -->
<section class="dcta" id="donate">
    <div class="dcta-inner">
        <span class="sec-label">Make a Difference Today</span>
        <h2 class="sec-title">Your Kindness Sustains<br><em style="font-style:italic;color:var(--orange)">Sacred Service</em></h2>
        <p>Every donation, large or small, directly supports the monks who devote their lives to our community's spiritual wellbeing.</p>
        <div class="chips">
            <a href="public_donate.php?amount=500" class="chip">Rs. 500</a>
            <a href="public_donate.php?amount=1000" class="chip active">Rs. 1,000</a>
            <a href="public_donate.php?amount=2500" class="chip">Rs. 2,500</a>
            <a href="public_donate.php?amount=5000" class="chip">Rs. 5,000</a>
            <a href="public_donate.php?amount=10000" class="chip">Rs. 10,000</a>
            <a href="public_donate.php?amount=0" class="chip">Custom</a>
        </div>
        <a href="public_donate.php" class="btn-donate">🙏 Donate Now</a>
        <p class="dcta-note">🔒 Secure payment &nbsp;·&nbsp; Instant receipt &nbsp;·&nbsp; 100% transparent</p>
    </div>
</section>

<!-- TESTIMONIAL -->
<section class="testi">
    <div class="testi-inner">
        <p class="testi-q">Knowing that my donation goes directly to the welfare of our monks — and seeing the public reports — gives me complete peace of mind. This platform has made giving so simple and trustworthy.</p>
        <p class="testi-auth"><strong>Kamala Perera</strong> &nbsp;·&nbsp; Donor since 2021, Colombo</p>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="foot-grid">
        <div><div class="foot-brand">☸ Seela suwa herath</div><p class="foot-tag">Supporting monastery welfare through community generosity, transparent governance, and compassionate care.</p></div>
        <div class="foot-col"><h4>Platform</h4><ul><li><a href="public_donate.php">Donate</a></li><li><a href="public_transparency.php">Transparency</a></li><li><a href="register.php">Register</a></li><li><a href="login.php">Sign In</a></li></ul></div>
        <div class="foot-col"><h4>Welfare</h4><ul><li><a href="#">Healthcare</a></li><li><a href="#">Housing</a></li><li><a href="#">Appointments</a></li><li><a href="#">Reports</a></li></ul></div>
        <div class="foot-col"><h4>Info</h4><ul><li><a href="#">About Us</a></li><li><a href="#">Contact</a></li><li><a href="#">Privacy Policy</a></li></ul></div>
    </div>
    <div class="foot-btm"><span>© 2026 Seela suwa herath — Monastery Welfare Platform</span><span>Made with 🙏 in Sri Lanka</span></div>
</footer>

<script>
const nav=document.getElementById('mainNav');
window.addEventListener('scroll',()=>nav.classList.toggle('scrolled',window.scrollY>60));
document.querySelectorAll('.chip').forEach(c=>c.addEventListener('click',function(){document.querySelectorAll('.chip').forEach(x=>x.classList.remove('active'));this.classList.add('active')}));
</script>
</body>
</html>
