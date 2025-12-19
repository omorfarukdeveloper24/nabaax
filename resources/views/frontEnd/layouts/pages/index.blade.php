<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Upcoming — Our Website</title>
  <meta name="description" content="A clean, animated one-page 'Coming Soon' landing." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg1:#0f1020; --bg2:#101a3b; --bg3:#0a0b14;
      --text:#e7eaf3; --muted:#a9b0c3; --accent:#7cdbff; --accent2:#a78bfa; --glass:rgba(255,255,255,.06);
      --maxw:1100px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial;
      color:var(--text); background: radial-gradient(1200px 800px at 80% -20%, #2a2d70 0%, transparent 60%),
                 radial-gradient(900px 700px at -10% 20%, #13345e 0%, transparent 50%),
                 linear-gradient(120deg, var(--bg1), var(--bg2) 40%, var(--bg3));
      overflow-x:hidden;
    }

    /* Animated gradient veil */
    .gradient-veil{
      position: fixed; inset: -20vmax; pointer-events:none; z-index:-1;
      background: conic-gradient(from 0deg, #2dd4bf33, #60a5fa33, #a78bfa33, #f472b633, #2dd4bf33);
      filter: blur(70px);
      animation: spin 24s linear infinite;
      opacity:.6;
    }
    @keyframes spin{to{transform:rotate(1turn)}}

    /* Floating orbs */
    .orb{position:absolute; border-radius:50%; mix-blend:screen; filter:blur(20px); opacity:.45;}
    .orb.one{width:220px;height:220px; background:#60a5fa66; top:12%; left:8%; animation:float 13s ease-in-out infinite}
    .orb.two{width:320px;height:320px; background:#a78bfa55; bottom:10%; right:6%; animation:float 17s ease-in-out infinite reverse}
    .orb.three{width:180px;height:180px; background:#22d3ee55; top:60%; left:35%; animation:float 19s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(-10px)}50%{transform:translateY(12px)}}

    header{
      width:100%; position:fixed; top:0; left:0; right:0; z-index:20; backdrop-filter: blur(10px);
      background:linear-gradient(to bottom, rgba(10,12,20,.7), rgba(10,12,20,0));
      border-bottom:1px solid rgba(255,255,255,.06);
    }
    .nav{max-width:var(--maxw); margin:0 auto; display:flex; align-items:center; gap:16px; padding:14px 18px;}
    .brand{display:flex; align-items:center; gap:10px; text-decoration:none; color:var(--text); font-weight:800; letter-spacing:.3px}
    .logo{width:34px; height:34px; border-radius:9px; display:grid; place-items:center; color:#0b1020; font-weight:900;
      background:linear-gradient(135deg, var(--accent), var(--accent2)); box-shadow:0 8px 30px #00000066}

    main{min-height:100vh; display:grid; place-items:center; padding:110px 18px 60px}

    .card{
      width:min(100%, 780px);
      background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
      border:1px solid rgba(255,255,255,.1);
      border-radius:24px; padding:34px; box-shadow:0 20px 60px rgba(0,0,0,.35);
      position:relative; overflow:hidden;
    }
    .card::after{content:""; position:absolute; inset:0; border-radius:inherit; pointer-events:none;
      background: radial-gradient(800px 300px at 20% -20%, rgba(255,255,255,.18), transparent 50%),
                  radial-gradient(600px 220px at 120% 120%, rgba(124,219,255,.15), transparent 60%);
    }

    .eyebrow{display:inline-flex; align-items:center; gap:8px; font-size:.86rem; color:var(--muted);
      background:var(--glass); padding:8px 12px; border-radius:999px; border:1px solid rgba(255,255,255,.1)}
    .ping{width:8px;height:8px;border-radius:50%; background:#34d399; box-shadow:0 0 0 0 #34d399; animation:ping 2s infinite}
    @keyframes ping{0%{box-shadow:0 0 0 0 #34d39966}70%{box-shadow:0 0 0 12px #34d39900}100%{box-shadow:0 0 0 0 #34d39900}}

    h1{font-size:clamp(2.2rem, 4.8vw, 3.8rem); line-height:1.1; margin:16px 0 14px}
    .gradient-text{background:linear-gradient(90deg, #7cdbff, #a78bfa, #f0abfc); -webkit-background-clip:text; background-clip:text; color:transparent}

    .kicker{color:var(--muted); font-size:1.05rem; max-width:56ch}

    /* Animated dots under title */
    .dots{display:inline-block; width:36px; text-align:left}
    .dots span{display:inline-block; width:6px; height:6px; margin-left:4px; background:var(--accent); border-radius:50%; animation:bounce 1.5s infinite}
    .dots span:nth-child(2){animation-delay:.15s}
    .dots span:nth-child(3){animation-delay:.3s}
    @keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}

    .cta-row{display:flex; align-items:center; gap:12px; margin:24px 0 8px; flex-wrap:wrap}
    .btn{
      padding:12px 18px; border-radius:12px; border:1px solid rgba(255,255,255,.14);
      background:linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.04));
      color:var(--text); text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:10px;
      transition: transform .15s ease, filter .15s ease;
    }
    .btn:hover{transform:translateY(-1px); filter:brightness(1.06)}

    .divider{height:1px; background:linear-gradient(to right, transparent, rgba(255,255,255,.12), transparent); margin:26px 0}

    .grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px}
    .feature{background:var(--glass); border:1px solid rgba(255,255,255,.1); padding:16px; border-radius:16px}
    .feature h3{margin:8px 0 6px; font-size:1rem}
    .feature p{margin:0; color:var(--muted); font-size:.95rem}

    /* Footer */
    footer{padding:26px 18px 36px; color:var(--muted); text-align:center}
    .social{display:flex; gap:12px; justify-content:center; margin-top:10px}
    .social a{display:inline-flex; width:38px; height:38px; align-items:center; justify-content:center; border-radius:10px; text-decoration:none; color:var(--text); background:var(--glass); border:1px solid rgba(255,255,255,.08)}

    /* Responsive */
    @media (max-width:820px){
      .grid{grid-template-columns:1fr}
      .card{padding:26px}
    }
  </style>
</head>
<body>
  <div class="gradient-veil" aria-hidden="true"></div>
  <div class="orb one" aria-hidden="true"></div>
  <div class="orb two" aria-hidden="true"></div>
  <div class="orb three" aria-hidden="true"></div>



  <main id="top">
    <section class="card" role="region" aria-labelledby="title">
      <div class="eyebrow"><span class="ping" aria-hidden="true"></span> Coming soon</div>
      <h1 id="title" class="gradient-text">Upcoming our Nabaax <span class="dots" aria-hidden="true"><span></span><span></span><span></span></span></h1>
      <p class="kicker">We’re crafting something special. A fast, modern experience for e‑commerce and websites—all in a single, elegant one‑page design.</p>

      <div class="cta-row">
        <a class="btn" href="#notify" aria-label="Get launch updates">
          <!-- bell icon -->
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14 20a2 2 0 11-4 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M6.3 8.8A5.7 5.7 0 0118 9v4.2c0 .5.2 1 .5 1.4l1 1.3c.6.8.1 2-.9 2H5.4c-1 0-1.6-1.2-1-2l1-1.3c.3-.4.5-.9.5-1.4V9c0-.7.1-1.3.3-1.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          Notify me
        </a>
        <a class="btn" href="#about" aria-label="Learn more">Learn more</a>
      </div>

      <div class="divider"></div>

      <div class="grid" id="about">
        <div class="feature">
          <h3>⚡ Speed first</h3>
          <p>NVMe‑powered hosting & optimized assets so pages feel instant.</p>
        </div>
        <div class="feature">
          <h3>🛍️ E‑commerce ready</h3>
          <p>Built to support stores (WooCommerce, OpenCart, Laravel, you name it).</p>
        </div>
        <div class="feature">
          <h3>🎨 One‑page design</h3>
          <p>All essentials above the fold, smooth scroll & subtle micro‑animations.</p>
        </div>
      </div>
    </section>
  </main>




  <script>
    // Basic helpers
    document.getElementById('yr').textContent = new Date().getFullYear();
    function fakeSubmit(e){ e.preventDefault(); const m=document.getElementById('msg'); m.style.display='block'; m.scrollIntoView({behavior:'smooth', block:'nearest'}); }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click', e=>{ const id=a.getAttribute('href'); if(id.length>1){ e.preventDefault(); document.querySelector(id)?.scrollIntoView({behavior:'smooth'}); } });
    });
  </script>
</body>
</html>
