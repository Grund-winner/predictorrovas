<?php
// login.php
session_start();

// Already logged in? send to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header("Location: admin.php");
    exit();
}

// MODIFIE ICI : ton nouveau nom d'utilisateur admin
$ADMIN_USER = "rovas";
// MODIFIE ICI : ton nouveau mot de passe admin (fort et secret !)
$ADMIN_PASS = "Rovas@2025!";

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';

    if ($u === $ADMIN_USER && $p === $ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid ID or Password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Admin Login</title>

  <!-- Icons (all.min.css) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root{
      --brand:#0d6efd;
      --bg:#f2f4f8;
      --card:#ffffff;
      --txt:#1f2937;
      --muted:#6b7280;
      --error:#ef4444;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;
      background:linear-gradient(180deg, #eef3ff, #f9fafb);
      min-height:100vh;
      display:grid;
      place-items:center;
      color:var(--txt);
    }

    .wrap{
      width:min(92vw, 420px);
      perspective: 1200px;
    }

    .card{
      background:var(--card);
      border-radius:18px;
      padding:28px 26px 24px;
      box-shadow:0 20px 40px rgba(13,110,253,.15), 0 6px 12px rgba(0,0,0,.06);
      transform-origin: 50% 0;
      animation: cardIn .7s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes cardIn{
      from{opacity:0; transform:translateY(14px) rotateX(-8deg) scale(.98)}
      to{opacity:1; transform:translateY(0) rotateX(0) scale(1)}
    }

    .logo{
      width:56px;height:56px;border-radius:14px;
      background:var(--brand);
      display:grid;place-items:center;
      color:#fff;font-size:24px;
      margin:6px 0 14px;
      box-shadow:0 8px 20px rgba(13,110,253,.35);
      animation: pop .6s .3s both;
    }
    @keyframes pop{from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1}}

    h1{
      font-size:22px;margin:4px 0 18px;letter-spacing:.2px
    }
    .muted{color:var(--muted);font-size:13px;margin-top:-4px;margin-bottom:18px}

    .field{
      position:relative;margin:12px 0 16px;
    }
    .field input{
      width:100%;
      padding:14px 44px 14px 44px;
      border:1px solid #e5e7eb;
      border-radius:12px;
      outline:none;
      font-size:15px;
      background:#fff;
      transition:border .2s, box-shadow .2s, transform .08s;
    }
    .field input:focus{
      border-color:var(--brand);
      box-shadow:0 0 0 4px rgba(13,110,253,.12);
    }
    .field .lead{
      position:absolute;left:14px;top:50%;transform:translateY(-50%);
      color:#9ca3af;font-size:18px;
    }
    .field .trail{
      position:absolute;right:12px;top:50%;transform:translateY(-50%);
      color:#9ca3af;font-size:16px;cursor:pointer;user-select:none;
    }

    .btn{
      appearance:none;border:none;cursor:pointer;
      width:100%;padding:14px 16px;
      background:var(--brand);color:#fff;
      border-radius:12px;font-weight:650;font-size:16px;
      letter-spacing:.2px;
      transition: transform .06s ease, box-shadow .2s ease, background .2s ease;
      box-shadow:0 10px 20px rgba(13,110,253,.30);
    }
    .btn:hover{filter:brightness(.98)}
    .btn:active{transform:translateY(1px)}
    .row{
      display:flex;gap:12px;margin-top:10px;align-items:center;justify-content:space-between
    }
    .error{
      background:#fee2e2;border:1px solid #fecaca;color:var(--error);
      padding:10px 12px;border-radius:10px;font-size:14px;margin:6px 0 12px;
      animation: shake .25s linear both;
    }
    @keyframes shake{
      0%,100%{transform:translateX(0)}
      25%{transform:translateX(-4px)}
      75%{transform:translateX(4px)}
    }

    .foot{
      text-align:center;color:var(--muted);font-size:12px;margin-top:14px
    }

    /* small screens already perfect via viewport; nothing extra needed */
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="logo" aria-hidden="true">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <h1>Admin Login</h1>
      <div class="muted">Enter your administrator credentials to continue.</div>

      <?php if ($error): ?>
        <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" novalidate>
        <div class="field">
          <i class="fa-solid fa-user lead"></i>
          <input type="text" name="username" placeholder="Admin ID" required />
        </div>
        <div class="field">
          <i class="fa-solid fa-key lead"></i>
          <input id="pw" type="password" name="password" placeholder="Password" required />
          <span class="trail" onclick="togglePw()" title="Show/Hide password">
            <i id="eye" class="fa-solid fa-eye-slash"></i>
          </span>
        </div>
        <button class="btn" type="submit">
          <i class="fa-solid fa-right-to-bracket"></i> Sign In
        </button>
        <div class="row">
          <small class="muted"><i class="fa-regular fa-circle-check"></i> Secure session enabled</small>
        </div>
      </form>

      <div class="foot">&copy; <?= date('Y') ?> Admin Panel</div>
    </div>
  </div>

  <script>
    function togglePw(){
      const pw = document.getElementById('pw');
      const eye = document.getElementById('eye');
      if (pw.type === 'password'){ pw.type='text'; eye.classList.replace('fa-eye-slash','fa-eye'); }
      else { pw.type='password'; eye.classList.replace('fa-eye','fa-eye-slash'); }
    }
    // subtle entrance tilt on pointer move
    const card = document.querySelector('.card');
    let rx=0, ry=0;
    card.addEventListener('pointermove', e=>{
      const r = card.getBoundingClientRect();
      const cx = e.clientX - r.left, cy = e.clientY - r.top;
      rx = ((cy/r.height)-.5)*4;  // tilt X
      ry = ((cx/r.width)-.5)*-4;  // tilt Y
      card.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;
    });
    card.addEventListener('pointerleave', ()=>{ card.style.transform='rotateX(0) rotateY(0)'; });
  </script>
</body>
</html>
