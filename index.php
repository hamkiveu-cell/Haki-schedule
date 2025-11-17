<?php
declare(strict_types=1);
@ini_set('display_errors', '1');
@error_reporting(E_ALL);
@date_default_timezone_set('UTC');

$phpVersion = PHP_VERSION;
$now = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>New Style</title>
<?php
// Read project preview data from environment
$projectDescription = $_SERVER['PROJECT_DESCRIPTION'] ?? '';
$projectImageUrl = $_SERVER['PROJECT_IMAGE_URL'] ?? '';
?>
<?php if ($projectDescription): ?>
  <!-- Meta description -->
  <meta name="description" content='<?= htmlspecialchars($projectDescription) ?>' />
  <!-- Open Graph meta tags -->
  <meta property="og:description" content="<?= htmlspecialchars($projectDescription) ?>" />
  <!-- Twitter meta tags -->
  <meta property="twitter:description" content="<?= htmlspecialchars($projectDescription) ?>" />
<?php endif; ?>
<?php if ($projectImageUrl): ?>
  <!-- Open Graph image -->
  <meta property="og:image" content="<?= htmlspecialchars($projectImageUrl) ?>" />
  <!-- Twitter image -->
  <meta property="twitter:image" content="<?= htmlspecialchars($projectImageUrl) ?>" />
<?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-color-start: #6a11cb;
      --bg-color-end: #2575fc;
      --text-color: #ffffff;
      --card-bg-color: rgba(255, 255, 255, 0.01);
      --card-border-color: rgba(255, 255, 255, 0.1);
    }
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: linear-gradient(45deg, var(--bg-color-start), var(--bg-color-end));
      color: var(--text-color);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      text-align: center;
      overflow: hidden;
      position: relative;
    }
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M-10 10L110 10M10 -10L10 110" stroke-width="1" stroke="rgba(255,255,255,0.05)"/></svg>');
      animation: bg-pan 20s linear infinite;
      z-index: -1;
    }
    @keyframes bg-pan {
      0% { background-position: 0% 0%; }
      100% { background-position: 100% 100%; }
    }
    main {
      padding: 2rem;
    }
    .card {
      background: var(--card-bg-color);
      border: 1px solid var(--card-border-color);
      border-radius: 16px;
      padding: 2rem;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
    }
    .loader {
      margin: 1.25rem auto 1.25rem;
      width: 48px;
      height: 48px;
      border: 3px solid rgba(255, 255, 255, 0.25);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }
    .hint {
      opacity: 0.9;
    }
    .sr-only {
      position: absolute;
      width: 1px; height: 1px;
      padding: 0; margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap; border: 0;
    }
    h1 {
      font-size: 3rem;
      font-weight: 700;
      margin: 0 0 1rem;
      letter-spacing: -1px;
    }
    p {
      margin: 0.5rem 0;
      font-size: 1.1rem;
    }
    code {
      background: rgba(0,0,0,0.2);
      padding: 2px 6px;
      border-radius: 4px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    footer {
      position: absolute;
      bottom: 1rem;
      font-size: 0.8rem;
      opacity: 0.7;
    }
  </style>
</head>
<body>
  <main>
    <div class="card">
      <h1>Analyzing your requirements and generating your website…</h1>
      <div class="loader" role="status" aria-live="polite" aria-label="Applying initial changes">
        <span class="sr-only">Loading…</span>
      </div>
      <p class="hint"><?= ($_SERVER['HTTP_HOST'] ?? '') === 'appwizzy.com' ? 'AppWizzy' : 'Flatlogic' ?> AI is collecting your requirements and applying the first changes.</p>
      <p class="hint">This page will update automatically as the plan is implemented.</p>
      <p>Runtime: PHP <code><?= htmlspecialchars($phpVersion) ?></code> — UTC <code><?= htmlspecialchars($now) ?></code></p>
    </div>
  </main>
  <footer>
    Page updated: <?= htmlspecialchars($now) ?> (UTC)
  </footer>
</body>
</html>
