<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo isset($page_title) ? $page_title : 'Футбольный менеджер'; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
<style>
  body { font-family: 'Roboto', sans-serif; background: #f8f9fa; margin:0; padding:0; }
  .container { max-width: 900px; margin: 20px auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
  .player-header { display: flex; align-items: center; gap: 20px; }
  .player-photo { width: 140px; height: 140px; border-radius: 8px; object-fit: cover; border: 1px solid #ccc; }
  .player-info h1 { margin: 0 0 10px; }
  nav.tabs { margin-top: 20px; border-bottom: 1px solid #ddd; }
  nav.tabs a { padding: 10px 20px; display: inline-block; color: #333; text-decoration: none; border: 1px solid transparent; border-bottom: none; }
  nav.tabs a.active { font-weight: bold; border-color: #ddd #ddd white; background: white; }
  nav.tabs a:hover { background: #f1f1f1; }
  .tab-content { padding: 20px 0; }
</style>
</head>
<body>
<div class="container">
