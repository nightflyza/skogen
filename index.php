<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mørk Skogen</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}

    .bg {
      position:fixed;
      inset:0;
      background-image: url('skog.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      z-index:0;
      overflow:hidden;
    }

    .overlay {
      position:relative;
      z-index:1;
      width:100%;
      height:100vh;
      pointer-events:none;
    }

    .scanlines::before{
      content:'';
      position:absolute;
      inset:0;
      background-image: linear-gradient(rgba(0,0,0,0.02) 50%, rgba(255,255,255,0.02) 50%);
      background-size: 100% 4px;
      mix-blend-mode: multiply;
      opacity:0.9;
      animation: scanmove 6s linear infinite;
    }

    @keyframes scanmove{
      0%{transform:translateY(0)}
      100%{transform:translateY(2px)}
    }

    .noise::after{
      content:'';
      position:absolute;
      inset:0;
      background-image:
        radial-gradient(circle at 10% 20%, rgba(255,255,255,0.02) 0, transparent 20%),
        radial-gradient(circle at 80% 60%, rgba(0,0,0,0.02) 0, transparent 20%),
        repeating-linear-gradient(0deg, rgba(255,255,255,0.02) 0, rgba(255,255,255,0.02) 1px, transparent 1px, transparent 2px);
      opacity:0.25;
      mix-blend-mode: screen;
      animation: noiseflicker 0.2s steps(2) infinite;
      pointer-events:none;
    }

    @keyframes noiseflicker{
      0%{opacity:0.25}
      50%{opacity:0.08}
      100%{opacity:0.25}
    }

    .glitch {
      position:absolute;
      inset:0;
      pointer-events:none;
      overflow:hidden;
    }

    .glitch .slice{
      position:absolute;
      inset:0;
      background-image: inherit;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      transform: translateZ(0);
      opacity:0.8;
      mix-blend-mode: screen;
    }

    .glitch .r{filter:contrast(1.05) saturate(1.1) drop-shadow(0 0 0 rgba(255,0,0,0.4)); animation: rgmove 6s linear infinite; clip-path: polygon(0 0,100% 0,100% 33%,0 30%);} 
    .glitch .g{filter:contrast(1.03) saturate(1.05) drop-shadow(0 0 0 rgba(0,255,0,0.25)); animation: ggmove 5.5s linear infinite; clip-path: polygon(0 33%,100% 30%,100% 66%,0 66%);} 
    .glitch .b{filter:contrast(1.02) saturate(1) drop-shadow(0 0 0 rgba(0,0,255,0.35)); animation: gbmove 6.4s linear infinite; clip-path: polygon(0 66%,100% 66%,100% 100%,0 100%);} 

    @keyframes rgmove{
      0%{transform:translateX(0)}
      20%{transform:translateX(-6px)}
      40%{transform:translateX(0)}
      60%{transform:translateX(4px)}
      80%{transform:translateX(0)}
      100%{transform:translateX(-2px)}
    }
    @keyframes ggmove{
      0%{transform:translateX(0)}
      25%{transform:translateX(3px)}
      50%{transform:translateX(0)}
      75%{transform:translateX(-3px)}
      100%{transform:translateX(0)}
    }
    @keyframes gbmove{
      0%{transform:translateX(0)}
      30%{transform:translateX(-4px)}
      60%{transform:translateX(2px)}
      100%{transform:translateX(0)}
    }

    .glitch .bar{
      position:absolute;
      left:-10%;
      width:120%;
      height:6px;
      background:rgba(255,255,255,0.03);
      mix-blend-mode: overlay;
      animation: barjump 3s infinite;
      transform: skewX(-10deg);
    }
    .glitch .bar:nth-child(1){top:20%;animation-delay:0s}
    .glitch .bar:nth-child(2){top:40%;animation-delay:0.7s}
    .glitch .bar:nth-child(3){top:60%;animation-delay:1.4s}
    .glitch .bar:nth-child(4){top:80%;animation-delay:2.1s}

    @keyframes barjump{
      0%{transform:translateX(-15%) skewX(-10deg);opacity:0}
      5%{opacity:1}
      20%{transform:translateX(5%) skewX(-10deg);opacity:1}
      30%{opacity:0}
      100%{transform:translateX(-15%) skewX(-10deg);opacity:0}
    }

    .hint{
      position:fixed;left:12px;bottom:12px;color:#fff;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;opacity:0.7;z-index:5;pointer-events:none;text-shadow:0 1px 2px rgba(0,0,0,0.6)
    }
  </style>
</head>
<body>
  <div class="bg" aria-hidden="true"></div>
  <div class="overlay scanlines noise">
    <div class="glitch" aria-hidden="true">
      <div class="slice r" style="background-image:url('skog.jpg');"></div>
      <div class="slice g" style="background-image:url('skog.jpg');"></div>
      <div class="slice b" style="background-image:url('skog.jpg');"></div>
      <div class="bar"></div>
      <div class="bar"></div>
      <div class="bar"></div>
      <div class="bar"></div>
    </div>
  </div>
  <div class="hint">
  Gjoer skogen vond aa ferdes i
  </div>
</body>
</html>

