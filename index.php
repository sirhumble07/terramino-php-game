<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Terramino</title>
  <link rel="icon" href="https://www.terraform.io/favicon.ico" type="image/x-icon" />

  <style>
    html, body { height:100%; margin:0; }
    body {
      background-image: url("https://github.com/hashicorp/learn-terramino/raw/master/background.png");
      background-size: cover;
      background-position: center;
      display:flex; align-items:center; justify-content:center;
      color:white; font-family: Arial, Helvetica, sans-serif;
    }
    h1 { font-family: Impact, Charcoal, sans-serif; margin:0 0 10px; }
    canvas { border:1px solid white; }

    .page {
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      gap: 20px;
      grid-template-columns: 360px 360px 1fr;
      align-items:start;
    }

    .card {
      background: rgba(0,0,0,0.45);
      border:1px solid rgba(255,255,255,0.2);
      border-radius: 12px;
      padding: 16px;
    }

    .attribute-name { display:inline-block; font-weight:bold; width: 10em; }

    pre {
      white-space: pre-wrap;
      word-break: break-word;
      background: rgba(0,0,0,0.35);
      padding:12px;
      border-radius:8px;
      max-height: 70vh;
      overflow:auto;
    }

    details { margin-top: 10px; }
  </style>
</head>

<?php
// ===================
// Fetch Azure IMDS
// ===================

$apiVersion = '2025-04-07';
$base = "http://169.254.169.254/metadata";

// cURL handle
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Metadata:true'],
    CURLOPT_CONNECTTIMEOUT => 1,
    CURLOPT_TIMEOUT => 2
]);

// Get full metadata JSON
$instanceUrl = "$base/instance?api-version=$apiVersion";
curl_setopt($ch, CURLOPT_URL, $instanceUrl);
$instanceJson = curl_exec($ch);
$instance = $instanceJson ? json_decode($instanceJson, true) : null;

// Convenience leaf helper
function imds_leaf($ch, $path, $apiVersion) {
    $url = "http://169.254.169.254/metadata/instance/$path?api-version=$apiVersion&format=text";
    curl_setopt($ch, CURLOPT_URL, $url);
    return curl_exec($ch);
}

$vm_name    = imds_leaf($ch, "compute/name",       $apiVersion);
$zone       = imds_leaf($ch, "compute/zone",       $apiVersion);
$resourceId = imds_leaf($ch, "compute/resourceId", $apiVersion);

curl_close($ch);

// ========================
// MASK RESOURCE ID
// ========================
function mask_resource_id($id) {
    if (!$id) return "N/A";

    $len = strlen($id);
    if ($len <= 30) return $id;

    $start = substr($id, 0, 20);
    $end   = substr($id, -8);

    return $start . "..." . $end;
}

$maskedId = mask_resource_id($resourceId);

// Pretty metadata JSON
$full_pretty = $instance ? json_encode($instance, JSON_PRETTY_PRINT) : "No metadata available.";
?>

