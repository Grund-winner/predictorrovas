<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Broadcast Panel</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/milligram/1.4.1/milligram.min.css" rel="stylesheet">
  <style>
    body { background:#f9f9f9; }
    .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin:20px auto; max-width:600px; }
    .progress { height:18px; background:#eee; border-radius:9px; overflow:hidden; margin-top:12px; }
    .progress-bar { height:100%; background:#2196f3; width:0%; transition:width 0.4s ease; }
    .hidden { display:none; }
    .btn { border-radius:6px; }
    .topbar { background:#2196f3; padding:12px; color:#fff; text-align:center; font-weight:600; margin-bottom:20px; }
  </style>
</head>
<body>
  <div class="topbar">📢 Broadcast Panel</div>

  <div class="card">
    <form id="broadcastForm" enctype="multipart/form-data">
      <label>Broadcast Type</label>
      <select name="type" id="type" required>
        <option value="text">Text</option>
        <option value="image">Image</option>
        <option value="document">Document</option>
        <option value="audio">Audio</option>
      </select>

      <div id="textBox">
        <label>Message</label>
        <textarea name="message" rows="3" placeholder="Enter your message"></textarea>
      </div>

      <div id="fileBox" class="hidden">
        <label>Upload File</label>
        <input type="file" name="file" id="fileInput">
        <label id="captionLabel" class="hidden">Caption</label>
        <input type="text" name="caption" id="caption" class="hidden" placeholder="Optional caption">
      </div>

      <label>Target Users</label>
      <select name="filter">
        <option value="all">All Users</option>
        <option value="registered">Registered Only</option>
        <option value="unregistered">Unregistered Only</option>
        <option value="deposited">Deposited Only</option>
        <option value="undeposited">Undeposited Only</option>
      </select>

      <button type="submit" class="button-primary btn">Start Broadcast</button>
      <button type="button" onclick="history.back()" class="button-primary">🔙 Back</button>
    </form>
  </div>

  <div class="card hidden" id="progressCard">
    <h4>Broadcast Progress</h4>
    <div class="progress"><div class="progress-bar" id="progressBar"></div></div>
    <p id="progressText">0% | Sent: 0 | Failed: 0</p>
  </div>

  <script>
    const typeSelect = document.getElementById('type');
    const fileBox = document.getElementById('fileBox');
    const textBox = document.getElementById('textBox');
    const captionLabel = document.getElementById('captionLabel');
    const captionInput = document.getElementById('caption');

    typeSelect.addEventListener('change', () => {
      if (typeSelect.value === "text") {
        textBox.classList.remove('hidden');
        fileBox.classList.add('hidden');
        captionLabel.classList.add('hidden');
        captionInput.classList.add('hidden');
      } else {
        textBox.classList.add('hidden');
        fileBox.classList.remove('hidden');
        captionLabel.classList.remove('hidden');
        captionInput.classList.remove('hidden');
      }
    });

    document.getElementById('broadcastForm').addEventListener('submit', async function(e){
      e.preventDefault();
      const formData = new FormData(this);
      document.getElementById("progressCard").classList.remove("hidden");
      document.getElementById("progressBar").style.width = "0%";
      document.getElementById("progressText").innerText = "Starting broadcast...";

      fetch("broadcast_worker.php", { method: "POST", body: formData });

      const interval = setInterval(async () => {
        let res = await fetch("progress.json?_=" + Date.now());
        if (!res.ok) return;
        let data = await res.json();
        document.getElementById("progressBar").style.width = data.percent + "%";
        document.getElementById("progressText").innerText = 
          data.percent + "% | Sent: " + data.sent + " | Failed: " + data.failed;

        if (data.status === "done" || data.status === "error") {
          clearInterval(interval);
          if (data.status === "error") {
            alert("❌ Error: " + data.error);
          } else {
            alert("✅ Broadcast Finished!");
          }
        }
      }, 1000);
    });
  </script>
</body>
</html>