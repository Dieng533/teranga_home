<?php
// Simple upload + JSON persistence for demo purposes
// Saves images to ../assets/uploads and appends listing to ../data/family_listings.json

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

// Ensure directories
$root = realpath(__DIR__ . '/..'); // public
$uploadDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
$dataDir = $root . DIRECTORY_SEPARATOR . 'data';
$jsonFile = $dataDir . DIRECTORY_SEPARATOR . 'family_listings.json';

if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
if (!is_dir($dataDir)) { @mkdir($dataDir, 0777, true); }
if (!file_exists($jsonFile)) { @file_put_contents($jsonFile, json_encode([])); }

// Helpers
function sanitize_text($value) {
  return trim(filter_var($value ?? '', FILTER_SANITIZE_STRING));
}

function json_error($message, $code = 400) {
  http_response_code($code);
  echo json_encode(['error' => $message]);
  exit;
}

// Collect fields
$nomFamille   = sanitize_text($_POST['nomFamille'] ?? '');
$localisation = sanitize_text($_POST['localisation'] ?? '');
$presentation = sanitize_text($_POST['presentation'] ?? '');
$capacite     = intval($_POST['capacite'] ?? 0);
$equipements  = sanitize_text($_POST['equipements'] ?? '');
$activites    = sanitize_text($_POST['activites'] ?? '');
$dateDebut    = sanitize_text($_POST['dateDebut'] ?? '');
$dateFin      = sanitize_text($_POST['dateFin'] ?? '');
$conditions   = sanitize_text($_POST['conditions'] ?? '');
$ownerEmail   = sanitize_text($_POST['ownerEmail'] ?? '');

if ($nomFamille === '' || $localisation === '' || $capacite < 1 || $dateDebut === '') {
  json_error('Champs requis manquants ou invalides.');
}

// Handle files
$savedImages = [];
if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
  $allowed = ['jpg','jpeg','png'];
  $count = count($_FILES['photos']['name']);
  for ($i = 0; $i < $count; $i++) {
    $name = $_FILES['photos']['name'][$i];
    $tmp  = $_FILES['photos']['tmp_name'][$i];
    $err  = $_FILES['photos']['error'][$i];
    $size = $_FILES['photos']['size'][$i];
    if ($err !== UPLOAD_ERR_OK) { continue; }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) { continue; }
    if ($size > 5 * 1024 * 1024) { continue; } // 5MB limit
    $newName = 'listing_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;
    if (@move_uploaded_file($tmp, $dest)) {
      // Save as web path relative to public
      $savedImages[] = 'assets/uploads/' . $newName;
    }
  }
}

// Prepare listing
$listing = [
  'id' => round(microtime(true) * 1000),
  'ownerEmail' => $ownerEmail !== '' ? $ownerEmail : null,
  'images' => $savedImages,
  'nomFamille' => $nomFamille,
  'localisation' => $localisation,
  'presentation' => $presentation,
  'capacite' => $capacite,
  'equipements' => $equipements,
  'activites' => $activites,
  'dateDebut' => $dateDebut,
  'dateFin' => $dateFin,
  'conditions' => $conditions,
  'createdAt' => date('c'),
  'updatedAt' => date('c')
];

// Read + upsert JSON
$json = @file_get_contents($jsonFile);
$list = $json ? json_decode($json, true) : [];
if (!is_array($list)) { $list = []; }

// Upsert by ownerEmail if provided
$updated = false;
if ($listing['ownerEmail']) {
  foreach ($list as $idx => $item) {
    if (!empty($item['ownerEmail']) && $item['ownerEmail'] === $listing['ownerEmail']) {
      $listing['createdAt'] = $item['createdAt'] ?? $listing['createdAt'];
      $list[$idx] = $listing;
      $updated = true;
      break;
    }
  }
}
if (!$updated) { $list[] = $listing; }

@file_put_contents($jsonFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['ok' => true, 'listing' => $listing]);
exit;