<body>
  <div class="page">

    <!-- LEFT PANEL -->
    <div class="card">
      <h1>Terramino</h1>

      <p><span class="attribute-name">VM Name:</span>
         <code><?= htmlspecialchars($vm_name) ?></code></p>

      <p><span class="attribute-name">Instance ID:</span>
         <code><?= htmlspecialchars($maskedId) ?></code></p>

      <p><span class="attribute-name">Zone:</span>
         <code><?= htmlspecialchars($zone) ?></code></p>

      <p>Use ← → to move, ↑ to rotate, ↓ to drop.</p>
    </div>

    <!-- GAME PANEL -->
    <div class="card">
      <canvas width="320" height="640" id="game"></canvas>
    </div>

    <!-- METADATA PANEL -->
    <div class="card">
      <h2 style="margin-top:0;">Azure IMDS (Full JSON)</h2>

      <details open>
        <summary>Show/Hide full metadata</summary>
        <pre><?= htmlspecialchars($full_pretty) ?></pre>
      </details>
    </div>

  </div>

  <!-- GAME SCRIPT (unchanged) -->
  <script>
    function getRandomInt(min, max) { min = Math.ceil(min); max = Math.floor(max); return Math.floor(Math.random()*(max-min+1))+min; }
    function generateSequence() {
      const sequence = ["I","J","L","O","S","T","Z"];
      while (sequence.length) { const rand = getRandomInt(0, sequence.length-1); tetrominoSequence.push(sequence.splice(rand,1)[0]); }
    }
    function getNextTetromino() {
      if (!tetrominoSequence.length) generateSequence();
      const name = tetrominoSequence.pop();
      const matrix = tetrominos[name];
      const col = playfield[0].length / 2 - Math.ceil(matrix[0].length/2);
      const row = name === "I" ? -1 : -2;
      return { name, matrix, row, col };
    }
    function rotate(m) { const N = m.length - 1; return m.map((r,i)=>r.map((v,j)=>m[N-j][i])); }
    function isValidMove(matrix, cellRow, cellCol) {
      for (let r=0; r<matrix.length; r++) for (let c=0; c<matrix[r].length; c++) if (matrix[r][c] &&
        (cellCol+c<0 || cellCol+c>=playfield[0].length || cellRow+r>=playfield.length || playfield[cellRow+r][cellCol+c])) return false;
      return true;
    }
    function placeTetromino() {
      for (let r=0; r<tetromino.matrix.length; r++) for (let c=0; c<tetromino.matrix[r].length; c++) if (tetromino.matrix[r][c]) {
        if (tetromino.row + r < 0) return showGameOver();
        playfield[tetromino.row+r][tetromino.col+c] = tetromino.name;
      }
      for (let r=playfield.length-1; r>=0;) {
        if (playfield[r].every(cell=>!!cell)) { for (let rr=r; rr>=0; rr--) playfield[rr]=playfield[rr-1]; }
        else r--;
      }
      tetromino = getNextTetromino();
    }
    function showGameOver() {
      cancelAnimationFrame(rAF); gameOver=true;
      context.fillStyle="black"; context.globalAlpha=0.75; context.fillRect(0, canvas.height/2-30, canvas.width, 60);
      context.globalAlpha=1; context.fillStyle="white"; context.font="36px monospace"; context.textAlign="center"; context.textBaseline="middle";
      context.fillText("GAME OVER!", canvas.width/2, canvas.height/2);
    }

    const canvas = document.getElementById("game");
    const context = canvas.getContext("2d");
    const grid = 32;
    const tetrominoSequence = [];
    const playfield = [];
    for (let r=-2; r<20; r++) { playfield[r]=[]; for (let c=0; c<10; c++) playfield[r][c]=0; }
    const tetrominos = {
      I:[[0,0,0,0],[1,1,1,1],[0,0,0,0],[0,0,0,0]],
      J:[[1,0,0],[1,1,1],[0,0,0]],
      L:[[0,0,1],[1,1,1],[0,0,0]],
      O:[[1,1],[1,1]],
      S:[[0,1,1],[1,1,0],[0,0,0]],
      Z:[[1,1,0],[0,1,1],[0,0,0]],
      T:[[0,1,0],[1,1,1],[0,0,0]]
    };
    const colors = { I:"#623CE4", O:"#7C8797", T:"#00BC7F", S:"#CA2171", Z:"#1563ff", J:"#00ACFF", L:"white" };

    let count=0, tetromino=getNextTetromino(), rAF=null, gameOver=false;

    function loop() {
      rAF = requestAnimationFrame(loop);
      context.clearRect(0,0,canvas.width,canvas.height);

      for (let r=0; r<20; r++) for (let c=0; c<10; c++) if (playfield[r][c]) {
        context.fillStyle = colors[playfield[r][c]];
        context.fillRect(c*grid, r*grid, grid-1, grid-1);
      }

      if (tetromino) {
        if (++count > 35) {
          tetromino.row++; count=0;
          if (!isValidMove(tetromino.matrix, tetromino.row, tetromino.col)) { tetromino.row--; placeTetromino(); }
        }
        context.fillStyle = colors[tetromino.name];
        for (let r=0; r<tetromino.matrix.length; r++) for (let c=0; c<tetromino.matrix[r].length; c++) if (tetromino.matrix[r][c]) {
          context.fillRect((tetromino.col+c)*grid, (tetromino.row+r)*grid, grid-1, grid-1);
        }
      }
    }

    document.addEventListener("keydown", e => {
      if (gameOver) return;
      if (e.which===37 || e.which===39) {
        const col = e.which===37 ? tetromino.col-1 : tetromino.col+1;
        if (isValidMove(tetromino.matrix, tetromino.row, col)) tetromino.col = col;
      }
      if (e.which===38) {
        const m = rotate(tetromino.matrix);
        if (isValidMove(m, tetromino.row, tetromino.col)) tetromino.matrix = m;
      }
      if (e.which===40) {
        const row = tetromino.row + 1;
        if (!isValidMove(tetromino.matrix, row, tetromino.col)) { tetromino.row = row - 1; placeTetromino(); return; }
        tetromino.row = row;
      }
    });

    rAF = requestAnimationFrame(loop);
  </script>

</body>
</html>
